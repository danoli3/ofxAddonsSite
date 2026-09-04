<?php
declare(strict_types=1);

const OFX_REPO_TYPES = ['Addon', 'Deleted', 'Empty', 'Incomplete', 'NonAddon', 'Spam', 'Unsorted'];
const OFX_ADMIN_TYPES = ['Unsorted', 'Incomplete', 'Spam', 'Addon'];
// "Curated" isn't a repos.type value - it's a separate tab filtered on
// description_curated=1 (any type), so admins can review/audit every
// hand-written or AI-generated-then-approved description in one place.
const OFX_ADMIN_CURATED_TAB = 'Curated';
const OFX_ADMIN_PAGE_SIZE = 25;

function ofx_admin_index(): void
{
    $admin = ofx_require_admin();
    $pdo = ofx_db();

    $type = $_GET['type'] ?? 'Unsorted';
    if (!in_array($type, OFX_ADMIN_TYPES, true) && $type !== OFX_ADMIN_CURATED_TAB) {
        $type = 'Unsorted';
    }
    $isCuratedTab = $type === OFX_ADMIN_CURATED_TAB;

    $sort = $_GET['sort'] ?? 'pushed';
    if (!in_array($sort, ['pushed', 'updated'], true)) {
        $sort = 'pushed';
    }
    $orderColumn = $sort === 'updated' ? 'r.updated_at' : 'r.pushed_at';

    $search = trim($_GET['q'] ?? '');

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_ADMIN_PAGE_SIZE;
    $fetch = OFX_ADMIN_PAGE_SIZE + 1;

    // banned repos (NonAddon/Deleted) can end up with a curated
    // description from before they were banned - they belong on the
    // Banned page, not cluttering this review tab
    $where = $isCuratedTab
        ? "r.description_curated = 1 AND r.type NOT IN ('NonAddon', 'Deleted')"
        : 'r.type = ?';
    $params = $isCuratedTab ? [] : [$type];
    if ($search !== '') {
        $where .= ' AND (r.full_name LIKE ? OR r.name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE {$where}
        ORDER BY {$orderColumn} DESC, r.id ASC
        LIMIT {$fetch} OFFSET {$offset}
    ");
    $stmt->execute($params);
    [$repos, $hasMore] = ofx_paginate_slice($stmt->fetchAll(), OFX_ADMIN_PAGE_SIZE);

    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY LOWER(name) ASC')->fetchAll();
    $repoCategoryIds = ofx_admin_category_ids_for($pdo, array_column($repos, 'id'));

    if (ofx_is_ajax()) {
        header('X-Has-More: ' . ($hasMore ? '1' : '0'));
        foreach ($repos as $repo) {
            ofx_admin_row_partial($repo, $categories, $repoCategoryIds[$repo['id']] ?? []);
        }
        return;
    }

    $counts = [];
    foreach (OFX_ADMIN_TYPES as $t) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM repos WHERE type = ?');
        $countStmt->execute([$t]);
        $counts[$t] = (int)$countStmt->fetchColumn();
    }
    $counts[OFX_ADMIN_CURATED_TAB] = (int)$pdo->query(
        "SELECT COUNT(*) FROM repos WHERE description_curated = 1 AND type NOT IN ('NonAddon', 'Deleted')"
    )->fetchColumn();

    ofx_render('admin/index', [
        'repos' => $repos,
        'repoCategoryIds' => $repoCategoryIds,
        'categories' => $categories,
        'admin' => $admin,
        'type' => $type,
        'sort' => $sort,
        'search' => $search,
        'counts' => $counts,
        'hasMore' => $hasMore,
        'nextUrl' => ofx_next_page_url(2),
        'title' => 'Admin',
    ]);
}

function ofx_admin_category_ids_for(PDO $pdo, array $repoIds): array
{
    if (empty($repoIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($repoIds), '?'));
    $stmt = $pdo->prepare("SELECT repo_id, category_id FROM categorizations WHERE repo_id IN ({$placeholders})");
    $stmt->execute($repoIds);

    $result = [];
    while ($row = $stmt->fetch()) {
        $result[$row['repo_id']][] = (int)$row['category_id'];
    }
    return $result;
}

function ofx_admin_row_partial(array $repo, array $categories, array $selectedCategoryIds): void
{
    include __DIR__ . '/../views/partials/admin-row.php';
}

