<?php
declare(strict_types=1);

function ofx_dispatch(): void
{
    $path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = rtrim($path, '/');
    if ($path === '') {
        $path = '/';
    }
    $method = $_SERVER['REQUEST_METHOD'];

    $routes = [
        ['GET', '#^/$#', fn () => ofx_redirect('/categories')],
        ['GET', '#^/categories$#', 'ofx_categories_index'],
        ['GET', '#^/categories/([a-zA-Z0-9-]+)$#', 'ofx_categories_show'],
        ['GET', '#^/addons$#', 'ofx_addons_index'],
        ['GET', '#^/search$#', 'ofx_addons_search'],
        ['GET', '#^/addons/([^/]+)/([^/]+)$#', 'ofx_addons_show'],
        ['GET', '#^/freshest$#', 'ofx_addons_freshest'],
        ['GET', '#^/popular$#', 'ofx_addons_popular'],
        ['GET', '#^/unsorted$#', 'ofx_unsorted_index'],
        ['GET', '#^/versions$#', 'ofx_versions_index'],
        ['GET', '#^/versions/([0-9.]+)$#', 'ofx_versions_show'],
        ['GET', '#^/contributors$#', 'ofx_contributors_index'],
        ['GET', '#^/contributors/([^/]+)$#', 'ofx_contributors_show'],
        ['GET', '#^/pages/howto$#', 'ofx_pages_howto'],
        ['GET', '#^/about-openframeworks$#', 'ofx_pages_about_openframeworks'],
        ['GET', '#^/history$#', 'ofx_pages_history'],
        ['GET', '#^/sitemap$#', 'ofx_pages_sitemap'],
        ['GET', '#^/sitemap\.xml$#', 'ofx_sitemap_xml'],
        ['GET', '#^/sitemap\.json$#', 'ofx_sitemap_json'],
        ['GET', '#^/llms\.txt$#', 'ofx_llms_txt'],
        ['GET', '#^/llms\.md$#', 'ofx_llms_md'],
        ['POST', '#^/webhooks/sync$#', 'ofx_webhook_sync'],
        ['GET', '#^/banned\.json$#', 'ofx_banned_json'],
        ['GET', '#^/addon-repos\.json$#', 'ofx_addon_repos_json'],
        ['GET', '#^/auth/github$#', 'ofx_session_new'],
        ['GET', '#^/auth/github/callback$#', 'ofx_session_create'],
        ['GET', '#^/logout$#', 'ofx_session_destroy'],
        ['GET', '#^/admin/repos$#', 'ofx_admin_index'],
        ['POST', '#^/admin/repos/(\d+)$#', 'ofx_admin_update'],
        ['POST', '#^/admin/repos/(\d+)/generate-description$#', 'ofx_admin_generate_description'],
        ['POST', '#^/admin/repos/(\d+)/generate-thumbnail$#', 'ofx_admin_generate_thumbnail'],
        ['GET', '#^/admin/banned$#', 'ofx_admin_banned'],
        ['GET', '#^/admin/review$#', 'ofx_admin_review_queue'],
        ['GET', '#^/admin/duplicates$#', 'ofx_admin_duplicates'],
        ['POST', '#^/admin/repos/(\d+)/confirm-fork$#', 'ofx_admin_confirm_fork'],
        ['POST', '#^/admin/repos/(\d+)/unconfirm-fork$#', 'ofx_admin_unconfirm_fork'],
        ['POST', '#^/admin/repos/(\d+)/confirm-unique$#', 'ofx_admin_confirm_unique'],
        ['POST', '#^/admin/repos/(\d+)/unconfirm-unique$#', 'ofx_admin_unconfirm_unique'],
        ['GET', '#^/admin/log$#', 'ofx_admin_log'],
        ['GET', '#^/admin/admins$#', 'ofx_admin_admins'],
        ['POST', '#^/admin/admins/(\d+)/toggle$#', 'ofx_admin_toggle_admin'],
        ['POST', '#^/admin/sync-now$#', 'ofx_admin_sync_now'],
        ['POST', '#^/admin/regenerate-caches$#', 'ofx_admin_regenerate_caches'],
        ['POST', '#^/admin/add-repo$#', 'ofx_admin_add_repo'],
        ['POST', '#^/admin/categorizations/(\d+)/(\d+)/toggle-featured$#', 'ofx_admin_toggle_featured'],
        ['POST', '#^/admin/repos/(\d+)/dismiss-appeal$#', 'ofx_admin_dismiss_appeal'],
        ['GET', '#^/admin/export\.(json|xml)$#', 'ofx_admin_export'],
        ['GET', '#^/admin/export-triage\.json$#', 'ofx_admin_export_triage'],
        ['GET', '#^/admin/export-banned\.json$#', 'ofx_admin_export_banned'],
        ['GET', '#^/admin/backup\.sql\.gz$#', 'ofx_admin_backup_sql'],
        ['POST', '#^/admin/import/preview$#', 'ofx_admin_import_preview'],
        ['POST', '#^/admin/import/confirm$#', 'ofx_admin_import_confirm'],
        ['GET', '#^/admin/ai-triage/review$#', 'ofx_admin_ai_queue_review'],
        ['POST', '#^/admin/ai-triage/confirm$#', 'ofx_admin_ai_queue_confirm'],
        ['GET', '#^/api/triage/batch$#', 'ofx_api_triage_batch'],
        ['POST', '#^/api/triage/submit$#', 'ofx_api_triage_submit'],
        ['GET', '#^/my/addons$#', 'ofx_my_addons_index'],
        ['POST', '#^/my/addons/(\d+)$#', 'ofx_my_addons_update'],
        ['POST', '#^/my/addons/(\d+)/generate-description$#', 'ofx_my_addons_generate_description'],
        ['POST', '#^/my/addons/(\d+)/appeal-ban$#', 'ofx_my_addons_appeal_ban'],
    ];

    foreach ($routes as [$m, $pattern, $handler]) {
        if ($method !== $m || !preg_match($pattern, $path, $matches)) {
            continue;
        }
        array_shift($matches);
        call_user_func_array($handler, $matches);
        return;
    }

    ofx_not_found();
}
