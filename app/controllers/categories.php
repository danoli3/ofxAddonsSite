<?php
declare(strict_types=1);

const OFX_CATEGORY_PREVIEW_SIZE = 8;

function ofx_categories_index(): void
{
    $pdo = ofx_db();

    $stmt = $pdo->query('
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url, cz.category_id, cz.featured
        FROM repos r
        JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type = "Addon" AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0 AND r.archived = 0
        ORDER BY cz.featured DESC, LOWER(r.name) ASC
    ');

    $addonsByCategory = [];
    while ($row = $stmt->fetch()) {
        $addonsByCategory[$row['category_id']][] = $row;
    }

    $categories = $pdo->query('SELECT * FROM categories ORDER BY LOWER(name) ASC')->fetchAll();

    $categories = array_values(array_filter($categories, fn($c) => !empty($addonsByCategory[$c['id']])));

    // A varied preview per category instead of just the first
    // OFX_CATEGORY_PREVIEW_SIZE alphabetically, so the same handful of
    // "a"-named addons don't dominate every category's preview forever.
    $previewByCategory = [];
    foreach ($addonsByCategory as $categoryId => $all) {
        $previewByCategory[$categoryId] = ofx_category_preview_mix($all, OFX_CATEGORY_PREVIEW_SIZE);
    }

    ofx_render('categories/index', [
        'categories' => $categories,
        'addonsByCategory' => $addonsByCategory,
        'previewByCategory' => $previewByCategory,
        'title' => 'Categories',
    ]);
}

// Picks a varied preview of at most $limit addons from $all (which is
// already featured-first, alphabetical): featured ones always lead,
// then a mix of most-starred, most-forked, most-recently-updated, and
// random picks fill the rest - each de-duped against what's already
// chosen, so nothing appears twice in one category's preview. Used only
// for the /categories preview grid; the paginated /categories/{slug}
// page stays plain alphabetical, since re-randomizing every page load
// there would duplicate/skip addons across infinite-scroll pages.
function ofx_category_preview_mix(array $all, int $limit): array
{
    $featured = array_values(array_filter($all, fn($a) => !empty($a['featured'])));
    $rest = array_values(array_filter($all, fn($a) => empty($a['featured'])));

    $picked = array_slice($featured, 0, $limit);
    $pickedIds = array_column($picked, 'id');

    $take = function (array $candidates) use (&$picked, &$pickedIds, $limit): void {
        foreach ($candidates as $addon) {
            if (count($picked) >= $limit) {
                return;
            }
            if (in_array($addon['id'], $pickedIds, true)) {
                continue;
            }
            $picked[] = $addon;
            $pickedIds[] = $addon['id'];
        }
    };

    if (count($picked) < $limit) {
        $byStars = $rest;
        usort($byStars, fn($a, $b) => ($b['stargazers_count'] ?? 0) <=> ($a['stargazers_count'] ?? 0));
        $take(array_slice($byStars, 0, 3));
    }
    if (count($picked) < $limit) {
        $byForks = $rest;
        usort($byForks, fn($a, $b) => ($b['forks_count'] ?? 0) <=> ($a['forks_count'] ?? 0));
        $take(array_slice($byForks, 0, 2));
    }
    if (count($picked) < $limit) {
        $byUpdated = $rest;
        usort($byUpdated, fn($a, $b) => strtotime($b['pushed_at'] ?? '') <=> strtotime($a['pushed_at'] ?? ''));
        $take(array_slice($byUpdated, 0, 2));
    }
    if (count($picked) < $limit) {
        $shuffled = $rest;
        shuffle($shuffled);
        $take($shuffled);
    }

    return $picked;
}

// $slugOrId is the plain name slug (e.g. "gui", the current URL
// shape), or a legacy "{id}" / "{id}-{oldslug}" link from before URLs
// dropped the numeric id - a leading-digits match covers both of
// those old shapes in one branch. Category rows are few, so scanning
// them in PHP for a slug match is cheap and needs no stored/indexed
// slug column.
function ofx_categories_show(string $slugOrId): void
{
    $pdo = ofx_db();
    $isAdmin = !empty(ofx_current_user()['admin'] ?? false);

    $category = null;
    if (preg_match('/^(\d+)/', $slugOrId, $m)) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$m[1]]);
        $category = $stmt->fetch();
    }
    if (!$category) {
        foreach ($pdo->query('SELECT * FROM categories')->fetchAll() as $c) {
            if (ofx_slugify($c['name']) === $slugOrId) {
                $category = $c;
                break;
            }
        }
    }
    if (!$category) {
        ofx_not_found();
        return;
    }
    $id = $category['id'];

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_PAGE_SIZE;
    $fetch = OFX_PAGE_SIZE + 1;

    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url, cz.featured
        FROM repos r
        JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN users u ON u.id = r.user_id
        WHERE cz.category_id = ? AND r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
        ORDER BY cz.featured DESC, LOWER(r.name) ASC, r.id ASC
        LIMIT {$fetch} OFFSET {$offset}
    ");
    $stmt->execute([$id]);
    [$addons, $hasMore] = ofx_paginate_slice($stmt->fetchAll(), OFX_PAGE_SIZE);

    if (ofx_is_ajax()) {
        header('X-Has-More: ' . ($hasMore ? '1' : '0'));
        foreach ($addons as $addon) {
            ofx_category_addon_partial($addon, (int)$id, $isAdmin);
        }
        return;
    }

    ofx_render('categories/show', [
        'category' => $category,
        'addons' => $addons,
        'hasMore' => $hasMore,
        'nextUrl' => ofx_next_page_url(2),
        'isAdmin' => $isAdmin,
        'title' => $category['name'],
    ]);
}
