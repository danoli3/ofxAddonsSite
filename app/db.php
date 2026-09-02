<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

function ofx_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = ofx_env('DB_HOST', 'localhost');
    $name = ofx_env('DB_NAME', 'ofxaddons');
    $user = ofx_env('DB_USERNAME');
    $pass = ofx_env('DB_PASSWORD');

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
