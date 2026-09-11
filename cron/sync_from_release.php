<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/env.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/sync.php';
require_once __DIR__ . '/../app/thumbnail_scan.php';
require_once __DIR__ . '/../app/cache.php';

$snapshot = ofx_fetch_latest_crawl_snapshot();
if (!$snapshot) {
    fwrite(STDERR, "Could not fetch latest release from danoli3/ofxAddons\n");
    exit(1);
}

$result = ofx_apply_crawl_snapshot(ofx_db(), $snapshot['addons']);
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
if ($thumbScan['generic_found'] > 0) {
    ofx_regenerate_public_caches();
}
fwrite(STDOUT, sprintf(
    "Thumbnail scan: %d checked, %d generic found, %d left to check\n",
    $thumbScan['checked'],
    $thumbScan['generic_found'],
    $thumbScan['remaining']
));
