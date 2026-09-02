<?php
declare(strict_types=1);

function ofx_csrf_token(): string
{
    ofx_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function ofx_csrf_verify(): bool
{
    ofx_session_start();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', (string)$provided);
}

function ofx_require_csrf(): void
{
    if (!ofx_csrf_verify()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 403, 'error' => ['invalid or missing CSRF token']]);
        exit;
    }
}
