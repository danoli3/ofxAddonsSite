<?php
// Router for PHP's built-in server (`php -S localhost:8080 router.php`) -
// used for local dev and by tests/smoke.sh in CI. A real webserver
// (Apache/nginx) does this via .htaccess/config instead; this file only
// exists because the built-in server has no rewrite rules of its own.
declare(strict_types=1);

$path = urldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// realpath() both confirms the file actually exists AND resolves any
// ".."/symlink segments - the str_starts_with check after it is what
// actually matters: without it, a request path built with "../../"
// could resolve to a real file outside this directory entirely (e.g.
// something elsewhere on disk) and this router would hand it straight
// to the built-in server's static-file responder.
$resolved = realpath(__DIR__ . $path);
$withinDocroot = $resolved !== false && str_starts_with($resolved, __DIR__ . DIRECTORY_SEPARATOR);

if ($path !== '/' && $withinDocroot && !is_dir($resolved)) {
    return false;
}

require __DIR__ . '/index.php';
