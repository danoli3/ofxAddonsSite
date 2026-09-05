<?php
declare(strict_types=1);

// File-based cache for the handful of pages/feeds that used to
// recompute their (identical until the next crawl sync) response on
// every single request: the sitemap, the two small feeds the crawler
// itself polls (addon-repos.json, banned.json), the /categories
// homepage's addon+category dataset, and the full addon list behind
// /addons, /freshest, /popular (one per sort mode). Regenerated once
// per real data change via ofx_regenerate_public_caches() - right
// after a crawl sync applies (webhook or the admin's manual "Pull
// latest release"), or on demand from the admin "Regenerate feeds"
// button/Cache page - not on every page view. Alongside each cache
// file, OFX_CACHE_META_FILE tracks when and how long each one took to
// generate, shown on /admin/cache.

const OFX_CACHE_DIR = __DIR__ . '/cache';
const OFX_CACHE_META_FILE = '_meta.json';

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

// Reads back a cached array written by ofx_cache_generate_data() -
// returns null if the file doesn't exist yet or fails to decode, so
// callers can treat that the same as "no cache, compute live" without
// any special-casing.
function ofx_cache_read_data(string $name): ?array
{
    $path = ofx_cache_path($name);
    if (!is_readable($path)) {
        return null;
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function ofx_cache_write(string $name, string $contents): void
{
    if (!is_dir(OFX_CACHE_DIR)) {
        mkdir(OFX_CACHE_DIR, 0775, true);
    }
    file_put_contents(ofx_cache_path($name), $contents);
}

function ofx_cache_read_meta(): array
{
    $path = ofx_cache_path(OFX_CACHE_META_FILE);
    if (!is_readable($path)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function ofx_cache_record_meta(string $name, float $durationMs, int $bytes): void
{
    $meta = ofx_cache_read_meta();
    $meta[$name] = [
        'generated_at' => gmdate('c'),
        'duration_ms' => round($durationMs, 1),
        'bytes' => $bytes,
    ];
    ofx_cache_write(OFX_CACHE_META_FILE, json_encode($meta, JSON_PRETTY_PRINT));
}

// Times $generate(), writes its string result as the cache file, and
// records how long it took / how big it came out in the meta file.
function ofx_cache_generate(string $name, callable $generate): void
{
    $start = microtime(true);
    $contents = $generate();
    $durationMs = (microtime(true) - $start) * 1000;
    ofx_cache_write($name, $contents);
    ofx_cache_record_meta($name, $durationMs, strlen($contents));
}

// Same as ofx_cache_generate(), but for a PHP array (e.g. a raw DB
// result set) instead of a pre-formatted string - json_encode is the
// on-disk format, read back with ofx_cache_read_data().
function ofx_cache_generate_data(string $name, callable $generate): void
{
    ofx_cache_generate($name, function () use ($generate): string {
        return json_encode($generate());
    });
}

// Rebuilds every cached page/feed from the database as it stands right
// now. Not free (the /addons sort variants each fetch every Addon row),
// but still cheap enough to call synchronously wherever real data just
// changed, rather than needing a job queue.
function ofx_regenerate_public_caches(): void
{
    ofx_cache_generate('sitemap.xml', 'ofx_sitemap_xml_content');
    ofx_cache_generate('sitemap.json', 'ofx_sitemap_json_content');
    ofx_cache_generate('banned.json', 'ofx_banned_json_content');
    ofx_cache_generate('addon-repos.json', 'ofx_addon_repos_json_content');
    ofx_cache_generate_data('categories-addons.json', 'ofx_categories_addons_content');
    ofx_cache_generate_data('addons-name.json', fn () => ofx_addons_sorted_content('name'));
    ofx_cache_generate_data('addons-freshest.json', fn () => ofx_addons_sorted_content('freshest'));
    ofx_cache_generate_data('addons-popular.json', fn () => ofx_addons_sorted_content('popular'));
}
