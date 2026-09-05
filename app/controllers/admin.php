<?php
declare(strict_types=1);

const OFX_REPO_TYPES = ['Addon', 'Deleted', 'Empty', 'Incomplete', 'NonAddon', 'Spam', 'Unsorted'];
const OFX_ADMIN_TYPES = ['Unsorted', 'Incomplete', 'Spam', 'Addon'];
// Same definition /admin/banned uses - shared here so /my/addons can
// group an owner's own banned repos the same way.
const OFX_BANNED_TYPES = ['NonAddon', 'Deleted'];
// "Curated" isn't a repos.type value - it's a separate tab filtered on
// description_curated=1 (any type), so admins can review/audit every
// hand-written or AI-generated-then-approved description in one place.
const OFX_ADMIN_CURATED_TAB = 'Curated';
// Also not a repos.type value - repos.categories_ai_curated=1, set when
// an admin confirms AI-suggested categories on the import/AI-triage
// review screen. Independent of OFX_ADMIN_CURATED_TAB above - a repo can
// be AI-curated on categories, hand-curated on its description, both, or
// neither, so this is its own tab rather than folded into Curated.
const OFX_ADMIN_AI_CURATED_TAB = 'AiCurated';
// Also not a repos.type value - Addon-type repos with a blank/null
// description, regardless of type tab, so an admin can batch-fill them
// (by hand or via the AI triage export) without hunting across tabs.
const OFX_ADMIN_NO_DESC_TAB = 'NoDescription';
const OFX_ADMIN_PAGE_SIZE = 25;

// Accepts the human-facing "Banned" label as a synonym for the actual
// stored type value "NonAddon" - the admin Type dropdown displays
// NonAddon as "Banned" (see admin-row.php), and the AI triage API is
// told to use "Banned" too (see ai_triage_api.php), so anything parsing
// a type from external input (AI submissions, manual JSON/XML import)
// needs to accept either spelling rather than silently dropping one.
function ofx_normalize_repo_type(string $type): string
{
    return $type === 'Banned' ? 'NonAddon' : $type;
}

