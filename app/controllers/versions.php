<?php
declare(strict_types=1);

const OFX_VERSION_PREVIEW_SIZE = 8;

// GET /versions - every openFrameworks major version with at least one
// addon, newest first, each with a preview of matching addons. A
// version match is either curated (an admin/Qwen-read README value) or
// inferred from pushed_at when nothing's been curated - see
// ofx_addon_of_version().
function ofx_versions_index(): void
{
    $pdo = ofx_db();
    $inferredCase = ofx_of_version_sql_case('r.pushed_at');

    $stmt = $pdo->query("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url,
               IF(r.of_version_curated = 1, r.of_version, {$inferredCase}) AS effective_version
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
        ORDER BY LOWER(r.name) ASC
    ");
    $addonsByVersion = [];
    foreach ($stmt->fetchAll() as $addon) {
        if (!empty($addon['effective_version'])) {
            $addonsByVersion[$addon['effective_version']][] = $addon;
        }
    }

    // OFX_VERSIONS is already newest-first; only show versions with addons
    $versions = array_values(array_filter(
        array_column(OFX_VERSIONS, 'version'),
        fn($v) => !empty($addonsByVersion[$v])
    ));

    ofx_render('versions/index', [
        'versions' => $versions,
        'addonsByVersion' => $addonsByVersion,
        'title' => 'Browse by openFrameworks version',
    ]);
}

// GET /versions/{version} - e.g. /versions/0.11
function ofx_versions_show(string $version): void
{
    $pdo = ofx_db();

    if (!in_array($version, array_column(OFX_VERSIONS, 'version'), true)) {
        ofx_not_found();
        return;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * OFX_PAGE_SIZE;
    $fetch = OFX_PAGE_SIZE + 1;
    $inferredCase = ofx_of_version_sql_case('r.pushed_at');

    // nosemgrep: php.lang.security.injection.tainted-callable.tainted-callable,php.lang.security.injection.tainted-sql-string.tainted-sql-string -- $version was already validated against the hardcoded OFX_VERSIONS list above; $fetch/$offset are (int)-cast/computed
    $stmt = $pdo->prepare("
        SELECT r.*, u.login AS user_login, u.avatar_url AS user_avatar_url
        FROM repos r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
          AND (
            (r.of_version_curated = 1 AND r.of_version = ?)
            OR (r.of_version_curated = 0 AND {$inferredCase} = ?)
          )
        ORDER BY LOWER(r.name) ASC, r.id ASC
        LIMIT {$fetch} OFFSET {$offset}
    ");
    $stmt->execute([$version, $version]);
    [$addons, $hasMore] = ofx_paginate_slice($stmt->fetchAll(), OFX_PAGE_SIZE);

    if (ofx_is_ajax()) {
        header('X-Has-More: ' . ($hasMore ? '1' : '0'));
        foreach ($addons as $addon) {
            ofx_addon_partial($addon);
        }
        return;
    }

    ofx_render('versions/show', [
        'version' => $version,
        'addons' => $addons,
        'hasMore' => $hasMore,
        'nextUrl' => ofx_next_page_url(2),
        'title' => "openFrameworks {$version}",
    ]);
}
