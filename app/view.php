<?php
declare(strict_types=1);

const OFX_PAGE_SIZE = 24;
const OFX_DESCRIPTION_MAX_LENGTH = 350;

// Real openFrameworks major-version release dates (first commit/tag of
// each x.y.0 series, oldest catch-all bucket at the end), used to guess
// which version an addon targets from its last-pushed date when no
// version has been curated from its README. Newest first, since
// ofx_infer_of_version() takes the first bucket a date qualifies for.
// Update this list when a new openFrameworks version ships.
const OFX_VERSIONS = [
    ['version' => '0.12', 'released' => '2023-08-30'],
    ['version' => '0.11', 'released' => '2019-11-30'],
    ['version' => '0.10', 'released' => '2018-05-07'],
    ['version' => '0.9', 'released' => '2015-11-08'],
    ['version' => '0.8', 'released' => '2013-08-11'],
    ['version' => '0.7', 'released' => '2010-01-01'],
];

// String comparison works here because both sides are Y-m-d-prefixed
// (repos.pushed_at is a full "Y-m-d H:i:s" DATETIME, $released is a
// plain date) - lexicographic order matches chronological order.
function ofx_infer_of_version(?string $pushedAt): ?string
{
    if (!$pushedAt) {
        return null;
    }
    foreach (OFX_VERSIONS as $v) {
        if ($pushedAt >= $v['released']) {
            return $v['version'];
        }
    }
    return null;
}

// The version actually shown for an addon: a curated value (set by an
// admin, e.g. from README text a Qwen pass extracted) takes priority
// over the inferred guess from pushed_at. Returns null with nothing to
// show at all (no curated value and no pushed_at to infer from).
function ofx_addon_of_version(array $addon): ?array
{
    if (!empty($addon['of_version_curated']) && !empty($addon['of_version'])) {
        return ['version' => $addon['of_version'], 'curated' => true];
    }
    $inferred = ofx_infer_of_version($addon['pushed_at'] ?? null);
    return $inferred ? ['version' => $inferred, 'curated' => false] : null;
}

// SQL CASE expression computing the same inference as
// ofx_infer_of_version(), for use in a query's SELECT/HAVING - the
// version-browse pages need this to filter/group on a value most rows
// don't have curated, without loading every repo into PHP first.
// OFX_VERSIONS is a fixed constant, not user input, so interpolating
// it directly into SQL here is safe.
function ofx_of_version_sql_case(string $column): string
{
    $cases = [];
    foreach (OFX_VERSIONS as $v) {
        $cases[] = "WHEN {$column} >= '{$v['released']}' THEN '{$v['version']}'";
    }
    return 'CASE ' . implode(' ', $cases) . ' ELSE NULL END';
}

function ofx_version_url(string $version): string
{
    return '/versions/' . rawurlencode($version);
}

function ofx_render(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/views/' . $template . '.php';
    $content = ob_get_clean();
    include __DIR__ . '/views/layout.php';
}

function ofx_not_found(): void
{
    http_response_code(404);
    ofx_render('errors/404', ['title' => 'Not Found']);
}

function ofx_redirect(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function ofx_h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

const OFX_README_MAX_CHARS = 20000;

// Resolves a markdown image/link target for rendering: an absolute
// http(s) URL passes through unchanged; a protocol-relative "//host/..."
// gets an explicit https: prefix; a bare "#fragment" passes through
// (harmless, browser-only); anything else with a URI scheme (javascript:,
// data:, mailto:, etc) is rejected, same as the original http(s)-only
// allowlist did. A genuine schemeless relative path - the common case
// for a Github README image like "./img/x.png" - is resolved against
// the repo/branch context when one is given: $mode 'raw' for an actual
// image (raw.githubusercontent.com), 'blob' for a browsable link
// (github.com/.../blob/...). Returns null when nothing safe/resolvable
// is available, so the caller can drop the image/strip the link exactly
// as it already did before relative-path resolution existed.
function ofx_md_resolve_url(string $url, ?string $repoFullName, ?string $defaultBranch, string $mode): ?string
{
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }
    if ($url === '' || $url[0] === '#') {
        return $url;
    }
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $url)) {
        return null;
    }
    if (!$repoFullName || !$defaultBranch) {
        return null;
    }
    $path = preg_replace('#^\./#', '', ltrim($url, '/'));
    $base = $mode === 'raw'
        ? "https://raw.githubusercontent.com/{$repoFullName}/{$defaultBranch}/"
        : "https://github.com/{$repoFullName}/blob/{$defaultBranch}/";
    return $base . $path;
}