function ofx_admin_update(string $id): void
{
    ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $type = $_POST['type'] ?? null;
    if (!in_array($type, OFX_REPO_TYPES, true)) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ['invalid type']]);
        return;
    }

    $categoryIds = ofx_valid_category_ids(ofx_db(), $_POST['category_ids'] ?? []);

    if ($type === 'Addon' && empty($categoryIds)) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ["Categories can't be empty for an addon"]]);
        return;
    }

    if (array_key_exists('description', $_POST) && mb_strlen($_POST['description']) > OFX_DESCRIPTION_MAX_LENGTH) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ['Description is over ' . OFX_DESCRIPTION_MAX_LENGTH . ' characters']]);
        return;
    }

    $pdo = ofx_db();
    $pdo->beginTransaction();
    try {

        // moving off NonAddon (unbanning) clears any pending appeal too -
        // it's resolved either way, and a future re-ban should start fresh
        if (array_key_exists('description', $_POST)) {
            $generated = !empty($_POST['description_generated']) ? 1 : 0;
            $pdo->prepare(
                "UPDATE repos SET type = ?, description = ?, description_curated = 1,
                 description_generated = ?, ban_appealed = IF(? != 'NonAddon', 0, ban_appealed),
                 updated_at = NOW() WHERE id = ?"
            )->execute([$type, $_POST['description'], $generated, $type, $id]);
        } else {
            $pdo->prepare(
                "UPDATE repos SET type = ?, ban_appealed = IF(? != 'NonAddon', 0, ban_appealed),
                 updated_at = NOW() WHERE id = ?"
            )->execute([$type, $type, $id]);
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

    $categoryNames = [];
    if (!empty($categoryIds)) {
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id IN ({$placeholders})");
        $stmt->execute($categoryIds);
        $categoryNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    $details = "type: {$type}";
    if (!empty($categoryNames)) {
        $details .= '; categories: ' . implode(', ', $categoryNames);
    }
    if (array_key_exists('description', $_POST)) {
        $details .= !empty($_POST['description_generated']) ? '; description: AI-generated' : '; description: edited';
    }
    ofx_log_admin_action($pdo, ofx_current_user()['id'] ?? null, 'update_repo', (int)$id, $details);

    echo json_encode(['status' => 200, 'repo' => ['id' => (int)$id, 'type' => $type]]);
}

function ofx_admin_generate_description(string $id): void
{
    ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    if (!ofx_env('OPENAI_API_KEY')) {
        http_response_code(501);
        echo json_encode(['status' => 501, 'error' => ['OPENAI_API_KEY is not configured']]);
        return;
    }

    $stmt = ofx_db()->prepare('SELECT full_name, name FROM repos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $repo = $stmt->fetch();
    if (!$repo) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['repo not found']]);
        return;
    }

    $readme = ofx_fetch_readme($repo['full_name']);
    if (!$readme) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['could not fetch a README for this repo']]);
        return;
    }

    // The "existing" description comes from the request, not the DB -
    // this is whatever the admin currently has in the textarea (saved
    // or not), so the prompt steers away from restating it and a
    // second click builds on the live draft rather than the last saved
    // value. The client appends this to what it's showing, not us -
    // we don't know what unsaved edits are in that textarea.
    $existing = trim((string)($_POST['existing'] ?? ''));
    $addition = ofx_generate_description($repo['name'] ?? $repo['full_name'], $readme, $existing ?: null);
    if (!$addition) {
        http_response_code(502);
        echo json_encode(['status' => 502, 'error' => ['description generation failed']]);
        return;
    }

    echo json_encode(['status' => 200, 'description' => $addition]);
}

function ofx_admin_banned(): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query('
        SELECT r.*, u.login AS user_login
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type = "NonAddon" AND r.hidden_by_owner = 0
        ORDER BY r.ban_appealed DESC, r.updated_at DESC
    ');

    ofx_render('admin/banned', [
        'repos' => $stmt->fetchAll(),
        'title' => 'Banned',
    ]);
}

