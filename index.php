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

require_once __DIR__ . '/app/env.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/view.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/sync.php';
require_once __DIR__ . '/app/ai.php';
require_once __DIR__ . '/app/image.php';
require_once __DIR__ . '/app/audit.php';
require_once __DIR__ . '/app/controllers/categories.php';
require_once __DIR__ . '/app/controllers/addons.php';
require_once __DIR__ . '/app/controllers/unsorted.php';
require_once __DIR__ . '/app/controllers/contributors.php';
require_once __DIR__ . '/app/controllers/pages.php';
require_once __DIR__ . '/app/controllers/sitemap.php';
require_once __DIR__ . '/app/controllers/session.php';
require_once __DIR__ . '/app/controllers/admin.php';
require_once __DIR__ . '/app/controllers/webhooks.php';
require_once __DIR__ . '/app/controllers/my_addons.php';
require_once __DIR__ . '/app/routes.php';

ofx_dispatch();
