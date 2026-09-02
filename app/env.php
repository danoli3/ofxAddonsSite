<?php
declare(strict_types=1);

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
