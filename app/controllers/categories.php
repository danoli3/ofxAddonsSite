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
        WHERE r.type = "Addon" AND r.hidden_by_owner = 0
        ORDER BY cz.featured DESC, LOWER(r.name) ASC
    ');

    $addonsByCategory = [];
    while ($row = $stmt->fetch()) {
        $addonsByCategory[$row['category_id']][] = $row;
    }

    $categories = $pdo->query('SELECT * FROM categories ORDER BY LOWER(name) ASC')->fetchAll();

    $categories = array_values(array_filter($categories, fn($c) => !empty($addonsByCategory[$c['id']])));

    ofx_render('categories/index', [
        'categories' => $categories,
        'addonsByCategory' => $addonsByCategory,
        'title' => 'Categories',
    ]);
}

function ofx_categories_show(string $id): void
{
    $pdo = ofx_db();
    $isAdmin = !empty(ofx_current_user()['admin'] ?? false);

    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category) {
        ofx_not_found();
        return;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_PAGE_SIZE;
    $fetch = OFX_PAGE_SIZE + 1;

    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url, cz.featured
        FROM repos r
        JOIN categorizations cz ON cz.repo_id = r.id
        LEFT JOIN users u ON u.id = r.user_id
        WHERE cz.category_id = ? AND r.type = 'Addon' AND r.hidden_by_owner = 0
        ORDER BY cz.featured DESC, LOWER(r.name) ASC
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
