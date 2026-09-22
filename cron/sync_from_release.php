<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/env.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/sync.php';
require_once __DIR__ . '/../app/thumbnail_scan.php';
require_once __DIR__ . '/../app/cache.php';

$snapshot = ofx_fetch_latest_crawl_snapshot();
if (!$snapshot) {
    ofx_log_sync_run(ofx_db(), 'cron', null, null, null, 'Could not fetch latest release from danoli3/ofxAddons');
    fwrite(STDERR, "Could not fetch latest release from danoli3/ofxAddons\n");
    exit(1);
}

$result = ofx_apply_crawl_snapshot(ofx_db(), $snapshot['addons']);
ofx_log_sync_run(ofx_db(), 'cron', $snapshot, $result);
fwrite(STDOUT, sprintf(
    "Synced from release generated_at=%s: %d added, %d updated, %d skipped (banned)\n",
    $snapshot['generated_at'] ?? 'unknown',
    $result['added'],
    $result['updated'],
    $result['skipped_banned']
));

// One batch a day (see app/thumbnail_scan.php) is enough to both catch
// newly-crawled repos and, over time, work through the pre-existing
// backlog - bounded so this can't turn an unattended daily cron run into
// the same kind of hang ofx_admin_duplicates() had before it was capped.
$thumbScan = ofx_scan_generic_thumbnails(ofx_db());
fwrite(STDOUT, sprintf(
    "Thumbnail scan: %d checked, %d generic found, %d left to check\n",
    $thumbScan['checked'],
    $thumbScan['generic_found'],
    $thumbScan['remaining']
));

// Unconditional - matches ofx_webhook_sync() exactly. This used to only
// regenerate when the thumbnail scan above happened to flag something,
// which meant sitemap.xml/json, categories-addons.json, addons-*.json
// etc. only picked up a day's real pushed_at/type/category changes on
// days that ALSO happened to find a generic thumbnail - everything else
// (lastmod included) kept serving whatever the last regeneration had,
// however stale. This is the only path that runs daily regardless of
// whether the crawler's own webhook call ever reaches this site, so it
// can't be conditional on something unrelated to whether a real sync
// actually happened.
ofx_regenerate_public_caches();
