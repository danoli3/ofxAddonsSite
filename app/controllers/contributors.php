<?php
declare(strict_types=1);

function ofx_contributors_index(): void
{
    $pdo = ofx_db();
    $stmt = $pdo->query('
        SELECT u.*, COUNT(r.id) AS repo_count
        FROM users u
        JOIN repos r ON r.user_id = u.id AND r.type = "Addon" AND r.hidden_by_owner = 0
        GROUP BY u.id
        ORDER BY repo_count DESC, LOWER(u.login) ASC
    ');
    $users = $stmt->fetchAll();

    ofx_render('contributors/index', [
        'users' => $users,
        'title' => 'Contributors',
    ]);
}

function ofx_contributors_show(string $login): void
{
    $pdo = ofx_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(login) = LOWER(?) LIMIT 1');
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    if (!$user) {
        ofx_not_found();
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM repos WHERE user_id = ? AND type = "Addon" AND hidden_by_owner = 0 ORDER BY LOWER(name) ASC'
    );
    $stmt->execute([$user['id']]);
    $addons = $stmt->fetchAll();

    // Forks of this contributor's own addons that are more actively
    // maintained than the addon itself - reuses the newer_forks data the
    // crawler already collects per-addon, just rolled up across all of
    // this contributor's addons in one place instead of scattered across
    // each addon's own detail page.
    $forks = [];
    foreach ($addons as $addon) {
        if (empty($addon['newer_forks'])) {
            continue;
        }
        $decoded = json_decode($addon['newer_forks'], true);
        foreach (is_array($decoded) ? $decoded : [] as $fork) {
            if (empty($fork['full_name'])) {
                continue;
            }
            $fork['parent_full_name'] = $addon['full_name'];
            $forks[strtolower($fork['full_name'])] = $fork;
        }
    }
    usort($forks, fn($a, $b) => strcmp($b['pushed_at'] ?? '', $a['pushed_at'] ?? ''));

    ofx_render('contributors/show', [
        'user' => $user,
        'addons' => $addons,
        'forks' => $forks,
        'title' => $user['login'],
    ]);
}
