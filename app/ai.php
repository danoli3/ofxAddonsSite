<?php
declare(strict_types=1);

function ofx_fetch_readme(string $fullName): ?string
{
    [$owner, $repo] = array_pad(explode('/', $fullName, 2), 2, '');
    $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/readme';

    $token = ofx_env('GITHUB_TOKEN');
    $headers = ['Accept: application/vnd.github.v3+json', 'User-Agent: ofxaddons-site'];
    if ($token) {
        $headers[] = "Authorization: token {$token}";
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$body) {
        return null;
    }

    $data = json_decode($body, true);
    if (($data['encoding'] ?? null) !== 'base64' || empty($data['content'])) {
        return null;
    }

    $decoded = base64_decode(str_replace("\n", '', $data['content']), true);
    return $decoded !== false ? $decoded : null;
}

// Live-fetched on the addon detail page, same as the README above -
// only called when has_releases is already true (set by the crawler's
// cheap releases/latest check) so this doesn't waste a call on the
// majority of addons that have never cut a release.
function ofx_fetch_latest_release(string $fullName): ?array
{
    [$owner, $repo] = array_pad(explode('/', $fullName, 2), 2, '');
    $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/releases/latest';

    $token = ofx_env('GITHUB_TOKEN');
    $headers = ['Accept: application/vnd.github.v3+json', 'User-Agent: ofxaddons-site'];
    if ($token) {
        $headers[] = "Authorization: token {$token}";
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$body) {
        return null;
    }

    $data = json_decode($body, true);
    if (empty($data['tag_name'])) {
        return null;
    }

    return [
        'tag_name' => $data['tag_name'],
        'name' => $data['name'] ?? null,
        'published_at' => $data['published_at'] ?? null,
        'html_url' => $data['html_url'] ?? null,
        'body' => $data['body'] ?? null,
    ];
}

// Fetches one repo's full data from Github and shapes it exactly like
// a single item of the crawler's data/addons.json snapshot, so it can
// be handed straight to ofx_apply_crawl_snapshot() - used by the admin
// "add a repo manually" action, for real addons that use a different
// naming convention (e.g. drawcall/ofmUI) than the "ofx" prefix
// crawl.php searches Github for, so it would never surface on its own.
function ofx_fetch_repo_snapshot(string $fullName): ?array
{
    [$owner, $repo] = array_pad(explode('/', $fullName, 2), 2, '');

    $token = ofx_env('GITHUB_TOKEN');
    $headers = ['Accept: application/vnd.github.v3+json', 'User-Agent: ofxaddons-site'];
    if ($token) {
        $headers[] = "Authorization: token {$token}";
    }

    $ch = curl_init('https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$body) {
        return null;
    }
    $item = json_decode($body, true);
    if (empty($item['full_name'])) {
        return null;
    }

    $hasMakefile = false;
    $exampleCount = 0;
    $hasCorrectFolder = false;
    $hasThumbnail = false;

    $ch = curl_init('https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/contents');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $contentsBody = curl_exec($ch);
    $contentsStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($contentsStatus === 200 && $contentsBody) {
        foreach (json_decode($contentsBody, true) ?: [] as $entry) {
            $name = $entry['name'] ?? '';
            if ($name === 'addon_config.mk' || $name === 'addon.make') {
                $hasMakefile = true;
            } elseif (preg_match('/example/i', $name)) {
                $exampleCount++;
            } elseif (preg_match('/src/i', $name)) {
                $hasCorrectFolder = true;
            } elseif (preg_match('/ofxaddons_thumbnail\.png/i', $name)) {
                $hasThumbnail = true;
            }
        }
    }

    return [
        'full_name' => $item['full_name'],
        'name' => $item['name'] ?? null,
        'description' => $item['description'] ?? null,
        'owner' => [
            'id' => $item['owner']['id'] ?? null,
            'login' => $item['owner']['login'] ?? null,
            'avatar_url' => $item['owner']['avatar_url'] ?? null,
        ],
        'fork' => !empty($item['fork']),
        'parent' => $item['parent']['full_name'] ?? null,
        'source' => $item['source']['full_name'] ?? null,
        'stargazers_count' => (int)($item['stargazers_count'] ?? 0),
        'forks_count' => (int)($item['forks_count'] ?? 0),
        'pushed_at' => $item['pushed_at'] ?? null,
        'created_at' => $item['created_at'] ?? null,
        'default_branch' => $item['default_branch'] ?? null,
        'has_makefile' => $hasMakefile,
        'example_count' => $exampleCount,
        'has_correct_folder_structure' => $hasCorrectFolder,
        'has_thumbnail' => $hasThumbnail,
        'archived' => !empty($item['archived']),
        'has_releases' => ofx_fetch_latest_release($item['full_name']) !== null,
        'newer_forks' => [],
        'ahead_branches' => [],
    ];
}

