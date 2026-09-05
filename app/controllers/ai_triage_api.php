<?php
declare(strict_types=1);

const OFX_AI_TRIAGE_TYPES = ['Unsorted', 'Incomplete', 'Spam'];
const OFX_AI_TRIAGE_DEFAULT_LIMIT = 8;
const OFX_AI_TRIAGE_MAX_LIMIT = 20;
// How long a batch stays "claimed" (see ofx_api_triage_batch below)
// before it's fair game again. Long enough to cover a slow README-fetch
// + classify + submit round trip; short enough that a crashed or
// abandoned run doesn't strand those addons for good.
const OFX_AI_TRIAGE_LEASE_MINUTES = 30;

// Every /api/triage/* endpoint is a machine-to-machine API for a locally
// run model, not a browser session - authenticated by a static bearer key
// in .env (AI_TRIAGE_API_KEY) rather than the admin login cookie, the same
// pattern /webhooks/sync already uses for the crawler.
function ofx_api_triage_require_key(): bool
{
    header('Content-Type: application/json');
    // critical if a CDN ever sits in front of this site: /api/triage/batch
    // hands out a different, not-yet-claimed set of addons on every call
    // (see the lease logic below) - a cached response here would silently
    // start serving the same batch to every caller again, exactly the bug
    // the lease was built to fix, just moved to a layer this app can't see.
    header('Cache-Control: private, no-store');
    $secret = ofx_env('AI_TRIAGE_API_KEY');
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $provided = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';

    if (!$secret || !hash_equals($secret, $provided)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        return false;
    }
    return true;
}