// GET /admin/export-banned.json - downloadable list of every banned
// repo (NonAddon/Deleted), for feeding into external automation (e.g.
// a Github Action that wants to skip them). banned_at prefers the
// admin_logs entry that actually recorded the ban over repos.updated_at,
// since that column also moves on unrelated crawl syncs.
function ofx_admin_export_banned(): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query("
        SELECT r.full_name, r.type, r.updated_at, r.ban_appealed,
               (SELECT l.created_at FROM admin_logs l
                WHERE l.repo_id = r.id AND l.details LIKE 'type: NonAddon%'
                ORDER BY l.created_at DESC LIMIT 1) AS logged_banned_at,
               (SELECT u.login FROM admin_logs l
                JOIN users u ON u.id = l.user_id
                WHERE l.repo_id = r.id AND l.details LIKE 'type: NonAddon%'
                ORDER BY l.created_at DESC LIMIT 1) AS banned_by
        FROM repos r
        WHERE r.type IN ('NonAddon', 'Deleted')
        ORDER BY LOWER(r.full_name) ASC
    ");
    $banned = array_map(function (array $row): array {
        $bannedAt = $row['logged_banned_at'] ?? $row['updated_at'];
        $ts = $bannedAt ? strtotime($bannedAt) : false;
        return [
            'full_name' => $row['full_name'],
            'type' => $row['type'],
            'banned_at' => $ts !== false ? gmdate('c', $ts) : null,
            'banned_by' => $row['banned_by'],
            'appealed' => (bool)$row['ban_appealed'],
        ];
    }, $stmt->fetchAll());

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ofxaddons-banned.json"');
    echo json_encode(
        ['generated_at' => gmdate('c'), 'banned' => $banned],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
}

function ofx_admin_export(string $format): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query('
        SELECT r.full_name, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR "||") AS categories
        FROM repos r
        JOIN categorizations cz ON cz.repo_id = r.id
        JOIN categories c ON c.id = cz.category_id
        WHERE r.type = "Addon"
        GROUP BY r.id
        ORDER BY LOWER(r.full_name) ASC
    ');
    $entries = array_map(
        fn($row) => ['full_name' => $row['full_name'], 'categories' => explode('||', $row['categories'])],
        $stmt->fetchAll()
    );

    if ($format === 'xml') {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('addons');
        foreach ($entries as $entry) {
            $xml->startElement('addon');
            $xml->writeAttribute('full_name', $entry['full_name']);
            foreach ($entry['categories'] as $cat) {
                $xml->writeElement('category', $cat);
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();

        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="ofxaddons-export.xml"');
        echo $xml->outputMemory();
        return;
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ofxaddons-export.json"');
    echo json_encode($entries, JSON_PRETTY_PRINT);
}

// GET /admin/export-triage.json - everything still needing a human (or
// AI) decision: Unsorted, Incomplete, Spam. Includes the master
// category list and the same structural signals the crawler uses, so
// a local model has enough to either assign real categories or flag
// something as spam. Import side is deliberately not built yet - the
// output shape a model actually produces needs to be seen first.
function ofx_admin_export_triage(): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    $categories = $pdo->query('SELECT name FROM categories ORDER BY LOWER(name) ASC')->fetchAll(PDO::FETCH_COLUMN);

    $placeholders = implode(',', array_fill(0, count(OFX_ADMIN_TYPES), '?'));
    $stmt = $pdo->prepare("
        SELECT id, full_name, name, description, type, stargazers_count,
               has_makefile, example_count, has_correct_folder_structure, has_thumbnail, archived
        FROM repos
        WHERE type IN ({$placeholders})
        ORDER BY LOWER(full_name) ASC
    ");
    $stmt->execute(OFX_ADMIN_TYPES);
    $addons = array_map(function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'full_name' => $row['full_name'],
            'name' => $row['name'],
            'description' => $row['description'],
            'type' => $row['type'],
            'stargazers_count' => (int)$row['stargazers_count'],
            'has_makefile' => (bool)$row['has_makefile'],
            'example_count' => (int)$row['example_count'],
            'has_correct_folder_structure' => (bool)$row['has_correct_folder_structure'],
            'has_thumbnail' => (bool)$row['has_thumbnail'],
            'archived' => (bool)$row['archived'],
        ];
    }, $stmt->fetchAll());

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ofxaddons-triage.json"');
    echo json_encode(['categories' => $categories, 'addons' => $addons], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function ofx_admin_import(): void
{
    ofx_require_admin();

    if (!ofx_csrf_verify()) {
        $_SESSION['flash'] = 'Import failed: invalid request, please try again.';
        ofx_redirect('/admin/repos');
        return;
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash'] = 'Import failed: no file uploaded.';
        ofx_redirect('/admin/repos');
        return;
    }

    $name = $_FILES['file']['name'];
    $contents = file_get_contents($_FILES['file']['tmp_name']);
    $isXml = str_ends_with(strtolower($name), '.xml');

    try {
        $entries = $isXml ? ofx_parse_import_xml($contents) : ofx_parse_import_json($contents);
    } catch (Throwable $e) {
        $_SESSION['flash'] = 'Import failed: ' . $e->getMessage();
        ofx_redirect('/admin/repos');
        return;
    }

    $pdo = ofx_db();
    $result = ofx_apply_addon_import($pdo, $entries);

    ofx_log_admin_action(
        $pdo,
        ofx_current_user()['id'] ?? null,
        'bulk_import',
        null,
        "{$name}: {$result['updated']} categorized, {$result['notFound']} not found"
    );

    $_SESSION['flash'] = "Import done: {$result['updated']} addon(s) categorized, "
        . "{$result['notFound']} not found in this database.";
    ofx_redirect('/admin/repos');
}

function ofx_parse_import_json(string $contents): array
{
    $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('expected a JSON array of {full_name, categories}');
    }
    return $data;
}

function ofx_parse_import_xml(string $contents): array
{
    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contents);
    libxml_use_internal_errors($prev);
    if ($xml === false) {
        throw new RuntimeException('invalid XML');
    }

    $entries = [];
    foreach ($xml->addon as $addon) {
        $categories = [];
        foreach ($addon->category as $cat) {
            $categories[] = (string)$cat;
        }
        $entries[] = ['full_name' => (string)$addon['full_name'], 'categories' => $categories];
    }
    return $entries;
}

function ofx_apply_addon_import(PDO $pdo, array $entries): array
{
    $updated = 0;
    $notFound = 0;

    foreach ($entries as $entry) {
        $fullName = $entry['full_name'] ?? null;
        $categoryNames = array_filter(array_map('trim', $entry['categories'] ?? []));
        if (!$fullName || empty($categoryNames)) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT id FROM repos WHERE LOWER(full_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$fullName]);
        $repoId = $stmt->fetchColumn();
        if (!$repoId) {
            $notFound++;
            continue;
        }

        $categoryIds = [];
        foreach ($categoryNames as $name) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
            $stmt->execute([$name]);
            $categoryId = $stmt->fetchColumn();
            if (!$categoryId) {
                $pdo->prepare('INSERT INTO categories (name, created_at, updated_at) VALUES (?, NOW(), NOW())')
                    ->execute([$name]);
                $categoryId = $pdo->lastInsertId();
            }
            $categoryIds[] = $categoryId;
        }

        $pdo->prepare('UPDATE repos SET type = "Addon", updated_at = NOW() WHERE id = ?')->execute([$repoId]);
        $pdo->prepare('DELETE FROM categorizations WHERE repo_id = ?')->execute([$repoId]);
        $insert = $pdo->prepare(
            'INSERT INTO categorizations (category_id, repo_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
        );
        foreach ($categoryIds as $categoryId) {
            $insert->execute([$categoryId, $repoId]);
        }

        $updated++;
    }

    return ['updated' => $updated, 'notFound' => $notFound];
}