function ofx_admin_index(): void
{
    $admin = ofx_require_admin();
    $pdo = ofx_db();

    $type = $_GET['type'] ?? 'Unsorted';
    // "Deleted" is a real repos.type (see OFX_REPO_TYPES) but deliberately
    // left out of OFX_ADMIN_TYPES - that list also scopes the AI triage
    // export/API and a repo an owner has removed from Github doesn't need
    // categorizing, exporting, or re-triaging. It's still a tab here (like
    // Curated/No Description below) purely so an admin can browse/audit
    // what's been marked deleted, without it cluttering those other flows.
    if (!in_array($type, OFX_ADMIN_TYPES, true) && $type !== OFX_ADMIN_CURATED_TAB
        && $type !== OFX_ADMIN_AI_CURATED_TAB && $type !== OFX_ADMIN_NO_DESC_TAB && $type !== 'Deleted') {
        $type = 'Unsorted';
    }
    $isCuratedTab = $type === OFX_ADMIN_CURATED_TAB;
    $isAiCuratedTab = $type === OFX_ADMIN_AI_CURATED_TAB;
    $isNoDescTab = $type === OFX_ADMIN_NO_DESC_TAB;

    // "pushed" = Github's pushed_at (last commit); "updated" = our own
    // updated_at, which moves on every crawl sync or admin edit - lets
    // an admin surface rows the crawler *just* touched, not just repos
    // with recent commits upstream. "thumbnail" puts every repo that
    // already has an ofxaddons_thumbnail.png/override/AI-generated image
    // first (pushed_at as the tiebreaker within each group) - useful for
    // spotting ones still showing the generic ofxAddonTemplate example
    // image, which "has a thumbnail" but isn't really this addon's own.
    $sort = $_GET['sort'] ?? 'pushed';
    if (!in_array($sort, ['pushed', 'updated', 'thumbnail'], true)) {
        $sort = 'pushed';
    }
    if ($sort === 'updated') {
        $order = 'r.updated_at DESC, r.id ASC';
    } elseif ($sort === 'thumbnail') {
        $order = "((r.thumbnail_url_override IS NOT NULL AND r.thumbnail_url_override != '')
            OR r.has_thumbnail = 1 OR r.ai_thumbnail_generated_at IS NOT NULL) DESC, r.pushed_at DESC, r.id ASC";
    } else {
        $order = 'r.pushed_at DESC, r.id ASC';
    }

    $search = trim($_GET['q'] ?? '');

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_ADMIN_PAGE_SIZE;
    $fetch = OFX_ADMIN_PAGE_SIZE + 1;

    // banned repos (NonAddon/Deleted) can end up with a curated
    // description (or AI-curated categories) from before they were
    // banned - they belong on the Banned page, not cluttering these tabs
    if ($isCuratedTab) {
        $where = "r.description_curated = 1 AND r.type NOT IN ('NonAddon', 'Deleted')";
        $params = [];
    } elseif ($isAiCuratedTab) {
        $where = "r.categories_ai_curated = 1 AND r.type NOT IN ('NonAddon', 'Deleted')";
        $params = [];
    } elseif ($isNoDescTab) {
        $where = "r.type = 'Addon' AND (r.description IS NULL OR r.description = '')";
        $params = [];
    } else {
        $where = 'r.type = ?';
        $params = [$type];
    }
    if ($search !== '') {
        $where .= ' AND (r.full_name LIKE ? OR r.name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    // nosemgrep: php.lang.security.injection.tainted-callable.tainted-callable,php.lang.security.injection.tainted-sql-string.tainted-sql-string -- $where only ever contains hardcoded fragments + '?' placeholders (real values bound via $params/execute()); $order/$type are whitelist-validated above
    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE {$where}
        ORDER BY {$order}
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
    $counts[OFX_ADMIN_AI_CURATED_TAB] = (int)$pdo->query(
        "SELECT COUNT(*) FROM repos WHERE categories_ai_curated = 1 AND type NOT IN ('NonAddon', 'Deleted')"
    )->fetchColumn();
    $counts[OFX_ADMIN_NO_DESC_TAB] = (int)$pdo->query(
        "SELECT COUNT(*) FROM repos WHERE type = 'Addon' AND (description IS NULL OR description = '')"
    )->fetchColumn();
    $counts['Deleted'] = (int)$pdo->query("SELECT COUNT(*) FROM repos WHERE type = 'Deleted'")->fetchColumn();
    $reviewCount = (int)$pdo->query('SELECT COUNT(*) FROM repos WHERE ban_appealed = 1')->fetchColumn();
    // confirmed_unique repos are excluded, same as the actual /admin/duplicates
    // query - otherwise this badge count could show a group that page doesn't
    $dupeCount = (int)$pdo->query("
        SELECT COUNT(*) FROM (
            SELECT 1 FROM repos WHERE type = 'Addon' AND hidden_by_owner = 0 AND confirmed_unique = 0
            GROUP BY LOWER(name) HAVING COUNT(*) > 1
        ) t
    ")->fetchColumn();
    $aiQueueCount = (int)$pdo->query('SELECT COUNT(*) FROM ai_triage_queue')->fetchColumn();

    ofx_render('admin/index', [
        'repos' => $repos,
        'repoCategoryIds' => $repoCategoryIds,
        'categories' => $categories,
        'admin' => $admin,
        'type' => $type,
        'sort' => $sort,
        'search' => $search,
        'counts' => $counts,
        'reviewCount' => $reviewCount,
        'dupeCount' => $dupeCount,
        'aiQueueCount' => $aiQueueCount,
        'hasMore' => $hasMore,
        'nextUrl' => ofx_next_page_url(2),
        'maintenanceOn' => is_file(OFX_MAINTENANCE_FLAG_PATH),
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

function ofx_admin_row_partial(array $repo, array $categories, array $selectedCategoryIds, bool $showDismissRequest = false): void
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
        // nosemgrep: php.lang.security.injection.tainted-callable.tainted-callable,php.lang.security.injection.tainted-sql-string.tainted-sql-string -- $placeholders is a "?,?,?" string sized only by count($categoryIds); real values bound via execute($categoryIds) below
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

    // nosemgrep: php.lang.security.injection.echoed-request.echoed-request -- JSON API response (Content-Type: application/json), not HTML; htmlentities() doesn't apply here. $type is whitelist-validated, $id is (int)-cast
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

    // nosemgrep: php.lang.security.injection.echoed-request.echoed-request -- JSON API response; $addition is a model-generated description, not raw request input
    echo json_encode(['status' => 200, 'description' => $addition]);
}

// POST /admin/repos/{id}/generate-thumbnail - admin-only, no per-user
// cap (that's a Phase 2 self-serve /my/addons concern, not built yet).
// Asks DALL-E 3 for a banner image, crops/resizes it to the site's
// 270x70 spec, and saves it to OFX_GENERATED_THUMBNAIL_DIR/{id}.png -
// same file every time a repo is regenerated, so old requests for it
// don't 404 and nothing needs cleaning up.
function ofx_admin_generate_thumbnail(string $id): void
{
    ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    if (!ofx_env('OPENAI_IMAGE_API_KEY') && !ofx_env('OPENAI_API_KEY')) {
        http_response_code(501);
        echo json_encode(['status' => 501, 'error' => ['no OpenAI image API key is configured']]);
        return;
    }

    $stmt = ofx_db()->prepare('SELECT id, full_name, name, description FROM repos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $repo = $stmt->fetch();
    if (!$repo) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['repo not found']]);
        return;
    }

    $readme = ofx_fetch_readme($repo['full_name']);
    $png = ofx_generate_thumbnail_image($repo['name'] ?? $repo['full_name'], (string)$repo['description'], $readme);
    if (!$png) {
        http_response_code(502);
        echo json_encode(['status' => 502, 'error' => ['image generation failed']]);
        return;
    }

    $cropped = ofx_thumbnail_crop_resize($png);
    if (!$cropped) {
        http_response_code(502);
        echo json_encode(['status' => 502, 'error' => ['could not process the generated image']]);
        return;
    }

    if (!is_dir(OFX_GENERATED_THUMBNAIL_DIR)) {
        mkdir(OFX_GENERATED_THUMBNAIL_DIR, 0755, true);
    }
    $path = OFX_GENERATED_THUMBNAIL_DIR . '/' . (int)$repo['id'] . '.png';
    file_put_contents($path, $cropped);

    $pdo = ofx_db();
    $pdo->prepare('UPDATE repos SET ai_thumbnail_generated_at = NOW() WHERE id = ?')->execute([$repo['id']]);
    ofx_log_admin_action($pdo, ofx_current_user()['id'] ?? null, 'generate_thumbnail', (int)$repo['id'], $repo['full_name']);

    echo json_encode([
        'status' => 200,
        'thumbnail_url' => ofx_asset_url('/app/assets/generated-thumbnails/' . (int)$repo['id'] . '.png'),
    ]);
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

// GET /admin/backup.sql.gz - a full logical dump of every table (schema
// + data, as DROP/CREATE/INSERT statements), gzipped. Built in pure PHP
// via SHOW CREATE TABLE + SELECT rather than shelling out to the
// mysqldump binary, since shared hosting can't be relied on to have
// exec()/shell_exec() enabled or the binary on PATH.
function ofx_admin_backup_sql(): void
{
    ofx_require_super_admin();
    $pdo = ofx_db();

    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $sql = "-- ofxAddons database backup\n"
        . "-- Generated " . gmdate('c') . "\n"
        . "SET NAMES utf8mb4;\n"
        . "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $createRow = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
        $createSql = $createRow['Create Table'] ?? '';

        $sql .= "-- ----------------------------\n"
            . "-- Table: {$table}\n"
            . "-- ----------------------------\n"
            . "DROP TABLE IF EXISTS `{$table}`;\n"
            . $createSql . ";\n\n";

        $count = (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        if ($count === 0) {
            continue;
        }

        $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
        $columns = null;
        $rowsInBatch = [];
        $batchSize = 200;

        $flush = function () use (&$rowsInBatch, &$sql, $table, &$columns): void {
            if (empty($rowsInBatch)) {
                return;
            }
            $cols = '`' . implode('`, `', $columns) . '`';
            $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $rowsInBatch) . ";\n";
            $rowsInBatch = [];
        };

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = array_keys($row);
            }
            $values = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string)$v);
            }, $row);
            $rowsInBatch[] = '(' . implode(', ', $values) . ')';
            if (count($rowsInBatch) >= $batchSize) {
                $flush();
            }
        }
        $flush();
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    $filename = 'ofxaddons-' . ($dbName ?: 'db') . '-' . gmdate('Y-m-d') . '.sql.gz';
    $gz = gzencode($sql, 9);

    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($gz));
    echo $gz;
}

