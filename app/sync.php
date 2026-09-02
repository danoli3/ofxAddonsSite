<?php
declare(strict_types=1);

function ofx_apply_crawl_snapshot(PDO $pdo, array $addons): array
{
    $added = 0;
    $updated = 0;
    $skippedBanned = 0;

    foreach ($addons as $item) {
        $fullName = $item['full_name'] ?? null;
        if (!$fullName) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT id, type, description_curated FROM repos WHERE full_name = ? LIMIT 1');
        $stmt->execute([$fullName]);
        $existing = $stmt->fetch();

        if ($existing && in_array($existing['type'], ['Deleted', 'NonAddon'], true)) {
            $skippedBanned++;
            continue;
        }

        $userId = ofx_sync_upsert_user($pdo, $item['owner'] ?? []);

        $hasCorrectFolder = !empty($item['has_correct_folder_structure']);

        $type = $existing['type'] ?? 'Unsorted';
        if (empty($item['pushed_at'])) {
            $type = 'Empty';
        } elseif (!$hasCorrectFolder) {
            $type = 'Incomplete';
        }

        $params = [
            ofx_sync_to_datetime($item['created_at'] ?? null),
            (int)($item['forks_count'] ?? 0),
            !empty($item['fork']) ? 1 : 0,
            $item['name'] ?? null,
            $item['parent'] ?? null,
            ofx_sync_to_datetime($item['pushed_at'] ?? null),
            $item['source'] ?? null,
            (int)($item['stargazers_count'] ?? 0),
            (int)($item['example_count'] ?? 0),
            !empty($item['has_makefile']) ? 1 : 0,
            $hasCorrectFolder ? 1 : 0,
            !empty($item['has_thumbnail']) ? 1 : 0,
            !empty($item['archived']) ? 1 : 0,
            !empty($item['has_releases']) ? 1 : 0,
            $userId,
            $type,
        ];

        $isCurated = !empty($existing['description_curated']);

        if ($existing) {
            $sql = $isCurated
                ? 'UPDATE repos SET created_at=?, forks_count=?, fork=?, name=?, parent=?,
                   pushed_at=?, source=?, stargazers_count=?, example_count=?, has_makefile=?,
                   has_correct_folder_structure=?, has_thumbnail=?, archived=?, has_releases=?, user_id=?,
                   type=?, updated_at=NOW()
                   WHERE id=?'
                : 'UPDATE repos SET created_at=?, forks_count=?, fork=?, name=?, parent=?,
                   pushed_at=?, source=?, stargazers_count=?, example_count=?, has_makefile=?,
                   has_correct_folder_structure=?, has_thumbnail=?, archived=?, has_releases=?, user_id=?,
                   type=?, description=?, updated_at=NOW()
                   WHERE id=?';
            $execParams = $isCurated ? $params : [...$params, $item['description'] ?? null];
            $pdo->prepare($sql)->execute([...$execParams, $existing['id']]);
            $updated++;
        } else {
            $sql = 'INSERT INTO repos (created_at, forks_count, fork, name, parent, pushed_at,
                    source, stargazers_count, example_count, has_makefile, has_correct_folder_structure,
                    has_thumbnail, archived, has_releases, user_id, type, description, full_name, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())';
            $pdo->prepare($sql)->execute([...$params, $item['description'] ?? null, $fullName]);
            $added++;
        }
    }

    return ['added' => $added, 'updated' => $updated, 'skipped_banned' => $skippedBanned];
}

function ofx_sync_upsert_user(PDO $pdo, array $owner): ?int
{
    if (empty($owner['id'])) {
        return null;
    }

    $uid = (string)$owner['id'];
    $login = $owner['login'] ?? null;
    $avatarUrl = $owner['avatar_url'] ?? null;

    $stmt = $pdo->prepare('SELECT id FROM users WHERE provider = ? AND uid = ? LIMIT 1');
    $stmt->execute(['github', $uid]);
    $existing = $stmt->fetch();

    if (!$existing && $login) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE provider = ? AND login = ? LIMIT 1');
        $stmt->execute(['github', $login]);
        $existing = $stmt->fetch();
    }

    if ($existing) {
        $pdo->prepare('UPDATE users SET uid = ?, login = ?, avatar_url = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$uid, $login, $avatarUrl, $existing['id']]);
        return (int)$existing['id'];
    }

    $pdo->prepare(
        'INSERT INTO users (provider, uid, login, avatar_url, admin, created_at, updated_at)
         VALUES (?, ?, ?, ?, 0, NOW(), NOW())'
    )->execute(['github', $uid, $login, $avatarUrl]);
    return (int)$pdo->lastInsertId();
}

function ofx_sync_to_datetime(?string $iso): ?string
{
    if (!$iso) {
        return null;
    }
    $ts = strtotime($iso);
    return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : null;
}

function ofx_fetch_latest_crawl_snapshot(): ?array
{
    $url = 'https://github.com/danoli3/ofxAddons/releases/latest/download/addons.json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['User-Agent: ofxaddons-site'],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$body) {
        return null;
    }

    $data = json_decode($body, true);
    return is_array($data['addons'] ?? null) ? $data : null;
}

function ofx_banned_full_names(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT full_name FROM repos WHERE type IN ("NonAddon", "Deleted") ORDER BY full_name');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
