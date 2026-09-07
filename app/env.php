<?php
declare(strict_types=1);

// Every stored datetime in this app is written as a naive UTC string
// (gmdate() - see ofx_sync_to_datetime()), but PHP's date/time functions
// interpret a naive string using the script's default timezone, not UTC,
// unless told otherwise. This host's php.ini default is America/Los_Angeles,
// not UTC, so anything reading those strings back with strtotime()/date()
// - ofx_time_ago() foremost - was silently off by the server's UTC offset
// (reported: an addon showing "Updated 5h ago" when Github said 12h).
// Setting this once, here (required by both the web entry point and the
// cron sync job - the only two places PHP ever runs in this app), fixes
// every such read site at once instead of patching each call individually.
date_default_timezone_set('UTC');

function ofx_load_env(): array
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }

    $env = [];
    $path = dirname(__DIR__) . '/.env';
    if (is_readable($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }

    foreach (array_keys($env) as $key) {
        $override = getenv($key);
        if ($override !== false) {
            $env[$key] = $override;
        }
    }

    return $env;
}

function ofx_env(string $key, ?string $default = null): ?string
{
    return ofx_load_env()[$key] ?? $default;
}