function ofx_admin_export(string $format): void
{
    ofx_require_super_admin();
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
    ofx_require_super_admin();
    $pdo = ofx_db();

    $categories = $pdo->query('SELECT name FROM categories ORDER BY LOWER(name) ASC')->fetchAll(PDO::FETCH_COLUMN);

    $placeholders = implode(',', array_fill(0, count(OFX_ADMIN_TYPES), '?'));
    $stmt = $pdo->prepare("
        SELECT id, full_name, name, description, type, stargazers_count, pushed_at,
               has_makefile, example_count, has_correct_folder_structure, has_thumbnail, archived,
               of_version, of_version_curated, categories_ai_curated
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
            // of_version_confirmed: already curated (an admin, or a
            // prior Qwen pass that found an explicit version in the
            // README) - null if nothing's been confirmed yet.
            'of_version_confirmed' => $row['of_version_curated'] ? $row['of_version'] : null,
            // of_version_approximate: guessed from this addon's last
            // pushed date against openFrameworks' real release history -
            // not read from anything the addon itself says.
            'of_version_approximate' => ofx_infer_of_version($row['pushed_at']),
            // categories_ai_curated: this addon's current categories were
            // already assigned by a previous AI pass and confirmed by an
            // admin through the review screen - re-categorizing it is
            // optional, not required, on this run.
            'categories_ai_curated' => (bool)$row['categories_ai_curated'],
        ];
    }, $stmt->fetchAll());

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ofxaddons-triage.json"');
    echo json_encode([
        'instructions' => [
            'of_version' => 'For each addon, fetch its actual README from Github (full_name) and look for an '
                . 'explicit openFrameworks version requirement (e.g. "requires OF 0.11+", "tested on '
                . 'openFrameworks 0.10.0", a of_compatibleWith badge, etc). If you find one, include '
                . '"of_version" set to one of the values in of_versions below in your output for that addon - '
                . 'this becomes the new confirmed version, overriding of_version_confirmed shown here. If the '
                . 'README says nothing explicit, leave "of_version" out of your output entirely - don\'t guess; '
                . 'of_version_approximate already has a date-based guess for that case and doesn\'t need to be '
                . 'repeated or confirmed.',
            'categories_ai_curated' => 'true means an admin already reviewed and confirmed AI-assigned categories '
                . 'for this addon in a previous run - feel free to skip it unless you have a specific correction. '
                . 'false/absent means it still needs a first categorization pass.',
        ],
        'categories' => $categories,
        'of_versions' => array_column(OFX_VERSIONS, 'version'),
        'addons' => $addons,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// POST /admin/import/preview - upload a .json or .xml file in the same
// shape ofx_admin_export/ofx_admin_export_triage produce and show a
// current-vs-proposed diff per addon before anything touches the
// database. Nothing is applied here - each row's own parsed data
// round-trips through the confirm form below as a hidden field, so no
// server-side session state is needed between preview and apply.
function ofx_admin_import_preview(): void
{
    ofx_require_super_admin();

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

    if (empty($entries)) {
        $_SESSION['flash'] = 'Import failed: file contained no entries.';
        ofx_redirect('/admin/repos');
        return;
    }

    $diffs = ofx_admin_import_diff(ofx_db(), $entries);

    ofx_render('admin/import-preview', [
        'diffs' => $diffs,
        'filename' => $name,
        'title' => 'Review import',
    ]);
}

// Read-only: computes what ofx_apply_addon_import() would do for each
// entry (found/not-found, category adds/removes, of_version change, type
// change) against the database as it stands right now, for the review
// screen. Shared by the manual file-upload import and the AI triage
// queue review - entries from either source normalize to the same shape.
function ofx_admin_import_diff(PDO $pdo, array $entries): array
{
    $validVersions = array_column(OFX_VERSIONS, 'version');
    $diffs = [];

    foreach ($entries as $i => $entry) {
        $fullName = trim((string)($entry['full_name'] ?? ''));
        if ($fullName === '') {
            continue;
        }
        $proposedCategories = array_values(array_unique(array_filter(
            array_map('trim', $entry['categories'] ?? [])
        )));
        $proposedVersion = isset($entry['of_version']) ? trim((string)$entry['of_version']) : '';
        if ($proposedVersion !== '' && !in_array($proposedVersion, $validVersions, true)) {
            $proposedVersion = '';
        }
        $proposedType = isset($entry['type']) ? ofx_normalize_repo_type(trim((string)$entry['type'])) : '';
        if ($proposedType !== '' && !in_array($proposedType, OFX_REPO_TYPES, true)) {
            $proposedType = '';
        }
        $notes = isset($entry['notes']) ? trim((string)$entry['notes']) : '';

        // re-encoded from the normalized values above (not the raw
        // upload) so a confirmed row can only ever apply what this same
        // diff actually showed the admin, not whatever else the file said
        $normalizedEntry = ['full_name' => $fullName, 'categories' => $proposedCategories];
        if ($proposedVersion !== '') {
            $normalizedEntry['of_version'] = $proposedVersion;
        }
        if ($proposedType !== '') {
            $normalizedEntry['type'] = $proposedType;
        }

        $stmt = $pdo->prepare(
            'SELECT id, full_name, name, type, of_version, of_version_curated FROM repos WHERE LOWER(full_name) = LOWER(?) LIMIT 1'
        );
        $stmt->execute([$fullName]);
        $repo = $stmt->fetch();

        if (!$repo) {
            $diffs[] = [
                'index' => $i,
                'entry_json' => json_encode($normalizedEntry),
                'found' => false,
                'full_name' => $fullName,
            ];
            continue;
        }

        $catStmt = $pdo->prepare('
            SELECT c.name FROM categorizations cz JOIN categories c ON c.id = cz.category_id
            WHERE cz.repo_id = ? ORDER BY LOWER(c.name)
        ');
        $catStmt->execute([$repo['id']]);
        $currentCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

        $currentVersion = $repo['of_version_curated'] ? $repo['of_version'] : null;

        $diffs[] = [
            'index' => $i,
            'entry_json' => json_encode($normalizedEntry),
            'found' => true,
            'full_name' => $repo['full_name'],
            'name' => $repo['name'],
            'added_categories' => array_values(array_diff($proposedCategories, $currentCategories)),
            'removed_categories' => array_values(array_diff($currentCategories, $proposedCategories)),
            'unchanged_categories' => array_values(array_intersect($currentCategories, $proposedCategories)),
            'current_version' => $currentVersion,
            'proposed_version' => $proposedVersion !== '' ? $proposedVersion : null,
            'version_changed' => $proposedVersion !== '' && $proposedVersion !== $currentVersion,
            'current_type' => $repo['type'],
            'proposed_type' => $proposedType !== '' ? $proposedType : null,
            'type_changed' => $proposedType !== '' && $proposedType !== $repo['type'],
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    return $diffs;
}

// POST /admin/import/confirm - applies only the checked rows from the
// preview screen above. Each row's own normalized entry data travels as
// a hidden field (entry_data[i]); only indexes present in confirm[]
// (the checked boxes) get applied, so an admin can review-and-uncheck
// individual addons rather than it being all-or-nothing.
function ofx_admin_import_confirm(): void
{
    ofx_require_super_admin();

    if (!ofx_csrf_verify()) {
        $_SESSION['flash'] = 'Import failed: invalid request, please try again.';
        ofx_redirect('/admin/repos');
        return;
    }

    $confirmedIndexes = array_map('intval', $_POST['confirm'] ?? []);
    $entryData = $_POST['entry_data'] ?? [];

    $entries = [];
    foreach ($confirmedIndexes as $i) {
        if (!isset($entryData[$i])) {
            continue;
        }
        $decoded = json_decode((string)$entryData[$i], true);
        if (is_array($decoded) && !empty($decoded['full_name'])) {
            $entries[] = $decoded;
        }
    }

    if (empty($entries)) {
        $_SESSION['flash'] = 'Nothing confirmed - no changes applied.';
        ofx_redirect('/admin/repos');
        return;
    }

    $pdo = ofx_db();
    $result = ofx_apply_addon_import($pdo, $entries, true);

    ofx_log_admin_action(
        $pdo,
        ofx_current_user()['id'] ?? null,
        'bulk_import',
        null,
        count($entries) . " confirmed via review: {$result['updated']} applied, {$result['notFound']} not found"
    );

    $_SESSION['flash'] = "Import done: {$result['updated']} addon(s) updated, "
        . "{$result['notFound']} not found in this database.";
    ofx_redirect('/admin/repos');
}

// GET /admin/ai-triage/review - same review-before-applying screen as the
// manual file-upload import above, but sourced from ai_triage_queue (rows
// staged by a local model via POST /api/triage/submit) instead of an
// uploaded file. Reuses ofx_admin_import_diff() so both flows show and
// apply changes identically.
function ofx_admin_ai_queue_review(): void
{
    ofx_require_super_admin();
    $pdo = ofx_db();

    $rows = $pdo->query('SELECT entry_json FROM ai_triage_queue ORDER BY submitted_at ASC')->fetchAll();
    $entries = [];
    foreach ($rows as $row) {
        $decoded = json_decode($row['entry_json'], true);
        if (is_array($decoded)) {
            $entries[] = $decoded;
        }
    }

    $diffs = ofx_admin_import_diff($pdo, $entries);

    ofx_render('admin/import-preview', [
        'diffs' => $diffs,
        'filename' => 'the AI triage queue (' . count($entries) . ' pending)',
        'formAction' => '/admin/ai-triage/confirm',
        'title' => 'Review AI triage queue',
    ]);
}

// POST /admin/ai-triage/confirm - same checked-rows-only apply as
// /admin/import/confirm, except every row shown on the review screen
// (checked or not) is a queued suggestion the admin has now looked at, so
// all of them are removed from ai_triage_queue here - checked ones get
// applied first, unchecked ones are simply discarded. A discarded addon
// stays whatever type it already was, so it's free to be re-picked up by
// a future /api/triage/batch call if it's still Unsorted/Incomplete/Spam.
function ofx_admin_ai_queue_confirm(): void
{
    ofx_require_super_admin();

    if (!ofx_csrf_verify()) {
        $_SESSION['flash'] = 'Review failed: invalid request, please try again.';
        ofx_redirect('/admin/repos');
        return;
    }

    $confirmedIndexes = array_map('intval', $_POST['confirm'] ?? []);
    $entryData = $_POST['entry_data'] ?? [];

    $allEntries = [];
    $confirmedEntries = [];
    foreach ($entryData as $i => $json) {
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded) || empty($decoded['full_name'])) {
            continue;
        }
        $allEntries[] = $decoded;
        if (in_array((int)$i, $confirmedIndexes, true)) {
            $confirmedEntries[] = $decoded;
        }
    }

    if (empty($allEntries)) {
        $_SESSION['flash'] = 'Nothing to review.';
        ofx_redirect('/admin/repos');
        return;
    }

    $pdo = ofx_db();
    $result = ['updated' => 0, 'notFound' => 0];
    if (!empty($confirmedEntries)) {
        $result = ofx_apply_addon_import($pdo, $confirmedEntries, true);
    }

    $deleteStmt = $pdo->prepare('DELETE FROM ai_triage_queue WHERE LOWER(full_name) = LOWER(?)');
    // also clears the batch/submit API's claim (ai_triage_batched_at) - a
    // discarded-but-unchanged repo is otherwise fine to hand out again
    // right away rather than waiting out the rest of its claim window
    $clearClaimStmt = $pdo->prepare('UPDATE repos SET ai_triage_batched_at = NULL WHERE LOWER(full_name) = LOWER(?)');
    foreach ($allEntries as $entry) {
        $deleteStmt->execute([$entry['full_name']]);
        $clearClaimStmt->execute([$entry['full_name']]);
    }

    $discarded = count($allEntries) - count($confirmedEntries);
    ofx_log_admin_action(
        $pdo,
        ofx_current_user()['id'] ?? null,
        'ai_triage_review',
        null,
        count($confirmedEntries) . " confirmed of " . count($allEntries)
            . " reviewed: {$result['updated']} applied, {$result['notFound']} not found, {$discarded} discarded"
    );

    $_SESSION['flash'] = "AI triage review done: {$result['updated']} addon(s) updated, {$discarded} discarded.";
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
        $entries[] = [
            'full_name' => (string)$addon['full_name'],
            'categories' => $categories,
            'of_version' => isset($addon['of_version']) ? (string)$addon['of_version'] : null,
        ];
    }
    return $entries;
}

// Each entry can carry categories, of_version, type, or any mix - a
// version-only pass (e.g. a Qwen run reading READMEs for an explicit
// openFrameworks requirement) doesn't need to touch type/categorization
// at all. An entry can also carry an explicit "type" (e.g. from the AI
// triage queue, which classifies Unsorted/Spam repos rather than just
// categorizing already-confirmed ones) - if present and valid it's
// applied as given; if absent but categories are, it defaults to "Addon"
// as before, since that's the only sensible type for something being
// handed categories.
// $aiCurated marks any categories touched here as repos.categories_ai_curated
// = 1 - set only by the AI-triage preview/confirm flow, never by a human
// hand-editing categories through the picker, so a future triage export
// can tell an admin/model which addons were already AI-sorted.
function ofx_apply_addon_import(PDO $pdo, array $entries, bool $aiCurated = false): array
{
    $updated = 0;
    $notFound = 0;
    $validVersions = array_column(OFX_VERSIONS, 'version');

    foreach ($entries as $entry) {
        $fullName = $entry['full_name'] ?? null;
        $categoryNames = array_filter(array_map('trim', $entry['categories'] ?? []));
        $ofVersion = isset($entry['of_version']) ? trim((string)$entry['of_version']) : '';
        $type = isset($entry['type']) ? ofx_normalize_repo_type(trim((string)$entry['type'])) : '';
        if ($type !== '' && !in_array($type, OFX_REPO_TYPES, true)) {
            $type = '';
        }
        if (!$fullName || (empty($categoryNames) && $ofVersion === '' && $type === '')) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT id FROM repos WHERE LOWER(full_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$fullName]);
        $repoId = $stmt->fetchColumn();
        if (!$repoId) {
            $notFound++;
            continue;
        }

        if (!empty($categoryNames)) {
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

            $pdo->prepare(
                'UPDATE repos SET type = ?, categories_ai_curated = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$type !== '' ? $type : 'Addon', $aiCurated ? 1 : 0, $repoId]);
            $pdo->prepare('DELETE FROM categorizations WHERE repo_id = ?')->execute([$repoId]);
            $insert = $pdo->prepare(
                'INSERT INTO categorizations (category_id, repo_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
            );
            foreach ($categoryIds as $categoryId) {
                $insert->execute([$categoryId, $repoId]);
            }
        } elseif ($type !== '') {
            $pdo->prepare('UPDATE repos SET type = ?, updated_at = NOW() WHERE id = ?')->execute([$type, $repoId]);
        }

        if ($ofVersion !== '' && in_array($ofVersion, $validVersions, true)) {
            $pdo->prepare('UPDATE repos SET of_version = ?, of_version_curated = 1, updated_at = NOW() WHERE id = ?')
                ->execute([$ofVersion, $repoId]);
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
        'isSuperAdmin' => !empty($admin['super_admin']),
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

// POST /admin/admins/{id}/toggle-super - grant or revoke super admin
// access (bulk import, database backup, raw data export, AI triage
// queue). Only a super admin can do this - a plain admin can't escalate
// anyone, including themselves, into that tier. Same self-lockout
// protection as the plain-admin toggle: can't change your own row here.
function ofx_admin_toggle_super_admin(string $id): void
{
    $admin = ofx_require_super_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    if ((int)$id === (int)$admin['id']) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ["Can't change your own super admin access here"]]);
        return;
    }

    $pdo = ofx_db();
    $stmt = $pdo->prepare('SELECT id, login, super_admin FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['user not found']]);
        return;
    }

    $newSuperAdmin = $user['super_admin'] ? 0 : 1;
    $pdo->prepare('UPDATE users SET super_admin = ?, updated_at = NOW() WHERE id = ?')->execute([$newSuperAdmin, $id]);

    ofx_log_admin_action(
        $pdo,
        $admin['id'],
        $newSuperAdmin ? 'grant_super_admin' : 'revoke_super_admin',
        null,
        $user['login'] ?? ('user #' . $id)
    );

    echo json_encode(['status' => 200, 'super_admin' => (bool)$newSuperAdmin]);
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
        // nosemgrep: php.lang.security.injection.echoed-request.echoed-request -- JSON API response, not HTML; $fullName was already validated (only alphanumeric/-/_ owner/repo shape) above
        echo json_encode(['status' => 404, 'error' => "Could not find {$fullName} on Github"]);
        return;
    }

    $pdo = ofx_db();
    $result = ofx_apply_crawl_snapshot($pdo, [$item]);

    ofx_log_admin_action($pdo, $admin['id'], 'manual_add', null, $item['full_name']);

    $stmt = $pdo->prepare('SELECT type FROM repos WHERE full_name = ? LIMIT 1');
    $stmt->execute([$item['full_name']]);
    $type = $stmt->fetchColumn() ?: 'Unsorted';

    // nosemgrep: php.lang.security.injection.echoed-request.echoed-request -- JSON API response; $item comes from Github's own API (ofx_fetch_repo_snapshot), $type is a DB column value
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
    ofx_regenerate_public_caches();

    ofx_log_admin_action(
        $pdo,
        $admin['id'],
        'manual_sync',
        null,
        "{$result['added']} added, {$result['updated']} updated, {$result['skipped_banned']} skipped"
    );

    echo json_encode(['status' => 200] + $result);
}

// POST /admin/regenerate-caches - rebuilds the cached sitemap.xml/json
// and addon-repos.json/banned.json feeds (see app/cache.php) without
// waiting for the next crawl sync - for after a bunch of manual
// categorizing/banning that a sync-triggered regenerate wouldn't cover.
function ofx_admin_regenerate_caches(): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    ofx_regenerate_public_caches();
    ofx_log_admin_action(ofx_db(), $admin['id'], 'regenerate_caches', null, null);

    echo json_encode(['status' => 200]);
}

