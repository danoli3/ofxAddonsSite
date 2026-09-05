<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/view.php';

function ofx_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function ofx_current_user(): ?array
{
    ofx_session_start();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $stmt = ofx_db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $found = $stmt->fetch();
    $user = $found ?: null;
    return $user;
}

// Every session-gated page sends this before anything else - a CDN
// (Cloudflare, etc.) placed in front of the site respects an explicit
// origin Cache-Control by default, so this is what keeps one admin's
// or owner's session-specific page from ever being cached and handed
// to someone else, even if the CDN's own settings get pointed at "cache
// everything" without anyone thinking through the admin panel/My
// Addons/API side of that.
function ofx_send_private_no_store(): void
{
    header('Cache-Control: private, no-store');
}

function ofx_require_admin(): array
{
    ofx_send_private_no_store();
    $user = ofx_current_user();
    if (!$user || !$user['admin']) {
        http_response_code(403);
        ofx_render('errors/403', ['title' => 'Forbidden']);
        exit;
    }
    return $user;
}

// A second tier above plain admin - gates the higher-blast-radius tools
// (bulk import, database backup, raw data export, the AI triage queue)
// separately from day-to-day categorizing, which any admin can still do.
function ofx_require_super_admin(): array
{
    ofx_send_private_no_store();
    $user = ofx_current_user();
    if (!$user || !$user['admin'] || !$user['super_admin']) {
        http_response_code(403);
        ofx_render('errors/403', ['title' => 'Forbidden']);
        exit;
    }
    return $user;
}

function ofx_require_user(): array
{
    ofx_send_private_no_store();
    $user = ofx_current_user();
    if (!$user) {
        http_response_code(403);
        ofx_render('errors/403', ['title' => 'Forbidden']);
        exit;
    }
    return $user;
}

function ofx_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

function ofx_oauth_state(): string
{
    ofx_session_start();
    if (empty($_SESSION['oauth_state'])) {
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['oauth_state'];
}

function ofx_github_authorize_url(): string
{
    $params = [
        'client_id' => ofx_env('GITHUB_CLIENT_ID'),
        'redirect_uri' => ofx_base_url() . '/auth/github/callback',
        'scope' => 'read:user',
        'state' => ofx_oauth_state(),
    ];
    return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
}

function ofx_github_exchange_code(string $code): ?array
{
    $ch = curl_init('https://github.com/login/oauth/access_token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => ofx_env('GITHUB_CLIENT_ID'),
            'client_secret' => ofx_env('GITHUB_CLIENT_SECRET'),
            'code' => $code,
            'redirect_uri' => ofx_base_url() . '/auth/github/callback',
        ]),
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $ok = curl_errno($ch) === 0;
    curl_close($ch);
    if (!$ok || !$body) {
        return null;
    }

    $data = json_decode($body, true);
    if (empty($data['access_token'])) {
        return null;
    }

    return ofx_github_fetch_user((string)$data['access_token']);
}

function ofx_github_fetch_user(string $token): ?array
{
    $ch = curl_init('https://api.github.com/user');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: token {$token}",
            'User-Agent: ofxaddons-site',
            'Accept: application/vnd.github.v3+json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $ok = curl_errno($ch) === 0;
    curl_close($ch);
    if (!$ok || !$body) {
        return null;
    }

    $data = json_decode($body, true);
    return !empty($data['login']) ? $data : null;
}

function ofx_login_or_create_user(array $githubUser): array
{
    $pdo = ofx_db();
    $uid = (string)$githubUser['id'];
    $login = $githubUser['login'];
    $name = $githubUser['name'] ?? null;
    $avatar = $githubUser['avatar_url'] ?? null;
    $location = $githubUser['location'] ?? null;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE provider = ? AND uid = ? LIMIT 1');
    $stmt->execute(['github', $uid]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE provider = ? AND login = ? LIMIT 1');
        $stmt->execute(['github', $login]);
        $user = $stmt->fetch();
    }

    if ($user) {
        $stmt = $pdo->prepare(
            'UPDATE users SET uid = ?, login = ?, name = ?, avatar_url = ?, location = ?,
             last_login_at = NOW(), updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$uid, $login, $name, $avatar, $location, $user['id']]);
        $user = array_merge($user, compact('uid', 'login', 'name', 'avatar', 'location'));
        return $user;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (provider, uid, login, name, avatar_url, location, admin, last_login_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW(), NOW())'
    );
    $stmt->execute(['github', $uid, $login, $name, $avatar, $location]);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$pdo->lastInsertId()]);
    return $stmt->fetch();
}
