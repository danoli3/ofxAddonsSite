<?php
declare(strict_types=1);

function ofx_unsorted_index(): void
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_PAGE_SIZE;
    $fetch = OFX_PAGE_SIZE + 1;

    $pdo = ofx_db();
    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type IN ('Unsorted', 'Incomplete') AND r.hidden_by_owner = 0
        ORDER BY r.stargazers_count DESC, r.example_count DESC, r.pushed_at DESC, LOWER(r.name) ASC
        LIMIT {$fetch} OFFSET {$offset}
    ");
    $stmt->execute();
    [$repos, $hasMore] = ofx_paginate_slice($stmt->fetchAll(), OFX_PAGE_SIZE);

    if (ofx_is_ajax()) {
        header('X-Has-More: ' . ($hasMore ? '1' : '0'));
        foreach ($repos as $repo) {
            ofx_addon_partial($repo);
        }
        return;
    }

    ofx_render('unsorted/index', [
        'repos' => $repos,
        'hasMore' => $hasMore,
        'nextUrl' => ofx_next_page_url(2),
        'title' => 'Unsorted',
    ]);
}
