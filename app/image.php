<?php
declare(strict_types=1);

const OFX_THUMBNAIL_MAX_BYTES = 8 * 1024 * 1024;
const OFX_THUMBNAIL_ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

function ofx_validate_thumbnail_url(string $url): ?string
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        return 'Thumbnail URL must start with https://';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['User-Agent: ofxaddons-site'],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $contentLength = (int)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        return "Couldn't fetch that URL (status {$status})";
    }

    $contentType = strtolower(trim(explode(';', $contentType)[0] ?? ''));
    if (!in_array($contentType, OFX_THUMBNAIL_ALLOWED_TYPES, true)) {
        return "That URL isn't a supported image type (got \"{$contentType}\")";
    }

    if ($contentLength > 0 && $contentLength > OFX_THUMBNAIL_MAX_BYTES) {
        return 'Image is too large (max ' . (OFX_THUMBNAIL_MAX_BYTES / 1024 / 1024) . 'MB)';
    }

    return null;
}
