<?php
declare(strict_types=1);

function ofx_unsorted_index(): void
{
    // default: a triage-friendly mix (stars/examples first, so the more
    // promising-looking finds surface without anyone having to sort for
    // them); "pushed"/"updated" are plain single-column sorts, same
    // meaning and labels as the admin table's own Recently pushed/
    // Recently updated toggle - pushed_at is Github's last commit,
    // updated_at is when this database last touched the row (crawl sync
    // or an admin/owner edit), which for freshly-found Unsorted repos is
    // effectively "when the crawler picked it up."
    $sort = $_GET['sort'] ?? '';
    if ($sort === 'updated') {
        $order = 'r.updated_at DESC, r.id ASC';
    } elseif ($sort === 'pushed') {
        $order = 'r.pushed_at DESC, r.id ASC';
    } else {
        $sort = '';
        $order = 'r.stargazers_count DESC, r.example_count DESC, r.pushed_at DESC, LOWER(r.name) ASC, r.id ASC';
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_PAGE_SIZE;
    $fetch = OFX_PAGE_SIZE + 1;

    $pdo = ofx_db();
    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type IN ('Unsorted', 'Incomplete') AND r.hidden_by_owner = 0
        ORDER BY {$order}
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
        'sort' => $sort,
        'title' => 'Unsorted',
    ]);
}
