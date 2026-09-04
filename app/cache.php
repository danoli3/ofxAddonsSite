<?php
declare(strict_types=1);

// File-based cache for the handful of public JSON/XML feeds that used
// to recompute their response (identical until the next crawl sync)
// on every single request - the sitemap, and the two small feeds the
// crawler itself polls (addon-repos.json, banned.json). Regenerated
// once per real data change via ofx_regenerate_public_caches() - right
// after a crawl sync applies (webhook or the admin's manual "Pull
// latest release"), or on demand from the admin "Regenerate feeds"
// button - not on every page view.

const OFX_CACHE_DIR = __DIR__ . '/cache';

function ofx_cache_path(string $name): string
{
    return OFX_CACHE_DIR . '/' . $name;
}

// Serves the cached file if present; otherwise computes it live via
// $generate() and serves that directly without writing it to disk - a
// live-computed fallback (e.g. right after a fresh deploy, before the
// first sync/regenerate has populated the cache) is a one-off for that
// single request, not something that should risk racing a real
// regeneration or getting stuck as stale.
function ofx_cache_serve(string $name, callable $generate): void
{
    $path = ofx_cache_path($name);
    if (is_readable($path)) {
        readfile($path);
        return;
    }
    echo $generate();
}

function ofx_cache_write(string $name, string $contents): void
{
    if (!is_dir(OFX_CACHE_DIR)) {
        mkdir(OFX_CACHE_DIR, 0775, true);
    }
    file_put_contents(ofx_cache_path($name), $contents);
}

// Rebuilds every cached public feed from the database as it stands
// right now. Cheap enough (a handful of queries) to call synchronously
// wherever real data just changed, rather than needing a job queue.
function ofx_regenerate_public_caches(): void
{
    ofx_cache_write('sitemap.xml', ofx_sitemap_xml_content());
    ofx_cache_write('sitemap.json', ofx_sitemap_json_content());
    ofx_cache_write('banned.json', ofx_banned_json_content());
    ofx_cache_write('addon-repos.json', ofx_addon_repos_json_content());
}
