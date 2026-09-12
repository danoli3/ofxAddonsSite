<?php
// Router for PHP's built-in server (`php -S localhost:8080 router.php`) -
// used for local dev and by tests/smoke.sh in CI. A real webserver
// (Apache/nginx) does this via .htaccess/config instead; this file only
// exists because the built-in server has no rewrite rules of its own.
declare(strict_types=1);

$path = urldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $path;

// an existing real file/directory (assets, favicon, etc) - let the
// built-in server's default static-file handling serve it directly
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

require __DIR__ . '/index.php';