// GET /admin/cache - when each cached page/feed (see app/cache.php) was
// last regenerated, how long that took, and how big it came out - lets
// an admin see whether the cache is actually fresh/healthy, not just
// trust that it is. Any admin can view this (it's diagnostics, not
// data export); the "Regenerate all now" button on it is the same
// action as the toolbar's "Regenerate feeds" button.
function ofx_admin_cache_stats(): void
{
    ofx_require_admin();

    ofx_render('admin/cache', [
        'meta' => ofx_cache_read_meta(),
        'title' => 'Cache',
    ]);
}

// POST /admin/maintenance/toggle - site-wide kill switch for an active
// attack/DDoS/incident. Super-admin only: flipping this takes the whole
// public site down (see index.php), so it needs the same tier as backup/
// import/export, not day-to-day categorizing. Touching/removing a flag
// file (rather than a DB row) is deliberate - it has to be checkable
// before index.php even opens a DB connection.
function ofx_admin_toggle_maintenance(): void
{
    ofx_require_super_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    if (is_file(OFX_MAINTENANCE_FLAG_PATH)) {
        unlink(OFX_MAINTENANCE_FLAG_PATH);
        $on = false;
    } else {
        file_put_contents(OFX_MAINTENANCE_FLAG_PATH, gmdate('c'));
        $on = true;
    }

    echo json_encode(['maintenanceOn' => $on]);
}

