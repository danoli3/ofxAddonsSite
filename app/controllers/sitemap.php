<?php
declare(strict_types=1);

// The date this PHP rewrite actually went live - the baseline lastmod
// for a page whose content has no natural "when did this last change"
// data source of its own (a static guide page, or a listing that
// happens to be empty). Never used for anything with real underlying
// data; those compute a real MAX(pushed_at) instead, below.
const OFX_SITE_LAUNCHED_AT = '2026-09-02';

// Shared by ofx_sitemap_xml() and ofx_sitemap_json() so the two formats
// can never drift apart - every entry is ['loc' => full URL, 'lastmod'
// => 'Y-m-d']. A listing page's lastmod is the most recent pushed_at
// among the addons it actually lists, so it moves only when that page's
// real content would've changed - not stamped to "now" on every crawl.
function ofx_sitemap_urls(): array
{
    $pdo = ofx_db();
    $base = ofx_base_url();
    $urls = [];

    $addons = $pdo->query("
        SELECT full_name, pushed_at FROM repos
        WHERE type = 'Addon' AND hidden_by_owner = 0 AND fork_hidden_by_admin = 0
    ")->fetchAll();

    $maxAddonPushed = OFX_SITE_LAUNCHED_AT;
    foreach ($addons as $a) {
        if ($a['pushed_at'] && $a['pushed_at'] > $maxAddonPushed) {
            $maxAddonPushed = $a['pushed_at'];
        }
    }
    $maxAddonPushed = gmdate('Y-m-d', strtotime($maxAddonPushed));

    $maxUnsortedUpdated = $pdo->query(
        "SELECT MAX(updated_at) FROM repos WHERE type = 'Unsorted'"
    )->fetchColumn();
    $maxUnsortedUpdated = $maxUnsortedUpdated
        ? gmdate('Y-m-d', strtotime($maxUnsortedUpdated))
        : gmdate('Y-m-d', strtotime(OFX_SITE_LAUNCHED_AT));

    // /categories, /addons, /freshest, /popular, /contributors are all
    // just different views over the same confirmed-Addon set, so they
    // share one lastmod; /unsorted has its own (crawler-touch time, not
    // a commit date - most Unsorted rows don't even have a pushed_at);
    // /pages/howto and /sitemap are hand-written, static content.
    $staticPaths = [
        '/categories' => $maxAddonPushed,
        '/addons' => $maxAddonPushed,
        '/freshest' => $maxAddonPushed,
        '/popular' => $maxAddonPushed,
        '/unsorted' => $maxUnsortedUpdated,
        '/contributors' => $maxAddonPushed,
        '/pages/howto' => gmdate('Y-m-d', strtotime(OFX_SITE_LAUNCHED_AT)),
        '/about-openframeworks' => gmdate('Y-m-d', strtotime(OFX_SITE_LAUNCHED_AT)),
        '/history' => gmdate('Y-m-d', strtotime(OFX_SITE_LAUNCHED_AT)),
        '/sitemap' => gmdate('Y-m-d', strtotime(OFX_SITE_LAUNCHED_AT)),
    ];
    foreach ($staticPaths as $path => $lastmod) {
        $urls[] = ['loc' => $base . $path, 'lastmod' => $lastmod];
    }

    $categories = $pdo->query('
        SELECT c.id, c.name, MAX(r.pushed_at) AS last_pushed
        FROM categories c
        LEFT JOIN categorizations cz ON cz.category_id = c.id
        LEFT JOIN repos r ON r.id = cz.repo_id AND r.type = "Addon"
            AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
        GROUP BY c.id
        ORDER BY c.id
    ')->fetchAll();
    foreach ($categories as $c) {
        $urls[] = [
            'loc' => $base . ofx_category_url($c),
            'lastmod' => $c['last_pushed'] ? gmdate('Y-m-d', strtotime($c['last_pushed'])) : gmdate('Y-m-d', strtotime(OFX_SITE_LAUNCHED_AT)),
        ];
    }

    foreach ($addons as $a) {
        $urls[] = [
            'loc' => $base . ofx_addon_url($a['full_name']),
            'lastmod' => $a['pushed_at'] ? gmdate('Y-m-d', strtotime($a['pushed_at'])) : gmdate('Y-m-d', strtotime(OFX_SITE_LAUNCHED_AT)),
        ];
    }

    $contributors = $pdo->query("
        SELECT u.login, MAX(r.pushed_at) AS last_pushed
        FROM users u
        JOIN repos r ON r.user_id = u.id
        WHERE r.type = 'Addon' AND r.hidden_by_owner = 0 AND r.fork_hidden_by_admin = 0
        GROUP BY u.id
    ")->fetchAll();
    foreach ($contributors as $c) {
        if (!$c['login']) {
            continue;
        }
        $urls[] = [
            'loc' => $base . '/contributors/' . rawurlencode($c['login']),
            'lastmod' => $c['last_pushed'] ? gmdate('Y-m-d', strtotime($c['last_pushed'])) : gmdate('Y-m-d', strtotime(OFX_SITE_LAUNCHED_AT)),
        ];
    }

    return $urls;
}

// GET /sitemap.xml - every public page: static routes, categories,
// every non-hidden confirmed Addon (using pushed_at as <lastmod>),
// and every contributor with at least one listed addon.
function ofx_sitemap_xml_content(): string
{
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (ofx_sitemap_urls() as $u) {
        $out .= '  <url><loc>' . ofx_h($u['loc']) . '</loc>'
            . ($u['lastmod'] ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '')
            . '</url>' . "\n";
    }
    $out .= '</urlset>';
    return $out;
}

function ofx_sitemap_xml(): void
{
    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: public, max-age=900');
    ofx_cache_serve('sitemap.xml', 'ofx_sitemap_xml_content');
}

function ofx_sitemap_json_content(): string
{
    return json_encode([
        'generated_at' => gmdate('c'),
        'urls' => ofx_sitemap_urls(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// GET /sitemap.json - the same URL list as ofx_sitemap_xml(), as JSON.
function ofx_sitemap_json(): void
{
    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=900');
    ofx_cache_serve('sitemap.json', 'ofx_sitemap_json_content');
}

// Shared by ofx_llms_txt() and ofx_llms_md() - same markdown-formatted
// content either way, just a different route/Content-Type/file
// extension for whichever a given crawler or tool expects to find.
function ofx_llms_content(): string
{
    $pdo = ofx_db();
    $base = ofx_base_url();

    $categoryNames = $pdo->query('SELECT name FROM categories ORDER BY LOWER(name) ASC')->fetchAll(PDO::FETCH_COLUMN);
    $addonCount = (int)$pdo->query("
        SELECT COUNT(*) FROM repos WHERE type = 'Addon' AND hidden_by_owner = 0 AND fork_hidden_by_admin = 0
    ")->fetchColumn();

    $out = "# ofxAddons\n\n";
    $out .= "> The central directory for openFrameworks addons - browse, search, and discover "
        . "community-built extensions for the openFrameworks creative coding toolkit. Currently listing "
        . "{$addonCount} addons.\n\n";
    $out .= "openFrameworks (https://openframeworks.cc) is a C++ toolkit for creative coding. An \"addon\" "
        . "extends it - either wrapping an external library or packaging up reusable OF code. This site "
        . "auto-discovers Github repos matching the \"ofx\" naming convention and lets the community "
        . "categorize them.\n\n";
    $out .= "## Key pages\n";
    $out .= "- All categories: {$base}/categories\n";
    $out .= "- All addons: {$base}/addons\n";
    $out .= "- Contributors: {$base}/contributors\n";
    $out .= "- How addons work / folder structure: {$base}/pages/howto\n";
    $out .= "- About openFrameworks itself: {$base}/about-openframeworks\n";
    $out .= "- History and credits: {$base}/history\n";
    $out .= "- Human-readable sitemap: {$base}/sitemap\n";
    $out .= "- An individual addon (name, description, README, forks): {$base}/addons/{owner}/{repo}\n\n";
    $out .= "## Machine-readable data\n";
    $out .= "- Sitemap (XML): {$base}/sitemap.xml\n";
    $out .= "- Sitemap (JSON): {$base}/sitemap.json\n";
    $out .= "- Confirmed addon repos, full_name only (JSON): {$base}/addon-repos.json\n";
    $out .= "- Excluded/banned repos, full_name only (JSON): {$base}/banned.json\n\n";
    $out .= "## Categories\n";
    foreach ($categoryNames as $name) {
        $out .= "- {$name}\n";
    }

    return $out;
}

// GET /llms.txt - the emerging llms.txt convention: a short,
// hand-curated overview of the site for AI agents/crawlers, pointing
// at the structured JSON feeds instead of expecting them to scrape
// HTML for everything.
function ofx_llms_txt(): void
{
    header('Content-Type: text/plain; charset=UTF-8');
    echo ofx_llms_content();
}

// GET /llms.md - same content as /llms.txt, for any tool/crawler that
// specifically expects a .md extension rather than the llms.txt name.
function ofx_llms_md(): void
{
    header('Content-Type: text/markdown; charset=UTF-8');
    echo ofx_llms_content();
}
