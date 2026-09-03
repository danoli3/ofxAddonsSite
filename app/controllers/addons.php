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
        WHERE r.full_name = ? AND r.type = 'Addon' AND r.hidden_by_owner = 0
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

    $readme = ofx_fetch_readme($addon['full_name']);

    ofx_render('addons/show', [
        'addon' => $addon,
        'newerForks' => $newerForks,
        'readme' => $readme,
        'title' => $addon['name'],
    ]);
}

function ofx_render_addons_sorted(?string $sort): void
{

    $order = 'LOWER(r.name) ASC';
    if ($sort === 'freshest') {
        $order = 'r.pushed_at DESC';
    } elseif ($sort === 'popular') {
        $order = 'r.stargazers_count DESC';
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_PAGE_SIZE;
    $fetch = OFX_PAGE_SIZE + 1;

    $pdo = ofx_db();
    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url,
               GROUP_CONCAT(c.name SEPARATOR '||') AS categories
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN categories c ON c.id = cz.category_id
        WHERE r.type = 'Addon' AND r.hidden_by_owner = 0
        GROUP BY r.id
        ORDER BY {$order}
        LIMIT {$fetch} OFFSET {$offset}
    ");
    $stmt->execute();
    [$addons, $hasMore] = ofx_paginate_slice($stmt->fetchAll(), OFX_PAGE_SIZE);

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
