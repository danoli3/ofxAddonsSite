<?php
declare(strict_types=1);

function ofx_pages_howto(): void
{
    ofx_render('pages/howto', ['title' => 'How To']);
}

function ofx_pages_about_openframeworks(): void
{
    $commits = ofx_cache_read_data('openframeworks-recent.json') ?? ofx_openframeworks_recent_commits_content();
    ofx_render('pages/about-openframeworks', ['commits' => $commits, 'title' => 'About openFrameworks']);
}

// The last several commits to openframeworks/openFrameworks's default
// branch - unauthenticated (public GitHub API, 60 req/hr is plenty since
// this is cached and only refreshed on crawl sync / admin regenerate, not
// per page view) rather than through our own GITHUB_TOKEN, which the
// openframeworks org's fine-grained-PAT policy rejects for classic tokens.
function ofx_openframeworks_recent_commits_content(): array
{
    $ch = curl_init('https://api.github.com/repos/openframeworks/openFrameworks/commits?per_page=8');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['User-Agent: ofxaddons-site', 'Accept: application/vnd.github.v3+json'],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$body) {
        return [];
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return [];
    }

    return array_map(function (array $c): array {
        $message = (string)($c['commit']['message'] ?? '');
        return [
            'sha' => substr((string)($c['sha'] ?? ''), 0, 7),
            'message' => explode("\n", $message)[0],
            'date' => $c['commit']['author']['date'] ?? null,
            'url' => $c['html_url'] ?? null,
        ];
    }, $data);
}

function ofx_pages_history(): void
{
    ofx_render('pages/history', ['title' => 'History & Credits']);
}

// GET /sitemap - human-readable index of every page/section on the
// site, each with a one-line description of what it's for. The
// machine-readable /sitemap.xml and /sitemap.json cover URLs for every
// individual addon/category/contributor; this page is the "what is
// there to browse" overview for a person, linking out to those two.
function ofx_pages_sitemap(): void
{
    ofx_render('pages/sitemap', ['title' => 'Sitemap']);
}
