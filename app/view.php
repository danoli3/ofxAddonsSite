<?php
declare(strict_types=1);

const OFX_PAGE_SIZE = 24;
const OFX_DESCRIPTION_MAX_LENGTH = 350;

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
// images and links (http/https only), simple "- " lists, and GFM
// pipe tables.
function ofx_render_markdown_lite(string $markdown): string
{
    $markdown = mb_substr($markdown, 0, OFX_README_MAX_CHARS);
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    $html = ofx_h($markdown);

    $html = preg_replace_callback('/```[a-zA-Z0-9]*\n(.*?)\n?```/s', function (array $m): string {
        return '<pre class="md-code"><code>' . $m[1] . '</code></pre>';
    }, $html);

    // Setext headings (Title\n===  or  Title\n---) before ATX ones,
    // since both end up producing the same <h1>/<h2> tags
    $html = preg_replace('/^(.+)\n={3,}[ \t]*$/m', '<h1>$1</h1>', $html);
    $html = preg_replace('/^(.+)\n-{3,}[ \t]*$/m', '<h2>$1</h2>', $html);

    $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

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

    // Images before links - ![alt](url) shares [alt](url) syntax with
    // a link, just prefixed with "!", so this has to run first or the
    // link rule below partially matches it and leaves a stray "!"
    $html = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function (array $m): string {
        if (!preg_match('#^https?://#i', $m[2])) {
            return '';
        }
        return '<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy">';
    }, $html);

    $html = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function (array $m): string {
        if (!preg_match('#^https?://#i', $m[2])) {
            return $m[1];
        }
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
    }, $html);

    $html = preg_replace_callback('/(?:^[-*] .+\n?)+/m', function (array $m): string {
        $items = array_filter(array_map('trim', explode("\n", trim($m[0]))));
        $lis = array_map(fn($i) => '<li>' . preg_replace('/^[-*] /', '', $i) . '</li>', $items);
        return '<ul>' . implode('', $lis) . '</ul>';
    }, $html);

    return nl2br($html);
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

function ofx_thumbnail_url(string $fullName, ?string $override = null): string
{
    if ($override) {
        return $override;
    }
    return 'https://github.com/' . $fullName . '/raw/HEAD/ofxaddons_thumbnail.png';
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
