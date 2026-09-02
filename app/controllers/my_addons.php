<?php
declare(strict_types=1);

function ofx_my_addons_index(): void
{
    $user = ofx_require_user();
    $pdo = ofx_db();

    $stmt = $pdo->prepare('SELECT * FROM repos WHERE user_id = ? ORDER BY LOWER(name) ASC');
    $stmt->execute([$user['id']]);
    $repos = $stmt->fetchAll();

    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY LOWER(name) ASC')->fetchAll();
    $repoCategoryIds = ofx_admin_category_ids_for($pdo, array_column($repos, 'id'));

    ofx_render('my_addons/index', [
        'repos' => $repos,
        'categories' => $categories,
        'repoCategoryIds' => $repoCategoryIds,
        'title' => 'My Addons',
    ]);
}

function ofx_my_addons_update(string $id): void
{
    $user = ofx_require_user();
    header('Content-Type: application/json');

    $pdo = ofx_db();
    $repo = ofx_my_addons_owned_repo($pdo, $id, (int)$user['id']);
    if (!$repo) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'error' => ['not your addon']]);
        return;
    }

    if (array_key_exists('description', $_POST) && mb_strlen($_POST['description']) > OFX_DESCRIPTION_MAX_LENGTH) {
        http_response_code(400);
        echo json_encode([
            'status' => 400,
            'error' => ['Description is over ' . OFX_DESCRIPTION_MAX_LENGTH . ' characters'],
        ]);
        return;
    }

    $thumbnailOverride = trim($_POST['thumbnail_url_override'] ?? '');
    if ($thumbnailOverride !== '' && !filter_var($thumbnailOverride, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ['Thumbnail must be a valid URL']]);
        return;
    }

    $hidden = !empty($_POST['hidden']) ? 1 : 0;
    $categoryIds = array_values(array_filter(array_map('intval', $_POST['category_ids'] ?? [])));

    $pdo->beginTransaction();
    try {
        if (array_key_exists('description', $_POST)) {
            $generated = !empty($_POST['description_generated']) ? 1 : 0;
            $pdo->prepare(
                'UPDATE repos SET description = ?, description_curated = 1, description_generated = ?,
                 hidden_by_owner = ?, thumbnail_url_override = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$_POST['description'], $generated, $hidden, $thumbnailOverride ?: null, $id]);
        } else {
            $pdo->prepare(
                'UPDATE repos SET hidden_by_owner = ?, thumbnail_url_override = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$hidden, $thumbnailOverride ?: null, $id]);
        }

        $pdo->prepare('DELETE FROM categorizations WHERE repo_id = ?')->execute([$id]);
        if (!empty($categoryIds)) {
            $insert = $pdo->prepare(
                'INSERT INTO categorizations (category_id, repo_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
            );
            foreach ($categoryIds as $categoryId) {
                $insert->execute([$categoryId, $id]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 500, 'error' => [$e->getMessage()]]);
        return;
    }

    ofx_log_admin_action(
        $pdo,
        $user['id'],
        'owner_update',
        (int)$id,
        $hidden ? 'hidden from public listing by owner' : 'updated by owner'
    );

    echo json_encode(['status' => 200]);
}

function ofx_my_addons_generate_description(string $id): void
{
    $user = ofx_require_user();
    header('Content-Type: application/json');

    if (!ofx_env('OPENAI_API_KEY')) {
        http_response_code(501);
        echo json_encode(['status' => 501, 'error' => ['OPENAI_API_KEY is not configured']]);
        return;
    }

    $pdo = ofx_db();
    $repo = ofx_my_addons_owned_repo($pdo, $id, (int)$user['id']);
    if (!$repo) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'error' => ['not your addon']]);
        return;
    }

    $readme = ofx_fetch_readme($repo['full_name']);
    if (!$readme) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['could not fetch a README for this repo']]);
        return;
    }

    $description = ofx_generate_description($repo['name'] ?? $repo['full_name'], $readme);
    if (!$description) {
        http_response_code(502);
        echo json_encode(['status' => 502, 'error' => ['description generation failed']]);
        return;
    }

    echo json_encode(['status' => 200, 'description' => $description]);
}

function ofx_my_addons_owned_repo(PDO $pdo, string $repoId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT id, full_name, name, user_id FROM repos WHERE id = ? LIMIT 1');
    $stmt->execute([$repoId]);
    $repo = $stmt->fetch();
    if (!$repo || (int)$repo['user_id'] !== $userId) {
        return null;
    }
    return $repo;
}