// Live cross-repo compare, used once when an admin confirms a fork
// relationship Github's own metadata missed or lost (e.g. the parent
// repo network is detached) - tells us whether the presumed fork
// actually has unique commits ahead of the original, and when the
// most recent one landed, independent of either repo's own pushed_at.
function ofx_fetch_fork_compare(string $parentFullName, string $parentBranch, string $forkFullName, string $forkBranch): ?array
{
    [$parentOwner, $parentRepo] = array_pad(explode('/', $parentFullName, 2), 2, '');
    [$forkOwner] = array_pad(explode('/', $forkFullName, 2), 2, '');

    $token = ofx_env('GITHUB_TOKEN');
    $headers = ['Accept: application/vnd.github.v3+json', 'User-Agent: ofxaddons-site'];
    if ($token) {
        $headers[] = "Authorization: token {$token}";
    }

    $base = rawurlencode($parentBranch);
    $head = rawurlencode($forkOwner) . ':' . rawurlencode($forkBranch);
    $url = 'https://api.github.com/repos/' . rawurlencode($parentOwner) . '/' . rawurlencode($parentRepo)
        . "/compare/{$base}...{$head}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$body) {
        return null;
    }

    $data = json_decode($body, true);
    $commits = $data['commits'] ?? [];
    $lastCommit = end($commits);

    return [
        'ahead_by' => (int)($data['ahead_by'] ?? 0),
        'behind_by' => (int)($data['behind_by'] ?? 0),
        'last_commit_at' => $lastCommit['commit']['committer']['date']
            ?? $lastCommit['commit']['author']['date']
            ?? null,
    ];
}

// With $existingDescription: writes an ADDITIONAL sentence covering
// something the existing text doesn't already say (platform, dependencies,
// what problem it solves, ...) instead of restating it - the caller
// appends this to the existing description rather than replacing it,
// for a repo that already has a short description (e.g. synced from
// Github's own repo description field) and just needs more detail.
function ofx_generate_description(string $repoName, string $readme, ?string $existingDescription = null): ?string
{
    $apiKey = ofx_env('OPENAI_API_KEY');
    if (!$apiKey) {
        return null;
    }

    $excerpt = mb_substr($readme, 0, 6000);

    if ($existingDescription) {
        $systemPrompt = 'You write a single concise, factual sentence (max ~20 words) that ADDS a new, '
            . 'specific detail about a piece of software, based on its README - something not already '
            . 'covered by the existing description you are given (platform/dependencies, what problem it '
            . 'solves, a notable feature, etc). Do not restate the existing description. No marketing '
            . 'language, no "This repo/addon is..." preamble - just state the new fact. Plain text only, no markdown.';
        $userContent = "Repo name: {$repoName}\n\nExisting description: {$existingDescription}\n\nREADME:\n{$excerpt}";
    } else {
        $systemPrompt = 'You write a single concise, factual sentence (max ~25 words) describing what a '
            . 'piece of software does, based on its README. No marketing language, no "This repo/addon '
            . 'is..." preamble - just state what it does. Plain text only, no markdown.';
        $userContent = "Repo name: {$repoName}\n\nREADME:\n{$excerpt}";
    }

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ],
        'temperature' => 0.3,
        'max_tokens' => 80,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$apiKey}",
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$body) {
        return null;
    }

    $data = json_decode($body, true);
    $text = $data['choices'][0]['message']['content'] ?? null;
    return $text ? trim($text) : null;
}