// Minimal, dependency-free markdown-to-HTML for READMEs, which are
// written by arbitrary Github users and therefore untrusted input.
// The whole source is escaped FIRST (ofx_h), before any formatting
// rule runs - a literal <script> in someone's README becomes inert
// text (&lt;script&gt;) at that point and stays that way, since none
// of the rules below look for angle brackets. Because escaping
// happens before a rule builds an href="..."/src="..." attribute, a
// quote character in a URL is already &quot; by then too, so it can't
// break out of the attribute either. Deliberately basic - ATX and
// Setext headings, bold/italic, inline code, fenced code blocks,
// images and links (http/https, or a relative path resolved against
// $repoFullName/$defaultBranch when given), simple "- " lists, and
// GFM pipe tables.
function ofx_render_markdown_lite(string $markdown, ?string $repoFullName = null, ?string $defaultBranch = null): string
{
    $repoFullName = $repoFullName !== null ? ofx_h($repoFullName) : null;
    $defaultBranch = $defaultBranch !== null ? ofx_h($defaultBranch) : null;

    $markdown = mb_substr($markdown, 0, OFX_README_MAX_CHARS);
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    $html = ofx_h($markdown);

    // A literal <br> survives escaping as the inert text "&lt;br&gt;"
    // (by design, per the note above) - this narrowly un-escapes just
    // that one tag shape back into a real line break, nothing else.
    $html = preg_replace('/&lt;br\s*\/?&gt;/i', '<br>', $html);

    $html = preg_replace_callback('/```[a-zA-Z0-9]*\n(.*?)\n?```/s', function (array $m): string {
        return '<pre class="md-code"><code>' . $m[1] . '</code></pre>';
    }, $html);

    // Setext headings (Title\n===  or  Title\n---) before ATX ones,
    // since both end up producing the same <h1>/<h2> tags
    $html = preg_replace('/^(.+)\n={3,}[ \t]*$/m', '<h1>$1</h1>', $html);
    $html = preg_replace('/^(.+)\n-{3,}[ \t]*$/m', '<h2>$1</h2>', $html);

    // Up to 3 leading spaces tolerated (real READMEs sometimes have
    // them), the space after the #'s is optional (plenty of READMEs
    // skip it), and an optional trailing " ##" closing sequence is
    // stripped - all three are common real-world variations the
    // strict original version of this regex silently left as plain
    // text instead of a heading.
    $html = preg_replace('/^[ \t]{0,3}####[ \t]?(.+?)(?:[ \t]+#+[ \t]*)?$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^[ \t]{0,3}###[ \t]?(.+?)(?:[ \t]+#+[ \t]*)?$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^[ \t]{0,3}##[ \t]?(.+?)(?:[ \t]+#+[ \t]*)?$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^[ \t]{0,3}#[ \t]?(.+?)(?:[ \t]+#+[ \t]*)?$/m', '<h1>$1</h1>', $html);

    // GFM pipe tables: a header row, a |---|---| separator row (at
    // least two dash-runs so a plain "---" thematic break/Setext
    // underline never matches this), then one or more body rows
    $html = preg_replace_callback(
        '/^(.*\|.*)\n\|?[ \t]*:?-+:?[ \t]*(?:\|[ \t]*:?-+:?[ \t]*)*\|?[ \t]*\n((?:.*\|.*\n?)+)/m',
        function (array $m): string {
            $head = array_map('trim', explode('|', trim($m[1], "| \t")));
            $headHtml = '<tr>' . implode('', array_map(fn($c) => '<th>' . $c . '</th>', $head)) . '</tr>';

            $bodyHtml = '';
            foreach (array_filter(explode("\n", rtrim($m[2], "\n"))) as $line) {
                if (!str_contains($line, '|')) {
                    continue;
                }
                $cells = array_map('trim', explode('|', trim($line, "| \t")));
                $bodyHtml .= '<tr>' . implode('', array_map(fn($c) => '<td>' . $c . '</td>', $cells)) . '</tr>';
            }

            return '<table class="md-table"><thead>' . $headHtml . '</thead><tbody>' . $bodyHtml . '</tbody></table>';
        },
        $html
    );

    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);
    $html = preg_replace('/(?<!\*)\*([^*]+?)\*(?!\*)/', '<em>$1</em>', $html);

    $html = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $html);

    // Reference-style definitions - [ref]: http://url "title" - collected
    // and stripped before the images/links rules below so they have a
    // lookup table ready by the time they need to resolve a ![alt][ref]
    // or [text][ref]. Runs over already-escaped text same as every rule
    // here, so a definition is just as safe as the inline (url) forms.
    // A quote character in the source is already the entity &quot;/
    // &#039; by this point (ofx_h ran before any of these rules), so
    // that's what the optional title here has to match against, not a
    // literal quote - there isn't one left in the string to find.
    $refs = [];
    $html = preg_replace_callback(
        '/^[ \t]{0,3}\[([^\]]+)\]:[ \t]*(\S+)[ \t]*(?:&quot;.*?&quot;|&#0?39;.*?&#0?39;|\([^)]*\))?[ \t]*$/m',
        function (array $m) use (&$refs): string {
            $refs[strtolower(trim($m[1]))] = $m[2];
            return '';
        },
        $html
    );

    // Images before links - ![alt](url) shares [alt](url) syntax with
    // a link, just prefixed with "!", so this has to run first or the
    // link rule below partially matches it and leaves a stray "!". An
    // optional "title" after the URL (some READMEs add one) is matched
    // and discarded here - see the &quot; note above, same reason - and
    // the URL itself goes through ofx_md_resolve_url() so a relative
    // path - the common case for a README image, not a full https://
    // URL - still renders instead of silently vanishing.
    $html = preg_replace_callback(
        '/!\[([^\]]*)\]\(([^)\s]+)(?:[ \t]+&quot;.*?&quot;)?\)/',
        function (array $m) use ($repoFullName, $defaultBranch): string {
            $src = ofx_md_resolve_url($m[2], $repoFullName, $defaultBranch, 'raw');
            if ($src === null) {
                return '';
            }
            return '<img src="' . $src . '" alt="' . $m[1] . '" loading="lazy">';
        },
        $html
    );

    // Reference-style images - ![alt][ref], or ![alt][] which reuses
    // alt as the ref key - resolved against $refs above.
    $html = preg_replace_callback('/!\[([^\]]*)\]\[([^\]]*)\]/', function (array $m) use ($refs, $repoFullName, $defaultBranch): string {
        $key = strtolower(trim($m[2] !== '' ? $m[2] : $m[1]));
        $url = $refs[$key] ?? null;
        $src = $url !== null ? ofx_md_resolve_url($url, $repoFullName, $defaultBranch, 'raw') : null;
        if ($src === null) {
            return $m[1];
        }
        return '<img src="' . $src . '" alt="' . $m[1] . '" loading="lazy">';
    }, $html);

    $html = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)\s]+)(?:[ \t]+&quot;.*?&quot;)?\)/',
        function (array $m) use ($repoFullName, $defaultBranch): string {
            $href = ofx_md_resolve_url($m[2], $repoFullName, $defaultBranch, 'blob');
            if ($href === null) {
                return $m[1];
            }
            return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        },
        $html
    );

    // Reference-style links - [text][ref], or [text][] which reuses
    // text as the ref key.
    $html = preg_replace_callback('/\[([^\]]+)\]\[([^\]]*)\]/', function (array $m) use ($refs, $repoFullName, $defaultBranch): string {
        $key = strtolower(trim($m[2] !== '' ? $m[2] : $m[1]));
        $url = $refs[$key] ?? null;
        $href = $url !== null ? ofx_md_resolve_url($url, $repoFullName, $defaultBranch, 'blob') : null;
        if ($href === null) {
            return $m[1];
        }
        return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
    }, $html);

    // allow (and strip) leading whitespace so an indented list item -
    // common under a paragraph or nested one level - still matches;
    // the ^ anchor otherwise only catches a dash/star at column zero
    $html = preg_replace_callback('/(?:^[ \t]*[-*] .+\n?)+/m', function (array $m): string {
        $items = array_filter(array_map('trim', explode("\n", trim($m[0]))));
        $lis = array_map(fn($i) => '<li>' . preg_replace('/^[-*] /', '', $i) . '</li>', $items);
        return '<ul>' . implode('', $lis) . '</ul>';
    }, $html);

    // blockquotes - already-escaped input means a literal ">" is "&gt;"
    // by this point, so match that instead of the raw character
    $html = preg_replace_callback('/(?:^[ \t]*&gt;[ \t]?.*\n?)+/m', function (array $m): string {
        $lines = array_map(function (string $line): string {
            return preg_replace('/^[ \t]*&gt;[ \t]?/', '', $line);
        }, explode("\n", rtrim($m[0], "\n")));
        return '<blockquote>' . implode('<br>', $lines) . '</blockquote>';
    }, $html);

    // Github READMEs are routinely hard-wrapped at a fixed column width
    // by whatever editor wrote them - treating every remaining newline
    // as a literal <br> (nl2br below) kept that fixed width forever
    // instead of letting the paragraph reflow to fit this page. A
    // single newline inside a block of prose becomes a space instead;
    // a blank line (a real paragraph break) is preserved so nl2br still
    // turns it into a visible gap. Fenced code blocks are protected from
    // both this reflow AND nl2br() below - their line breaks are
    // meaningful and must stay exactly as written, and a <pre> already
    // renders raw newlines as real line breaks on its own, so adding
    // <br> tags in there too would double every line break - by
    // splitting them out first and applying neither step to them.
    $blocks = preg_split('/(<pre class="md-code">.*?<\/pre>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($blocks as $i => $block) {
        if ($i % 2 === 1) {
            continue;
        }
        $block = preg_replace('/\n{2,}/', "\n\n", $block);
        $block = preg_replace('/(?<!\n)\n(?!\n)/', ' ', $block);
        $blocks[$i] = nl2br($block);
    }

    return implode('', $blocks);
}

