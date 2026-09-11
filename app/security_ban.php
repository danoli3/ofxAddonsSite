<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

// This app's source is public on Github, so every route in routes.php -
// including /admin/* and /api/triage/* - is something an attacker can
// read ahead of time and go straight for, rather than needing to discover
// it by probing. This is the response to that: an IP that repeatedly
// fails to authenticate against those paths, or that shows an unambiguous
// scanner/exploit-probe signature on ANY request, gets served a 503
// (identical to the manual maintenance kill switch) for OFX_BAN_HOURS -
// scoped to that IP only, not the whole site.
const OFX_BAN_HOURS = 4;
const OFX_BAN_FAILURE_WINDOW_MINUTES = 15;
const OFX_BAN_TRIAGE_AUTH_THRESHOLD = 5;
const OFX_BAN_ADMIN_PROBE_THRESHOLD = 8;

// Named scanner/exploit tools with no legitimate reason to ever hit this
// site - unlike a single wrong API key or a mistyped /admin link, there's
// no ambiguity here, so these ban on the first hit rather than counting
// toward a threshold.
const OFX_BAN_USER_AGENT_SIGNATURES = [
    'sqlmap', 'nikto', 'nmap', 'masscan', 'nessus', 'acunetix', 'w3af',
    'havij', 'wpscan', 'dirbuster', 'gobuster', 'zgrab', 'nuclei',
    'metasploit', 'openvas', 'arachni', 'netsparker',
];

// Paths that only make sense against a WordPress/Laravel/phpMyAdmin
// install, a leaked .git/.env, or a handful of other stacks this codebase
// simply isn't (see README - plain PHP, no framework) - nothing
// legitimate ever requests these here, so a hit is just as unambiguous
// as the user-agent signatures above.
const OFX_BAN_PATH_SIGNATURES = [
    '/wp-admin', '/wp-login.php', '/wp-content', '/wp-includes', '/wordpress',
    '/.env', '/.git/config', '/.git/HEAD',
    '/phpmyadmin', '/pma', '/adminer.php',
    '/xmlrpc.php', '/.aws/credentials',
    '/vendor/phpunit', '/actuator', '/telescope',
    '/config.php.bak', '/backup.sql', '/db.sql',
    '/cgi-bin/',
];

function ofx_client_ip(): string
{
    // REMOTE_ADDR only - a proxy-supplied header like X-Forwarded-For is
    // trivially spoofable by the very requester it's meant to identify,
    // which would let an attacker frame an innocent IP or dodge their own
    // ban outright. Revisit only if a specific trusted reverse proxy is
    // actually placed in front of this host.
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function ofx_security_known_attack_reason(string $path, string $userAgent): ?string
{
    $uaLower = strtolower($userAgent);
    foreach (OFX_BAN_USER_AGENT_SIGNATURES as $sig) {
        if ($uaLower !== '' && str_contains($uaLower, $sig)) {
            return "scanner user-agent match: \"{$sig}\"";
        }
    }
    foreach (OFX_BAN_PATH_SIGNATURES as $sig) {
        if (str_starts_with($path, $sig)) {
            return "probe path match: \"{$sig}\"";
        }
    }
    return null;
}

function ofx_security_active_ban(PDO $pdo, string $ip): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM security_bans WHERE ip = ? AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1');
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string -- OFX_BAN_HOURS is a hardcoded int constant, not request input; $ip/$reason are bound via execute()
function ofx_security_ban(PDO $pdo, string $ip, string $reason): void
{
    $hours = OFX_BAN_HOURS;
    $stmt = $pdo->prepare("
        INSERT INTO security_bans (ip, reason, banned_at, expires_at)
        VALUES (?, ?, NOW(), NOW() + INTERVAL {$hours} HOUR)
    ");
    $stmt->execute([$ip, mb_substr($reason, 0, 255)]);
    ofx_log_admin_action($pdo, null, 'ip_banned', null, "{$ip}: {$reason}");
}

// Logs one failure of $kind for $ip, then bans it once $threshold such
// failures have landed within OFX_BAN_FAILURE_WINDOW_MINUTES - the
// counted-threshold half of this system, for the two triggers ambiguous
// enough that a single occurrence shouldn't be an instant ban (a wrong
// API key, a mistyped /admin link), as opposed to the signature-based
// instant bans in ofx_security_enforce() below.
// nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string -- OFX_BAN_FAILURE_WINDOW_MINUTES is a hardcoded int constant, not request input; $ip/$kind are bound via execute()
function ofx_security_record_failure(PDO $pdo, string $ip, string $kind, int $threshold): void
{
    $pdo->prepare('INSERT INTO security_failures (ip, kind, occurred_at) VALUES (?, ?, NOW())')
        ->execute([$ip, $kind]);

    $windowMinutes = OFX_BAN_FAILURE_WINDOW_MINUTES;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM security_failures
        WHERE ip = ? AND kind = ? AND occurred_at > (NOW() - INTERVAL {$windowMinutes} MINUTE)
    ");
    $stmt->execute([$ip, $kind]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $threshold) {
        ofx_security_ban($pdo, $ip, "{$count} failed \"{$kind}\" attempts within {$windowMinutes} minutes");
    }
}

function ofx_security_serve_ban_page(array $ban): void
{
    http_response_code(503);
    $retryAfter = max(60, strtotime($ban['expires_at']) - time());
    header("Retry-After: {$retryAfter}");
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/views/maintenance.html');
    exit;
}

// Called once per request, right after the DB connection is available
// (see index.php) - blocks an already-banned IP outright, and bans on
// sight for an unambiguous scanner/exploit-probe signature. The two
// failure-counted triggers (wrong triage API key, /admin probing by a
// non-admin) are recorded at their own call sites - ofx_api_triage_require_key()
// and ofx_require_admin()/ofx_require_super_admin() - not here.
function ofx_security_enforce(PDO $pdo): void
{
    $ip = ofx_client_ip();

    $activeBan = ofx_security_active_ban($pdo, $ip);
    if ($activeBan) {
        ofx_security_serve_ban_page($activeBan);
        return;
    }

    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $reason = ofx_security_known_attack_reason($path, $userAgent);
    if ($reason !== null) {
        ofx_security_ban($pdo, $ip, $reason);
        $freshBan = ofx_security_active_ban($pdo, $ip);
        if ($freshBan) {
            ofx_security_serve_ban_page($freshBan);
        }
    }
}
