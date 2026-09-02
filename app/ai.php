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

function ofx_generate_description(string $repoName, string $readme): ?string
{
    $apiKey = ofx_env('OPENAI_API_KEY');
    if (!$apiKey) {
        return null;
    }

    $excerpt = mb_substr($readme, 0, 6000);

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You write a single concise, factual sentence (max ~25 words) describing what a '
                    . 'piece of software does, based on its README. No marketing language, no "This repo/addon '
                    . 'is..." preamble - just state what it does. Plain text only, no markdown.',
            ],
            [
                'role' => 'user',
                'content' => "Repo name: {$repoName}\n\nREADME:\n{$excerpt}",
            ],
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