function ofx_flash_get(): ?string
{
    ofx_session_start();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function ofx_avatar_url(?string $url): string
{
    return $url ?: '/app/assets/img/default-gravatar.png';
}

function ofx_addon_url(string $fullName): string
{
    [$owner, $repo] = array_pad(explode('/', $fullName, 2), 2, '');
    return '/addons/' . rawurlencode($owner) . '/' . rawurlencode($repo);
}

function ofx_slugify(string $name): string
{
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

// Pure name slug, no id in the URL - ofx_categories_show() looks a
// category up by matching this slug against every category's name
// (a handful of rows, cheap to scan), falling back to a numeric id if
// the segment is all digits so old /categories/{id} links kept working.
function ofx_category_url(array $category): string
{
    $slug = ofx_slugify((string)($category['name'] ?? ''));
    return '/categories/' . ($slug !== '' ? $slug : (int)$category['id']);
}

function ofx_thumbnail_url(string $fullName, ?string $override = null): string
{
    if ($override) {
        return $override;
    }
    return 'https://github.com/' . $fullName . '/raw/HEAD/ofxaddons_thumbnail.png';
}

// Full priority for what thumbnail actually gets shown: an owner's
// manual override wins, then a real ofxaddons_thumbnail.png the repo
// ships itself, then an admin-generated AI thumbnail as a last resort.
// Returns null (not a placeholder) when none of the three apply, so the
// caller can skip rendering an <img> at all, same as before this existed.
function ofx_addon_thumbnail_url(array $addon): ?string
{
    if (!empty($addon['thumbnail_url_override'])) {
        return $addon['thumbnail_url_override'];
    }
    if (!empty($addon['has_thumbnail']) && !empty($addon['full_name'])) {
        return ofx_thumbnail_url($addon['full_name']);
    }
    if (!empty($addon['ai_thumbnail_generated_at']) && !empty($addon['id'])) {
        return ofx_asset_url('/app/assets/generated-thumbnails/' . (int)$addon['id'] . '.png');
    }
    return null;
}

function ofx_asset_url(string $path): string
{
    $file = dirname(__DIR__) . $path;
    $v = is_readable($file) ? filemtime($file) : time();
    return $path . '?v=' . $v;
}

function ofx_addon_partial(array $addon): void
{
    include __DIR__ . '/views/partials/addon-card.php';
}

function ofx_category_addon_partial(array $addon, int $categoryId, bool $isAdmin): void
{
    include __DIR__ . '/views/partials/category-addon-card.php';
}

// /my/addons has its own row shape (a Thumbnail URL column the admin
// table doesn't have), so it doesn't share ofx_admin_row_partial().
function ofx_my_addon_row_partial(array $repo, array $categories, array $selectedCategoryIds, bool $isBanned = false): void
{
    include __DIR__ . '/views/partials/my-addon-row.php';
}

function ofx_category_picker(array $categories, array $selectedCategoryIds): void
{
    include __DIR__ . '/views/partials/category-picker.php';
}

function ofx_addon_grid(array $addons, bool $hasMore, string $nextUrl): void
{
    include __DIR__ . '/views/partials/addon-grid.php';
}

function ofx_is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function ofx_next_page_url(int $nextPage): string
{
    $params = $_GET;
    $params['page'] = $nextPage;
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return $path . '?' . http_build_query($params);
}

function ofx_paginate_slice(array $rows, int $limit): array
{
    $hasMore = count($rows) > $limit;
    return [array_slice($rows, 0, $limit), $hasMore];
}

function ofx_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    $mins = intdiv($diff, 60);
    if ($mins < 60) {
        return "{$mins}m ago";
    }
    $hours = intdiv($mins, 60);
    if ($hours < 24) {
        return "{$hours}h ago";
    }
    $days = intdiv($hours, 24);
    if ($days < 30) {
        return "{$days}d ago";
    }
    $months = intdiv($days, 30);
    if ($months < 12) {
        return "{$months}mo ago";
    }
    $years = intdiv($months, 12);
    return "{$years}y ago";
}
