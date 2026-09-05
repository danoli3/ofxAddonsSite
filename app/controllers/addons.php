<?php
declare(strict_types=1);

function ofx_addons_index(): void
{
    ofx_render_addons_sorted($_GET['sort'] ?? null);
}

function ofx_addons_freshest(): void
{
    ofx_render_addons_sorted('freshest');
}

function ofx_addons_popular(): void
{
    ofx_render_addons_sorted('popular');
}

// GET /addons/{owner}/{repo} - the "more info" page linked from every
// addon card: full description, categories, forks that are more
// actively maintained than the addon itself (see the crawler's
// newer_forks field - only tracked for confirmed Addons, only kept
// when a fork's pushed_at is later than the addon's own), and a
// live-fetched README.
function ofx_addons_show(string $owner, string $repo): void
{
    $fullName = "{$owner}/{$repo}";
    $pdo = ofx_db();
    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url,
               GROUP_CONCAT(c.name SEPARATOR '||') AS categories
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN categories c ON c.id = cz.category_id
        WHERE r.full_name = ? AND r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
        GROUP BY r.id
    ");
    $stmt->execute([$fullName]);
    $addon = $stmt->fetch();
    if (!$addon) {
        ofx_not_found();
        return;
    }

    $newerForks = [];
    if (!empty($addon['newer_forks'])) {
        $decoded = json_decode($addon['newer_forks'], true);
        $newerForks = is_array($decoded) ? $decoded : [];
    }

    // Admin-confirmed forks (Github's own fork metadata missed or lost
    // the relationship) that actually have unique commits - shown
    // regardless of recency, unlike the auto-detected list above which
    // only ever includes forks pushed more recently than this addon's
    // own pushed_at. Deduped against the auto-detected list by
    // full_name in case Github does still report the same fork there.
    $confirmedForkNames = array_column($newerForks, 'full_name');
    $stmt = $pdo->prepare("
        SELECT full_name, stargazers_count, confirmed_fork_stats,
               (SELECT login FROM users WHERE id = repos.user_id) AS owner_login,
               (SELECT avatar_url FROM users WHERE id = repos.user_id) AS owner_avatar_url
        FROM repos
        WHERE confirmed_fork_of = ?
    ");
    $stmt->execute([$addon['id']]);
    foreach ($stmt->fetchAll() as $confirmed) {
        if (in_array($confirmed['full_name'], $confirmedForkNames, true)) {
            continue;
        }
        $stats = $confirmed['confirmed_fork_stats'] ? json_decode($confirmed['confirmed_fork_stats'], true) : null;
        if (empty($stats['ahead_by'])) {
            continue;
        }
        $newerForks[] = [
            'full_name' => $confirmed['full_name'],
            'owner_login' => $confirmed['owner_login'],
            'owner_avatar_url' => $confirmed['owner_avatar_url'],
            'stargazers_count' => (int)($confirmed['stargazers_count'] ?? 0),
            'pushed_at' => $stats['last_commit_at'] ?? null,
            'confirmed' => true,
        ];
    }
    usort($newerForks, fn($a, $b) => strcmp($b['pushed_at'] ?? '', $a['pushed_at'] ?? ''));

    $forkParent = null;
    if (!empty($addon['confirmed_fork_of'])) {
        $stmt = $pdo->prepare('SELECT full_name, name FROM repos WHERE id = ? LIMIT 1');
        $stmt->execute([$addon['confirmed_fork_of']]);
        $forkParent = $stmt->fetch() ?: null;
    }

    $aheadBranches = [];
    if (!empty($addon['ahead_branches'])) {
        $decoded = json_decode($addon['ahead_branches'], true);
        $aheadBranches = is_array($decoded) ? $decoded : [];
    }

    $readme = ofx_fetch_readme($addon['full_name']);
    $latestRelease = !empty($addon['has_releases']) ? ofx_fetch_latest_release($addon['full_name']) : null;

    ofx_render('addons/show', [
        'addon' => $addon,
        'newerForks' => $newerForks,
        'forkParent' => $forkParent,
        'aheadBranches' => $aheadBranches,
        'readme' => $readme,
        'latestRelease' => $latestRelease,
        'title' => $addon['name'],
    ]);
}

// GET /search?q=... - fallback for the filter box on /categories, /categories/{id}
// and /addons: those filter client-side against only the addon-cards already in
// the DOM (a handful per category preview, one page of infinite-scroll results),
// so a real addon further down the alphabet or list never gets a match. The JS
// only calls this once the client-side filter finds nothing, so it's a fallback,
// not a replacement.
function ofx_addons_search(): void
{
    $q = trim((string)($_GET['q'] ?? ''));
    $q = mb_substr($q, 0, 80);

    if (mb_strlen($q) < 2) {
        return;
    }

    // Escape LIKE's own wildcard characters (and the escape character itself)
    // so a literal % or _ typed by the user is matched literally, not treated
    // as a wildcard. Bound as a parameter below, so this is about correct
    // matching, not SQL injection - PDO's prepared statement already prevents that.
    $like = '%' . addcslashes($q, '%_\\') . '%';

    $pdo = ofx_db();
    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url,
               GROUP_CONCAT(c.name SEPARATOR '||') AS categories
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN categories c ON c.id = cz.category_id
        WHERE r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
          AND (r.name LIKE ? OR r.full_name LIKE ? OR r.description LIKE ?)
        GROUP BY r.id
        ORDER BY r.stargazers_count DESC
        LIMIT 30
    ");
    $stmt->execute([$like, $like, $like]);
    $addons = $stmt->fetchAll();

    if (empty($addons)) {
        echo '<p class="empty-state">No addons found for &ldquo;' . ofx_h($q) . '&rdquo;.</p>';
        return;
    }

    foreach ($addons as $addon) {
        ofx_addon_partial($addon);
    }
}

// whitelisted, not user-concatenated - safe to interpolate. r.id as a
// final tiebreaker keeps pagination stable across pages - without it,
// rows tied on the sort column (e.g. two repos with the same
// stargazers_count) can come back in a different relative order
// between the page-1 and page-2 queries, showing up twice or not at all
function ofx_addons_sort_order(string $sortKey): string
{
    if ($sortKey === 'freshest') {
        return 'r.pushed_at DESC, r.id ASC';
    }
    if ($sortKey === 'popular') {
        return 'r.stargazers_count DESC, r.id ASC';
    }
    return 'LOWER(r.name) ASC, r.id ASC';
}

// The full (unpaginated) addon list for one sort mode - identical
// between crawl syncs, so it's normally read from the cache built by
// ofx_regenerate_public_caches() and paginated in PHP, rather than
// re-running this GROUP_CONCAT join across 4 tables on every single
// page view of /addons, /freshest, or /popular.
function ofx_addons_sorted_content(string $sortKey): array
{
    $order = ofx_addons_sort_order($sortKey);
    return ofx_db()->query("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url,
               GROUP_CONCAT(c.name SEPARATOR '||') AS categories
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN categories c ON c.id = cz.category_id
        WHERE r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
        GROUP BY r.id
        ORDER BY {$order}
    ")->fetchAll();
}

function ofx_render_addons_sorted(?string $sort): void
{
    $sortKey = in_array($sort, ['freshest', 'popular'], true) ? $sort : 'name';

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_PAGE_SIZE;
    $fetch = OFX_PAGE_SIZE + 1;

    $cached = ofx_cache_read_data("addons-{$sortKey}.json");
    if ($cached !== null) {
        [$addons, $hasMore] = ofx_paginate_slice(array_slice($cached, $offset, $fetch), OFX_PAGE_SIZE);
    } else {
        $order = ofx_addons_sort_order($sortKey);
        $stmt = ofx_db()->prepare("
            SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url,
                   GROUP_CONCAT(c.name SEPARATOR '||') AS categories
            FROM repos r
            LEFT JOIN users u ON u.id = r.user_id
            LEFT JOIN categorizations cz ON cz.repo_id = r.id
            LEFT JOIN categories c ON c.id = cz.category_id
            WHERE r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
            GROUP BY r.id
            ORDER BY {$order}
            LIMIT {$fetch} OFFSET {$offset}
        ");
        $stmt->execute();
        [$addons, $hasMore] = ofx_paginate_slice($stmt->fetchAll(), OFX_PAGE_SIZE);
    }

    if (ofx_is_ajax()) {
        header('X-Has-More: ' . ($hasMore ? '1' : '0'));
        foreach ($addons as $addon) {
            ofx_addon_partial($addon);
        }
        return;
    }

    ofx_render('addons/index', [
        'addons' => $addons,
        'hasMore' => $hasMore,
        'nextUrl' => ofx_next_page_url(2),
        'sort' => $sort,
        'title' => 'All Addons',
    ]);
}