// GET /admin/security - a self-check dashboard, not a guarantee: every
// item here is something the app itself can verify by inspecting its own
// files/config/DB at request time, so it stays accurate as the codebase
// changes instead of being a snapshot someone forgets to update.
function ofx_admin_security(): void
{
    ofx_require_super_admin();
    $pdo = ofx_db();

    $root = dirname(__DIR__, 2);
    $htaccessChecks = [
        [
            'label' => 'Root .htaccess denies dotfiles (.env, .git, .htaccess itself)',
            'pass' => str_contains((string)@file_get_contents($root . '/.htaccess'), 'FilesMatch "^\\."'),
        ],
        [
            'label' => '/app is denied direct web access',
            'pass' => str_contains((string)@file_get_contents($root . '/app/.htaccess'), 'Require all denied'),
        ],
        [
            'label' => '/cron is denied direct web access',
            'pass' => str_contains((string)@file_get_contents($root . '/cron/.htaccess'), 'Require all denied'),
        ],
    ];

    $envPath = $root . '/.env';
    $envExists = is_file($envPath);
    $envPerms = $envExists ? substr(sprintf('%o', fileperms($envPath)), -4) : null;

    $cookieParams = session_get_cookie_params();

    $admins = $pdo->query(
        "SELECT login, admin, super_admin FROM users WHERE admin = 1 OR super_admin = 1 ORDER BY super_admin DESC, login ASC"
    )->fetchAll();

    ofx_render('admin/security', [
        'htaccessChecks' => $htaccessChecks,
        'envExists' => $envExists,
        'envPerms' => $envPerms,
        'cookieParams' => $cookieParams,
        'admins' => $admins,
        'maintenanceOn' => is_file(OFX_MAINTENANCE_FLAG_PATH),
        'syncSecretSet' => (bool)ofx_env('SYNC_SECRET'),
        'aiTriageKeySet' => (bool)ofx_env('AI_TRIAGE_API_KEY'),
        'displayErrorsOff' => ini_get('display_errors') === '' || ini_get('display_errors') === '0',
        'isHttps' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'title' => 'Security',
    ]);
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

// POST /admin/repos/{id}/dismiss-appeal - the classification stands
// (still banned, or still Spam), just clears the review-request flag
// so it drops off the /admin/review queue.
function ofx_admin_dismiss_appeal(string $id): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $pdo = ofx_db();
    $stmt = $pdo->prepare(
        "UPDATE repos SET ban_appealed = 0, updated_at = NOW() WHERE id = ? AND type IN ('NonAddon', 'Spam')"
    );
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'error' => ['not a banned or spam-flagged repo']]);
        return;
    }

    ofx_log_admin_action($pdo, $admin['id'], 'dismiss_appeal', (int)$id, null);

    echo json_encode(['status' => 200]);
}

