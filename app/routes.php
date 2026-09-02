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
        ['GET', '#^/categories/(\d+)(?:-[^/]*)?$#', 'ofx_categories_show'],
        ['GET', '#^/addons$#', 'ofx_addons_index'],
        ['GET', '#^/freshest$#', 'ofx_addons_freshest'],
        ['GET', '#^/popular$#', 'ofx_addons_popular'],
        ['GET', '#^/unsorted$#', 'ofx_unsorted_index'],
        ['GET', '#^/contributors$#', 'ofx_contributors_index'],
        ['GET', '#^/contributors/([^/]+)$#', 'ofx_contributors_show'],
        ['GET', '#^/pages/howto$#', 'ofx_pages_howto'],
        ['POST', '#^/webhooks/sync$#', 'ofx_webhook_sync'],
        ['GET', '#^/banned\.json$#', 'ofx_banned_json'],
        ['GET', '#^/auth/github$#', 'ofx_session_new'],
        ['GET', '#^/auth/github/callback$#', 'ofx_session_create'],
        ['GET', '#^/logout$#', 'ofx_session_destroy'],
        ['GET', '#^/admin/repos$#', 'ofx_admin_index'],
        ['POST', '#^/admin/repos/(\d+)$#', 'ofx_admin_update'],
        ['POST', '#^/admin/repos/(\d+)/generate-description$#', 'ofx_admin_generate_description'],
        ['GET', '#^/admin/banned$#', 'ofx_admin_banned'],
        ['GET', '#^/admin/log$#', 'ofx_admin_log'],
        ['GET', '#^/admin/admins$#', 'ofx_admin_admins'],
        ['GET', '#^/admin/export\.(json|xml)$#', 'ofx_admin_export'],
        ['POST', '#^/admin/import$#', 'ofx_admin_import'],
        ['GET', '#^/my/addons$#', 'ofx_my_addons_index'],
        ['POST', '#^/my/addons/(\d+)$#', 'ofx_my_addons_update'],
        ['POST', '#^/my/addons/(\d+)/generate-description$#', 'ofx_my_addons_generate_description'],
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
