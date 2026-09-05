<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Apache's mod_deflate compresses static assets here (see the 30-day
// Cache-Control headers on CSS/JS) but doesn't chain onto PHP's own
// output on this host, so every dynamically-rendered page was going
// out uncompressed - /categories is ~200KB uncompressed vs ~17KB
// gzipped. This is portable across CGI/FPM/mod_php either way.
ini_set('zlib.output_compression', '1');

// Maintenance kill switch (see /admin/maintenance/toggle) - checked before
// ANY other file loads, so a request under this flag never opens a DB
// connection or starts a session. That's the point under a real attack:
// the box does the cheapest possible thing per request (stat one file,
// print a static page) instead of every hit paying full app/DB cost.
// /admin, /auth/github(/callback) and /logout stay reachable so an admin
// can still sign in and turn it back off.
if (is_file(__DIR__ . '/app/maintenance.flag')) {
    $__ofxPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $__ofxAllowed = $__ofxPath === '/auth/github'
        || $__ofxPath === '/auth/github/callback'
        || $__ofxPath === '/logout'
        || str_starts_with($__ofxPath, '/admin');
    if (!$__ofxAllowed) {
        http_response_code(503);
        header('Retry-After: 300');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=UTF-8');
        readfile(__DIR__ . '/app/views/maintenance.html');
        exit;
    }
}

require_once __DIR__ . '/app/env.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/view.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/sync.php';
require_once __DIR__ . '/app/ai.php';
require_once __DIR__ . '/app/image.php';
require_once __DIR__ . '/app/audit.php';
require_once __DIR__ . '/app/cache.php';
require_once __DIR__ . '/app/controllers/categories.php';
require_once __DIR__ . '/app/controllers/addons.php';
require_once __DIR__ . '/app/controllers/unsorted.php';
require_once __DIR__ . '/app/controllers/versions.php';
require_once __DIR__ . '/app/controllers/contributors.php';
require_once __DIR__ . '/app/controllers/pages.php';
require_once __DIR__ . '/app/controllers/sitemap.php';
require_once __DIR__ . '/app/controllers/session.php';
require_once __DIR__ . '/app/controllers/admin.php';
require_once __DIR__ . '/app/controllers/ai_triage_api.php';
require_once __DIR__ . '/app/controllers/webhooks.php';
require_once __DIR__ . '/app/controllers/my_addons.php';
require_once __DIR__ . '/app/routes.php';

ofx_send_security_headers();
ofx_dispatch();
