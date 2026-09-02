<?php
declare(strict_types=1);

function ofx_webhook_sync(): void
{
    header('Content-Type: application/json');

    $secret = ofx_env('SYNC_SECRET');
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $provided = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';

    if (!$secret || !hash_equals($secret, $provided)) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'error' => 'forbidden']);
        return;
    }

    $snapshot = ofx_fetch_latest_crawl_snapshot();
    if (!$snapshot) {
        http_response_code(502);
        echo json_encode(['status' => 502, 'error' => 'could not fetch latest release']);
        return;
    }

    $result = ofx_apply_crawl_snapshot(ofx_db(), $snapshot['addons']);
    echo json_encode(['status' => 200] + $result);
}

function ofx_banned_json(): void
{
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    echo json_encode(ofx_banned_full_names(ofx_db()));
}
