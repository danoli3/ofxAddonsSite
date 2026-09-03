<?php
declare(strict_types=1);

// GET /sitemap.xml - every public page: static routes, categories,
// every non-hidden confirmed Addon (using pushed_at as <lastmod>),
// and every contributor with at least one listed addon.
function ofx_sitemap_xml(): void
{
    header('Content-Type: application/xml; charset=UTF-8');
    $pdo = ofx_db();
    $base = ofx_base_url();

    $staticPaths = ['/categories', '/addons', '/freshest', '/popular', '/unsorted', '/contributors', '/pages/howto'];
    $categories = $pdo->query('SELECT id FROM categories ORDER BY id')->fetchAll();
    $addons = $pdo->query("SELECT full_name, pushed_at FROM repos WHERE type = 'Addon' AND hidden_by_owner = 0")->fetchAll();
    $contributors = $pdo->query("
        SELECT u.login, MAX(r.pushed_at) AS last_pushed
        FROM users u
        JOIN repos r ON r.user_id = u.id
        WHERE r.type = 'Addon' AND r.hidden_by_owner = 0
        GROUP BY u.id
    ")->fetchAll();

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($staticPaths as $path) {
        echo '  <url><loc>' . ofx_h($base . $path) . '</loc></url>' . "\n";
    }
    foreach ($categories as $c) {
        echo '  <url><loc>' . ofx_h($base . '/categories/' . (int)$c['id']) . '</loc></url>' . "\n";
    }
    foreach ($addons as $a) {
        $loc = $base . ofx_addon_url($a['full_name']);
        $lastmod = $a['pushed_at'] ? gmdate('Y-m-d', strtotime($a['pushed_at'])) : null;
        echo '  <url><loc>' . ofx_h($loc) . '</loc>'
            . ($lastmod ? '<lastmod>' . $lastmod . '</lastmod>' : '')
            . '</url>' . "\n";
    }
    foreach ($contributors as $c) {
        if (!$c['login']) {
            continue;
        }
        echo '  <url><loc>' . ofx_h($base . '/contributors/' . rawurlencode($c['login'])) . '</loc></url>' . "\n";
    }

    echo '</urlset>';
}

// GET /llms.txt - the emerging llms.txt convention: a short,
// hand-curated overview of the site for AI agents/crawlers, pointing
// at the structured JSON feeds instead of expecting them to scrape
// HTML for everything.
function ofx_llms_txt(): void
{
    header('Content-Type: text/plain; charset=UTF-8');
    $pdo = ofx_db();
    $base = ofx_base_url();

    $categoryNames = $pdo->query('SELECT name FROM categories ORDER BY LOWER(name) ASC')->fetchAll(PDO::FETCH_COLUMN);
    $addonCount = (int)$pdo->query("SELECT COUNT(*) FROM repos WHERE type = 'Addon' AND hidden_by_owner = 0")->fetchColumn();

    echo "# ofxAddons\n\n";
    echo "> The central directory for openFrameworks addons - browse, search, and discover "
        . "community-built extensions for the openFrameworks creative coding toolkit. Currently listing "
        . "{$addonCount} addons.\n\n";
    echo "openFrameworks (https://openframeworks.cc) is a C++ toolkit for creative coding. An \"addon\" "
        . "extends it - either wrapping an external library or packaging up reusable OF code. This site "
        . "auto-discovers Github repos matching the \"ofx\" naming convention and lets the community "
        . "categorize them.\n\n";
    echo "## Key pages\n";
    echo "- All categories: {$base}/categories\n";
    echo "- All addons: {$base}/addons\n";
    echo "- Contributors: {$base}/contributors\n";
    echo "- How addons work / folder structure: {$base}/pages/howto\n";
    echo "- An individual addon (name, description, README, forks): {$base}/addons/{owner}/{repo}\n\n";
    echo "## Machine-readable data\n";
    echo "- Sitemap: {$base}/sitemap.xml\n";
    echo "- Confirmed addon repos, full_name only (JSON): {$base}/addon-repos.json\n";
    echo "- Excluded/banned repos, full_name only (JSON): {$base}/banned.json\n\n";
    echo "## Categories\n";
    foreach ($categoryNames as $name) {
        echo "- {$name}\n";
    }
}