const OFX_ADMIN_LOG_LIMIT = 200;

function ofx_admin_log(): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query('
        SELECT l.*, u.login AS user_login, u.avatar_url AS user_avatar_url, r.full_name AS repo_full_name,
               r.name AS repo_name
        FROM admin_logs l
        LEFT JOIN users u ON u.id = l.user_id
        LEFT JOIN repos r ON r.id = l.repo_id
        ORDER BY l.created_at DESC
        LIMIT ' . OFX_ADMIN_LOG_LIMIT
    );

    ofx_render('admin/log', [
        'entries' => $stmt->fetchAll(),
        'title' => 'Admin Log',
    ]);
}

function ofx_admin_admins(): void
{
    $admin = ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query('SELECT * FROM users WHERE last_login_at IS NOT NULL ORDER BY last_login_at DESC');

    ofx_render('admin/admins', [
        'users' => $stmt->fetchAll(),
        'currentUserId' => (int)$admin['id'],
        'title' => 'Users',
    ]);
}

function ofx_admin_toggle_admin(string $id): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    if ((int)$id === (int)$admin['id']) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ["Can't change your own admin access here"]]);
        return;
    }

    $pdo = ofx_db();
    $stmt = $pdo->prepare('SELECT id, login, admin FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['user not found']]);
        return;
    }

    $newAdmin = $user['admin'] ? 0 : 1;
    $pdo->prepare('UPDATE users SET admin = ?, updated_at = NOW() WHERE id = ?')->execute([$newAdmin, $id]);

    ofx_log_admin_action(
        $pdo,
        $admin['id'],
        $newAdmin ? 'grant_admin' : 'revoke_admin',
        null,
        $user['login'] ?? ('user #' . $id)
    );

    echo json_encode(['status' => 200, 'admin' => (bool)$newAdmin]);
}

