<?php
declare(strict_types=1);

// The crawl snapshot's has_thumbnail only records that a file named
// ofxaddons_thumbnail.png exists in a repo - it has no idea whether that
// file is an actual thumbnail for the addon, or just an untouched copy of
// ofxAddonTemplate's own placeholder image (270x70, "ofxAddonTemplate"
// wordmark) left over from forking it. A lot of addons never replace it,
// which is why the generic template image kept showing up as a real
// thumbnail in cards/tiles.
//
// Detecting an exact, unmodified copy only needs the file's git blob
// SHA - a single lightweight Contents API call - not downloading and
// hashing the actual image bytes. OFX_GENERIC_THUMBNAIL_BLOB_SHA is the
// blob SHA of openframeworks/ofxAddonTemplate's own
// ofxaddons_thumbnail.png; recompute it (sha1("blob " . strlen($bytes)
// . "\0" . $bytes)) if that file's own content is ever intentionally
// updated upstream.
const OFX_GENERIC_THUMBNAIL_BLOB_SHA = '0090c27c1152fc112af3ef8c9983fe68857159e6';
const OFX_THUMBNAIL_SCAN_BATCH = 30;

// Returns the blob sha on success, null if Github confirms the file
// genuinely doesn't exist (404 - e.g. deleted since the crawler last saw
// it), or false for anything else: rate-limited, timed out, a 5xx, a
// network blip. The caller must not treat false the same as null - doing
// so would permanently record a transient failure as "checked, not
// generic" and it would never be retried.
function ofx_thumbnail_blob_sha(string $fullName): string|false|null
{
    [$owner, $repo] = array_pad(explode('/', $fullName, 2), 2, '');
    $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo)
        . '/contents/ofxaddons_thumbnail.png';

    $token = ofx_env('GITHUB_TOKEN');
    $headers = ['Accept: application/vnd.github.v3+json', 'User-Agent: ofxaddons-site'];
    if ($token) {
        $headers[] = "Authorization: token {$token}";
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 404) {
        return null;
    }
    if ($status !== 200 || !$body) {
        return false;
    }
    $data = json_decode($body, true);
    return is_array($data) && isset($data['sha']) ? $data['sha'] : false;
}

// Processes up to $limit not-yet-checked repos (has_thumbnail=1,
// thumbnail_checked_at still NULL) - bounded per call, same lesson as
// ofx_admin_duplicates() before it was capped: unbounded live Github
// calls in one request/run is how you hang a page or a cron job. Safe to
// call repeatedly (from the admin toolbar, or once a day from
// cron/sync_from_release.php) to work through the backlog over time and
// keep catching newly-crawled repos.
function ofx_scan_generic_thumbnails(PDO $pdo, int $limit = OFX_THUMBNAIL_SCAN_BATCH): array
{
    // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string -- $limit is declared int (strict_types=1 rejects a non-int argument outright), not raw request input
    $stmt = $pdo->prepare("
        SELECT id, full_name FROM repos
        WHERE has_thumbnail = 1 AND thumbnail_checked_at IS NULL
        ORDER BY id ASC
        LIMIT {$limit}
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $checked = 0;
    $genericFound = 0;
    $update = $pdo->prepare('UPDATE repos SET thumbnail_is_generic = ?, thumbnail_checked_at = NOW() WHERE id = ?');

    foreach ($rows as $row) {
        $sha = ofx_thumbnail_blob_sha($row['full_name']);
        if ($sha === false) {
            // transient failure (rate-limited, timed out, Github hiccup) -
            // leave thumbnail_checked_at NULL so this repo is retried on
            // the next batch instead of being silently skipped forever
            continue;
        }
        $isGeneric = $sha !== null && hash_equals(OFX_GENERIC_THUMBNAIL_BLOB_SHA, $sha);
        $update->execute([$isGeneric ? 1 : 0, $row['id']]);
        $checked++;
        if ($isGeneric) {
            $genericFound++;
        }
    }

    $remaining = (int)$pdo->query(
        'SELECT COUNT(*) FROM repos WHERE has_thumbnail = 1 AND thumbnail_checked_at IS NULL'
    )->fetchColumn();

    return ['checked' => $checked, 'generic_found' => $genericFound, 'remaining' => $remaining];
}