// GET /api/triage/batch?limit=8 - a small, not-yet-claimed slice of
// Unsorted/Incomplete/Spam repos for a local model to classify, each with
// its actual README fetched server-side and truncated to a model-sized
// chunk - the point of this endpoint is that a local model has no
// browsing tool of its own to fetch that itself, which is what made the
// old single giant export (which just told the model to go fetch READMEs)
// unworkable for it. Repos already sitting in ai_triage_queue (submitted,
// awaiting review) are excluded outright; repos handed out in a batch
// within the last OFX_AI_TRIAGE_LEASE_MINUTES are excluded too, even if
// never submitted - without that claim, calling this endpoint more than
// once before finishing/submitting the first batch (parallel calls,
// retries, a slow per-addon README fetch) just re-served the same
// addons every time. The claim expires on its own if nothing is ever
// submitted, so a crashed/abandoned run doesn't strand those addons.
function ofx_api_triage_batch(): void
{
    if (!ofx_api_triage_require_key()) {
        return;
    }

    $limit = (int)($_GET['limit'] ?? OFX_AI_TRIAGE_DEFAULT_LIMIT);
    if ($limit < 1) {
        $limit = OFX_AI_TRIAGE_DEFAULT_LIMIT;
    }
    $limit = min($limit, OFX_AI_TRIAGE_MAX_LIMIT);

    $pdo = ofx_db();
    $typePlaceholders = implode(',', array_fill(0, count(OFX_AI_TRIAGE_TYPES), '?'));
    $leaseMinutes = OFX_AI_TRIAGE_LEASE_MINUTES;

    // nosemgrep: php.lang.security.injection.tainted-callable.tainted-callable,php.lang.security.injection.tainted-sql-string.tainted-sql-string -- $typePlaceholders is "?,?"-shaped (count of a hardcoded constant array), $limit is (int)-cast + min()-clamped, $leaseMinutes is a constant; all real values bound via execute()
    $stmt = $pdo->prepare("
        SELECT id, full_name, name, description, type, stargazers_count, pushed_at,
               has_makefile, example_count, has_correct_folder_structure, has_thumbnail, archived
        FROM repos
        WHERE type IN ({$typePlaceholders})
          AND id NOT IN (SELECT repo_id FROM ai_triage_queue)
          AND (ai_triage_batched_at IS NULL OR ai_triage_batched_at < (NOW() - INTERVAL {$leaseMinutes} MINUTE))
        ORDER BY (ai_triage_batched_at IS NULL) DESC, ai_triage_batched_at ASC, updated_at ASC
        LIMIT {$limit}
    ");
    $stmt->execute(OFX_AI_TRIAGE_TYPES);
    $rows = $stmt->fetchAll();

    if (!empty($rows)) {
        $idPlaceholders = implode(',', array_fill(0, count($rows), '?'));
        // nosemgrep: php.lang.security.injection.tainted-callable.tainted-callable,php.lang.security.injection.tainted-sql-string.tainted-sql-string -- $idPlaceholders is a "?,?"-shaped string sized only by count($rows); real ids bound via execute() below
        $pdo->prepare("UPDATE repos SET ai_triage_batched_at = NOW() WHERE id IN ({$idPlaceholders})")
            ->execute(array_column($rows, 'id'));
    }

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM repos
        WHERE type IN ({$typePlaceholders}) AND id NOT IN (SELECT repo_id FROM ai_triage_queue)
          AND (ai_triage_batched_at IS NULL OR ai_triage_batched_at < (NOW() - INTERVAL {$leaseMinutes} MINUTE))
    ");
    $countStmt->execute(OFX_AI_TRIAGE_TYPES);
    $remaining = (int)$countStmt->fetchColumn();

    $categories = $pdo->query('SELECT name FROM categories ORDER BY LOWER(name) ASC')->fetchAll(PDO::FETCH_COLUMN);

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
            'of_version_approximate' => ofx_infer_of_version($row['pushed_at']),
            'readme' => mb_substr((string)(ofx_fetch_readme($row['full_name']) ?? ''), 0, 4000),
        ];
    }, $rows);

    // nosemgrep: php.lang.security.injection.echoed-request.echoed-request -- JSON API response (bearer-token-gated); every field is DB/Github-sourced content, not raw request input, and htmlentities() doesn't apply to a JSON body anyway
    echo json_encode([
        'instructions' => 'For each addon, decide its "type": "Addon" if it is a real, usable openFrameworks '
            . 'addon (assign 1+ "categories" from the list below in that case); "Incomplete" if it looks like a '
            . 'real addon but is missing an example/structure or is too early to use; "Spam" or "Banned" if it '
            . 'has nothing to do with openFrameworks or is not an addon at all (an unmodified fork, a personal '
            . 'project, a tutorial repo, etc) - "Banned" is the general "not really an addon" rejection, same '
            . 'as "Spam" but for cases that aren\'t spam specifically, just not an addon; "Deleted" only if the '
            . 'description/readme indicate the repo no longer exists. Leave "categories" empty for anything '
            . 'that is not type "Addon". Optionally set "of_version" (one of of_versions below) only if the '
            . 'readme states an explicit openFrameworks version requirement - never guess. A short free-text '
            . '"notes" field is optional, shown to the human reviewer only, never applied to anything. POST '
            . 'results as {"entries": [{"full_name": ..., "type": ..., "categories": [...], "of_version": ..., '
            . '"notes": ...}, ...]} to /api/triage/submit with the same Authorization header used here. Nothing '
            . 'is saved to the site until an admin reviews and confirms each entry by hand.',
        'categories' => $categories,
        'of_versions' => array_column(OFX_VERSIONS, 'version'),
        'types' => array_map(fn($t) => $t === 'NonAddon' ? 'Banned' : $t, OFX_REPO_TYPES),
        'addons' => $addons,
        // already excludes the batch just claimed above, since the claim
        // was written before this count ran
        'remaining_after_this_batch' => $remaining,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// POST /api/triage/submit - accepts {"entries": [{full_name, type,
// categories, of_version, notes}, ...]} from the same local model that
// called /api/triage/batch. Entries are only staged into
// ai_triage_queue (upserted per repo, so resubmitting an addon just
// replaces its earlier suggestion) for a human to review/apply on
// /admin/ai-triage/review - nothing here touches repos directly, so a
// bad or hallucinated submission can't corrupt the database on its own.
function ofx_api_triage_submit(): void
{
    if (!ofx_api_triage_require_key()) {
        return;
    }

    $data = json_decode((string)file_get_contents('php://input'), true);
    $entries = is_array($data['entries'] ?? null) ? $data['entries'] : null;
    if ($entries === null) {
        http_response_code(400);
        echo json_encode(['error' => 'expected a JSON body shaped {"entries": [...]}']);
        return;
    }

    $pdo = ofx_db();
    $findStmt = $pdo->prepare('SELECT id FROM repos WHERE LOWER(full_name) = LOWER(?) LIMIT 1');
    $upsert = $pdo->prepare('
        INSERT INTO ai_triage_queue (repo_id, full_name, entry_json, submitted_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), entry_json = VALUES(entry_json), submitted_at = NOW()
    ');

    $accepted = 0;
    $skipped = 0;

    foreach ($entries as $entry) {
        $fullName = trim((string)($entry['full_name'] ?? ''));
        $type = ofx_normalize_repo_type(trim((string)($entry['type'] ?? '')));
        $categories = array_values(array_filter(array_map('trim', $entry['categories'] ?? [])));

        if ($fullName === '' || ($type === '' && empty($categories))) {
            $skipped++;
            continue;
        }
        if ($type !== '' && !in_array($type, OFX_REPO_TYPES, true)) {
            $skipped++;
            continue;
        }

        $findStmt->execute([$fullName]);
        $repoId = $findStmt->fetchColumn();
        if (!$repoId) {
            $skipped++;
            continue;
        }

        $normalized = ['full_name' => $fullName];
        if ($type !== '') {
            $normalized['type'] = $type;
        }
        if (!empty($categories)) {
            $normalized['categories'] = $categories;
        }
        if (!empty($entry['of_version'])) {
            $normalized['of_version'] = trim((string)$entry['of_version']);
        }
        if (!empty($entry['notes'])) {
            $normalized['notes'] = mb_substr(trim((string)$entry['notes']), 0, 500);
        }

        $upsert->execute([$repoId, $fullName, json_encode($normalized)]);
        $accepted++;
    }

    echo json_encode(['accepted' => $accepted, 'skipped' => $skipped]);
}
