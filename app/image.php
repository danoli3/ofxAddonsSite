<?php
declare(strict_types=1);

const OFX_THUMBNAIL_MAX_BYTES = 8 * 1024 * 1024;
const OFX_THUMBNAIL_ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

const OFX_THUMBNAIL_MAX_REDIRECTS = 3;

// Any logged-in user can submit a thumbnail URL (not just admins), so
// this has to defend against SSRF. curl's own CURLOPT_FOLLOWLOCATION
// can't be trusted here - it would happily follow a redirect straight
// to an internal address that never went through our host check. So
// redirects are followed manually, re-validating the resolved IP of
// every hop (including the well-known github.com/.../raw/HEAD/...
// convention, which itself redirects to raw.githubusercontent.com).
function ofx_validate_thumbnail_url(string $url): ?string
{
    for ($hop = 0; $hop <= OFX_THUMBNAIL_MAX_REDIRECTS; $hop++) {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return 'Thumbnail URL must start with https://';
        }

        $ips = @gethostbynamel($parts['host']);
        if (!$ips) {
            return "Couldn't resolve that host";
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return "That host isn't allowed";
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['User-Agent: ofxaddons-site'],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $contentLength = (int)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: null;
        curl_close($ch);

        if ($status >= 300 && $status < 400) {
            if (!$location) {
                return "Couldn't fetch that URL (redirect with no location)";
            }
            $url = $location;
            continue;
        }

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

    return 'Too many redirects';
}