// POST /admin/add-repo - pulls in one specific repo Github search never
// surfaces on its own: crawl.php only searches for repos whose *name*
// starts with "ofx", so a real addon under a different naming
// convention (e.g. drawcall/ofmUI) is invisible to it. Fetches the
// same data the crawler would for one repo and runs it through the
// same sync pipeline as a scheduled crawl, so the result lands in
// Unsorted for normal admin triage exactly like anything the crawler
// finds - no separate insert path to keep in sync with that one.
function ofx_admin_add_repo(): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $fullName = ofx_admin_parse_repo_input((string)($_POST['repo'] ?? ''));
    if (!$fullName) {
        http_response_code(422);
        echo json_encode(['status' => 422, 'error' => 'Enter a Github URL or owner/repo, e.g. drawcall/ofmUI']);
        return;
    }

    $item = ofx_fetch_repo_snapshot($fullName);
    if (!$item) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => "Could not find {$fullName} on Github"]);
        return;
    }

    $pdo = ofx_db();
    $result = ofx_apply_crawl_snapshot($pdo, [$item]);

    ofx_log_admin_action($pdo, $admin['id'], 'manual_add', null, $item['full_name']);

    $stmt = $pdo->prepare('SELECT type FROM repos WHERE full_name = ? LIMIT 1');
    $stmt->execute([$item['full_name']]);
    $type = $stmt->fetchColumn() ?: 'Unsorted';

    echo json_encode(['status' => 200, 'full_name' => $item['full_name'], 'type' => $type] + $result);
}

// Accepts a bare "owner/repo", a full Github URL, or either with a
// trailing ".git" - anything else is rejected before it ever reaches
// the Github API call.
function ofx_admin_parse_repo_input(string $input): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (preg_match('~github\.com/([^/\s]+)/([^/\s?#]+)~i', $input, $m)) {
        $input = $m[1] . '/' . $m[2];
    }
    $input = preg_replace('/\.git$/i', '', $input);
    if (!preg_match('#^[\w.-]+/[\w.-]+$#', $input)) {
        return null;
    }
    return $input;
}

function ofx_admin_sync_now(): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $snapshot = ofx_fetch_latest_crawl_snapshot();
    if (!$snapshot) {
        http_response_code(502);
        echo json_encode(['status' => 502, 'error' => ['could not fetch the latest release from danoli3/ofxAddons']]);
        return;
    }

    $pdo = ofx_db();
    $result = ofx_apply_crawl_snapshot($pdo, $snapshot['addons']);

    ofx_log_admin_action(
        $pdo,
        $admin['id'],
        'manual_sync',
        null,
        "{$result['added']} added, {$result['updated']} updated, {$result['skipped_banned']} skipped"
    );

    echo json_encode(['status' => 200] + $result);
}

function ofx_admin_toggle_featured(string $repoId, string $categoryId): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $pdo = ofx_db();
    $stmt = $pdo->prepare('SELECT featured FROM categorizations WHERE repo_id = ? AND category_id = ? LIMIT 1');
    $stmt->execute([$repoId, $categoryId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['that addon is not in this category']]);
        return;
    }

    $newFeatured = $row['featured'] ? 0 : 1;
    $pdo->prepare('UPDATE categorizations SET featured = ?, updated_at = NOW() WHERE repo_id = ? AND category_id = ?')
        ->execute([$newFeatured, $repoId, $categoryId]);

    ofx_log_admin_action(
        $pdo,
        $admin['id'],
        $newFeatured ? 'feature' : 'unfeature',
        (int)$repoId,
        'category #' . $categoryId
    );

    echo json_encode(['status' => 200, 'featured' => (bool)$newFeatured]);
}

// POST /admin/repos/{id}/dismiss-appeal - the ban stands, just clears
// the appeal flag so it drops back out of the "Appealed" section.
function ofx_admin_dismiss_appeal(string $id): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $pdo = ofx_db();
    $stmt = $pdo->prepare("UPDATE repos SET ban_appealed = 0, updated_at = NOW() WHERE id = ? AND type = 'NonAddon'");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['not a banned repo']]);
        return;
    }

    ofx_log_admin_action($pdo, $admin['id'], 'dismiss_appeal', (int)$id, null);

    echo json_encode(['status' => 200]);
}
