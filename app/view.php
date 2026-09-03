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
// happens before the link rule builds an href="..." attribute, a
// quote character in a URL is already &quot; by then too, so it can't
// break out of the attribute either. Deliberately basic - headings,
// bold/italic, inline code, fenced code blocks, links (http/https
// only), and simple "- " lists.
function ofx_render_markdown_lite(string $markdown): string
{
    $markdown = mb_substr($markdown, 0, OFX_README_MAX_CHARS);
    $html = ofx_h($markdown);

    $html = preg_replace_callback('/```[a-zA-Z0-9]*\n(.*?)\n?```/s', function (array $m): string {
        return '<pre class="md-code"><code>' . $m[1] . '</code></pre>';
    }, $html);

    $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);
    $html = preg_replace('/(?<!\*)\*([^*]+?)\*(?!\*)/', '<em>$1</em>', $html);

    $html = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $html);

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