// GET /admin/review - every repo whose owner has asked for a manual
// look (banned as NonAddon, or auto-classified as Spam by the sync
// pipeline for lacking any recognizable addon structure) via "Ask for
// Admin Review" on /my/addons. Reuses the full categorize row (type
// select, category picker, description) so an admin can reclassify in
// place, plus a Dismiss action if the existing classification stands.
function ofx_admin_review_queue(): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    $stmt = $pdo->query("
        SELECT r.*, u.login AS user_login
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.ban_appealed = 1 AND r.hidden_by_owner = 0
        ORDER BY r.updated_at DESC
    ");
    $repos = $stmt->fetchAll();

    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY LOWER(name) ASC')->fetchAll();
    $repoCategoryIds = ofx_admin_category_ids_for($pdo, array_column($repos, 'id'));

    ofx_render('admin/review', [
        'repos' => $repos,
        'categories' => $categories,
        'repoCategoryIds' => $repoCategoryIds,
        'title' => 'Review requests',
    ]);
}

// GET /admin/duplicates - addons sharing the exact same name are
// usually the same addon twice: a fork Github's own metadata doesn't
// (or no longer does) mark as a fork - the parent's network can go
// "detached" if the original repo was deleted or transferred, or the
// crawler just never had a parent link to begin with. Grouped by name,
// oldest created_at first as the presumed original, so an admin can
// confirm the relationship by hand.
function ofx_admin_duplicates(): void
{
    ofx_require_admin();
    $pdo = ofx_db();

    // confirmed_unique repos (an admin decided these are unrelated
    // projects that just happen to share a name) are excluded from
    // detection entirely - if that leaves only one repo with a given
    // name, it's no longer a "duplicate" group at all
    $dupeNames = $pdo->query("
        SELECT LOWER(name) AS name_key
        FROM repos
        WHERE type = 'Addon' AND hidden_by_owner = 0 AND confirmed_unique = 0
        GROUP BY LOWER(name)
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_COLUMN);

    $groups = [];
    if (!empty($dupeNames)) {
        $placeholders = implode(',', array_fill(0, count($dupeNames), '?'));
        $stmt = $pdo->prepare("
            SELECT r.*, u.login AS user_login
            FROM repos r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.confirmed_unique = 0
              AND LOWER(r.name) IN ({$placeholders})
            ORDER BY LOWER(r.name) ASC, r.created_at ASC
        ");
        $stmt->execute($dupeNames);
        foreach ($stmt->fetchAll() as $repo) {
            // last 200 chars of the actual README, live-fetched - often
            // has an install/usage note or a signature ("by so-and-so")
            // that's a faster tell for "same addon" vs "coincidence"
            // than eyeballing the description alone. Duplicate groups
            // are small in practice, so this stays a handful of calls.
            $readme = ofx_fetch_readme($repo['full_name']);
            $repo['readme_tail'] = $readme ? trim(mb_substr($readme, -200)) : null;
            $groups[strtolower($repo['name'])][] = $repo;
        }
    }

    ofx_render('admin/duplicates', [
        'groups' => $groups,
        'title' => 'Possible duplicate addons',
    ]);
}

// POST /admin/repos/{id}/confirm-fork - body: of=<parent repo id>,
// hide=0|1. Live-compares default branches across the two repos (once,
// cached on the fork's row) so the addon page can show whether this
// fork actually has unique commits, independent of either repo's own
// pushed_at - a fork that hasn't been touched in years still deserves
// to show up if it diverged meaningfully back when it was made.
function ofx_admin_confirm_fork(string $id): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $parentId = (int)($_POST['of'] ?? 0);
    $hide = !empty($_POST['hide']);

    $pdo = ofx_db();
    $stmt = $pdo->prepare('SELECT id, full_name, default_branch FROM repos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $repo = $stmt->fetch();
    $stmt->execute([$parentId]);
    $parent = $stmt->fetch();

    if (!$repo || !$parent || (int)$repo['id'] === (int)$parent['id']) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'error' => ['invalid repo or original']]);
        return;
    }

    $stats = null;
    if ($repo['default_branch'] && $parent['default_branch']) {
        $stats = ofx_fetch_fork_compare(
            $parent['full_name'],
            $parent['default_branch'],
            $repo['full_name'],
            $repo['default_branch']
        );
    }

    $pdo->prepare('
        UPDATE repos
        SET confirmed_fork_of = ?, fork_hidden_by_admin = ?, confirmed_fork_stats = ?, confirmed_unique = 0, updated_at = NOW()
        WHERE id = ?
    ')->execute([$parentId, $hide ? 1 : 0, $stats ? json_encode($stats) : null, $id]);

    ofx_log_admin_action($pdo, $admin['id'], 'confirm_fork', (int)$id, "fork of repo #{$parentId}");

    echo json_encode(['status' => 200, 'stats' => $stats]);
}

// POST /admin/repos/{id}/confirm-unique - the name match is a
// coincidence, not a fork/duplicate: two unrelated addons that happen
// to share a name. Removes it from the /admin/duplicates queue (unless
// a *different* repo still shares its name).
function ofx_admin_confirm_unique(string $id): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $pdo = ofx_db();
    $pdo->prepare('
        UPDATE repos
        SET confirmed_unique = 1, confirmed_fork_of = NULL, fork_hidden_by_admin = 0,
            confirmed_fork_stats = NULL, updated_at = NOW()
        WHERE id = ?
    ')->execute([$id]);

    ofx_log_admin_action($pdo, $admin['id'], 'confirm_unique', (int)$id, null);

    echo json_encode(['status' => 200]);
}

// POST /admin/repos/{id}/unconfirm-unique - undoes confirm-unique.
function ofx_admin_unconfirm_unique(string $id): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $pdo = ofx_db();
    $pdo->prepare('UPDATE repos SET confirmed_unique = 0, updated_at = NOW() WHERE id = ?')->execute([$id]);

    ofx_log_admin_action($pdo, $admin['id'], 'unconfirm_unique', (int)$id, null);

    echo json_encode(['status' => 200]);
}

// POST /admin/repos/{id}/unconfirm-fork - undoes confirm-fork.
function ofx_admin_unconfirm_fork(string $id): void
{
    $admin = ofx_require_admin();
    header('Content-Type: application/json');
    ofx_require_csrf();

    $pdo = ofx_db();
    $pdo->prepare('
        UPDATE repos
        SET confirmed_fork_of = NULL, fork_hidden_by_admin = 0, confirmed_fork_stats = NULL, updated_at = NOW()
        WHERE id = ?
    ')->execute([$id]);

    ofx_log_admin_action($pdo, $admin['id'], 'unconfirm_fork', (int)$id, null);

    echo json_encode(['status' => 200]);
}
