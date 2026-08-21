<?php

declare(strict_types=1);

function getSources(): array
{
    return [
        'https://www.formula1.com/en/latest',
        'https://www.formula1.com/en/racing/2026.html',
        'https://www.formula1.com/en/timing/f1-live-lite',
        'https://williamsracing.photos',
        'https://media.haasf1team.com/',
        'https://mercedes-benz-archive.com/marsF1/',
        'https://www.redbullcontentpool.com/login',
        'https://astonmartinf1.canto.global/allfiles?viewIndex=0',
        'https://mediahub.sauber-group.com/login/',
        'https://f1pressarea.pirelli.com/index.php',
        'https://brandfolder.com/organizations/mclaren/signin'
    ];
}

function newPhotoMetrics(): array
{
    return [
        'fetch_seconds' => 0.0,
        'parse_seconds' => 0.0,
        'download_seconds' => 0.0,
        'io_seconds' => 0.0,
        'sources_total' => 0,
        'sources_ok' => 0,
        'html_requests' => 0,
        'html_failures' => 0,
        'image_candidates' => 0,
        'image_urls_unique' => 0,
        'cache_hits' => 0,
        'image_download_attempts' => 0,
        'image_download_success' => 0,
        'image_download_failures' => 0,
        'downloaded_bytes' => 0,
        'items_built' => 0,
    ];
}

function getF1HubCookieFile(string $domain): ?string
{
    static $cookies = [];
    if (isset($cookies[$domain])) {
        return $cookies[$domain];
    }
    
    $cookieFile = tempnam(sys_get_temp_dir(), 'f1cookie_' . preg_replace('/[^a-zA-Z0-9]/', '', $domain));
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'Mozilla/5.0 F1PhotoWall/1.1',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($domain === 'f1pressarea.pirelli.com') {
        curl_setopt($ch, CURLOPT_URL, 'https://f1pressarea.pirelli.com/login.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'email' => 'info@formulapaddock.it',
            'password' => 'Gattipc231090',
            'login' => 'Login'
        ]));
        curl_exec($ch);
    } elseif ($domain === 'mediahub.sauber-group.com') {
        curl_setopt($ch, CURLOPT_URL, 'https://mediahub.sauber-group.com/login/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => 'info@formulapaddock.it',
            'password' => 'Gattipc231090',
            'submit' => 'Login'
        ]));
        curl_exec($ch);
    } elseif ($domain === 'media.haasf1team.com') {
        curl_setopt($ch, CURLOPT_URL, 'https://media.haasf1team.com/login');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'user' => 'HaasF1Media',
            'password' => 'Oxnard'
        ]));
        curl_exec($ch);
    } elseif ($domain === 'williamsracing.photos') {
        curl_setopt($ch, CURLOPT_URL, 'https://williamsracing.photos/login');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => 'williamsmedia',
            'password' => 'Williams25Media'
        ]));
        curl_exec($ch);
    } else {
        curl_close($ch);
        @unlink($cookieFile);
        return null;
    }

    curl_close($ch);
    $cookies[$domain] = $cookieFile;
    return $cookieFile;
}

function fetchHtml(string $url, ?array &$metrics = null): string
{
    $start = microtime(true);
    if (is_array($metrics)) {
        $metrics['html_requests']++;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        if (is_array($metrics)) {
            $metrics['html_failures']++;
            $metrics['fetch_seconds'] += microtime(true) - $start;
        }
        return '';
    }

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Mozilla/5.0 F1PhotoWall/1.1',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];

    $host = parse_url($url, PHP_URL_HOST);
    if ($host) {
        $cookieFile = getF1HubCookieFile($host);
        if ($cookieFile) {
            $opts[CURLOPT_COOKIEFILE] = $cookieFile;
            $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        }
    }

    curl_setopt_array($ch, $opts);

    $html = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (is_array($metrics)) {
        $metrics['fetch_seconds'] += microtime(true) - $start;
    }

    if (!is_string($html) || $status < 200 || $status >= 400) {
        if (is_array($metrics)) {
            $metrics['html_failures']++;
        }
        return '';
    }

    return $html;
}

function absolutizeUrl(string $candidate, string $baseUrl): string
{
    $candidate = trim($candidate);
    if ($candidate === '') {
        return '';
    }

    if (str_starts_with($candidate, '//')) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $candidate;
    }

    if (preg_match('#^https?://#i', $candidate)) {
        return $candidate;
    }

    $base = parse_url($baseUrl);
    if (!is_array($base) || !isset($base['host'])) {
        return $candidate;
    }

    $scheme = $base['scheme'] ?? 'https';
    $host = $base['host'];
    $port = isset($base['port']) ? ':' . $base['port'] : '';

    if (str_starts_with($candidate, '/')) {
        return "{$scheme}://{$host}{$port}{$candidate}";
    }

    $path = $base['path'] ?? '/';
    $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
    $dir = $dir === '' || $dir === '.' ? '' : '/' . $dir;

    return "{$scheme}://{$host}{$port}{$dir}/{$candidate}";
}

function extractImages(string $html, string $baseUrl, ?array &$metrics = null): array
{
    $start = microtime(true);
    $urls = [];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);

    if ($loaded) {
        $xpath = new DOMXPath($dom);

        $imgNodes = $xpath->query('//img[@src]');
        if ($imgNodes !== false) {
            foreach ($imgNodes as $node) {
                $src = (string)$node->attributes?->getNamedItem('src')?->nodeValue;
                if ($src !== '') {
                    $urls[] = absolutizeUrl($src, $baseUrl);
                }
            }
        }

        $dataSrcNodes = $xpath->query('//*[@data-src]');
        if ($dataSrcNodes !== false) {
            foreach ($dataSrcNodes as $node) {
                $src = (string)$node->attributes?->getNamedItem('data-src')?->nodeValue;
                if ($src !== '') {
                    $urls[] = absolutizeUrl($src, $baseUrl);
                }
            }
        }

        $srcsetNodes = $xpath->query('//*[@srcset]');
        if ($srcsetNodes !== false) {
            foreach ($srcsetNodes as $node) {
                $srcset = (string)$node->attributes?->getNamedItem('srcset')?->nodeValue;
                foreach (explode(',', $srcset) as $part) {
                    $candidate = trim(explode(' ', trim($part))[0] ?? '');
                    if ($candidate !== '') {
                        $urls[] = absolutizeUrl($candidate, $baseUrl);
                    }
                }
            }
        }

        $dataSrcsetNodes = $xpath->query('//*[@data-srcset]');
        if ($dataSrcsetNodes !== false) {
            foreach ($dataSrcsetNodes as $node) {
                $srcset = (string)$node->attributes?->getNamedItem('data-srcset')?->nodeValue;
                foreach (explode(',', $srcset) as $part) {
                    $candidate = trim(explode(' ', trim($part))[0] ?? '');
                    if ($candidate !== '') {
                        $urls[] = absolutizeUrl($candidate, $baseUrl);
                    }
                }
            }
        }
    }

    libxml_clear_errors();

    $clean = [];
    foreach ($urls as $u) {
        if (!preg_match('#^https?://#i', $u)) {
            continue;
        }
        $lower = strtolower($u);
        if (str_contains($lower, '.svg') || str_contains($lower, 'sprite')) {
            continue;
        }
        $clean[$u] = true;
    }

    $result = array_slice(array_keys($clean), 0, 60);

    if (is_array($metrics)) {
        $metrics['parse_seconds'] += microtime(true) - $start;
        $metrics['image_candidates'] += count($result);
    }

    return $result;
}

function extensionFromTypeOrUrl(string $contentType, string $url): string
{
    $type = strtolower(trim(explode(';', $contentType)[0]));

    return match ($type) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
        default => (preg_match('#\.(jpg|jpeg|png|webp|gif|avif)(?:\?.*)?$#i', $url, $m)
            ? strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1])
            : 'jpg'),
    };
}

function isSupportedImageContentType(string $contentType): bool
{
    $type = strtolower(trim(explode(';', $contentType)[0]));
    if ($type === '' || !str_starts_with($type, 'image/')) {
        return false;
    }

    return !in_array($type, ['image/svg+xml'], true);
}

function cacheImage(string $url, string $cacheDir, ?array &$metrics = null): ?string
{
    $ioStart = microtime(true);
    $hash = substr(sha1($url), 0, 20);
    $existing = glob($cacheDir . DIRECTORY_SEPARATOR . $hash . '.*');
    if (is_array($existing) && $existing !== []) {
        $file = $existing[0];
        if (is_file($file)) {
            if (is_array($metrics)) {
                $metrics['cache_hits']++;
                $metrics['io_seconds'] += microtime(true) - $ioStart;
            }
            return basename($file);
        }
    }

    $tmpFile = $cacheDir . DIRECTORY_SEPARATOR . $hash . '.tmp';
    $fp = fopen($tmpFile, 'wb');
    if ($fp === false) {
        if (is_array($metrics)) {
            $metrics['image_download_failures']++;
            $metrics['io_seconds'] += microtime(true) - $ioStart;
        }
        return null;
    }

    if (is_array($metrics)) {
        $metrics['io_seconds'] += microtime(true) - $ioStart;
        $metrics['image_download_attempts']++;
    }

    $downloadStart = microtime(true);
    $ch = curl_init($url);
    if ($ch === false) {
        fclose($fp);
        @unlink($tmpFile);
        if (is_array($metrics)) {
            $metrics['image_download_failures']++;
            $metrics['download_seconds'] += microtime(true) - $downloadStart;
        }
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 F1PhotoWall/1.1',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $ok = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    fclose($fp);

    if (is_array($metrics)) {
        $metrics['download_seconds'] += microtime(true) - $downloadStart;
    }

    if ($ok !== true || $status < 200 || $status >= 400 || !isSupportedImageContentType($contentType) || !is_file($tmpFile) || filesize($tmpFile) === 0) {
        @unlink($tmpFile);
        if (is_array($metrics)) {
            $metrics['image_download_failures']++;
        }
        return null;
    }

    $probeStart = microtime(true);
    $dimensions = @getimagesize($tmpFile);
    if (is_array($metrics)) {
        $metrics['io_seconds'] += microtime(true) - $probeStart;
    }
    if (!is_array($dimensions) || ($dimensions[0] ?? 0) < 200 || ($dimensions[1] ?? 0) < 120) {
        @unlink($tmpFile);
        if (is_array($metrics)) {
            $metrics['image_download_failures']++;
        }
        return null;
    }

    $ext = extensionFromTypeOrUrl($contentType, $url);
    $filename = $hash . '.' . $ext;
    $target = $cacheDir . DIRECTORY_SEPARATOR . $filename;

    $renameStart = microtime(true);
    if (!@rename($tmpFile, $target)) {
        @unlink($tmpFile);
        if (is_array($metrics)) {
            $metrics['image_download_failures']++;
            $metrics['io_seconds'] += microtime(true) - $renameStart;
        }
        return null;
    }

    if (is_array($metrics)) {
        $metrics['io_seconds'] += microtime(true) - $renameStart;
        $metrics['image_download_success']++;
        $metrics['downloaded_bytes'] += max(0, (int)filesize($target));
    }

    return $filename;
}

function buildPhotoItems(array $sources, string $cacheDir, ?array &$metrics = null): array
{
    $perf = is_array($metrics) ? $metrics : newPhotoMetrics();
    $start = microtime(true);

    $perf['sources_total'] = count($sources);

    $imageUrls = [];
    foreach ($sources as $sourceUrl) {
        $html = fetchHtml($sourceUrl, $perf);
        if ($html === '') {
            continue;
        }

        $perf['sources_ok']++;

        foreach (extractImages($html, $sourceUrl, $perf) as $img) {
            $imageUrls[$img] = true;
        }
    }

    $imageUrls = array_slice(array_keys($imageUrls), 0, 60);
    $perf['image_urls_unique'] = count($imageUrls);

    $items = [];

    foreach ($imageUrls as $imgUrl) {
        $cached = cacheImage($imgUrl, $cacheDir, $perf);
        if ($cached === null) {
            continue;
        }

        $items[] = [
            'source' => $imgUrl,
            'local' => 'downloads/cache/' . $cached,
        ];
    }

    $perf['items_built'] = count($items);
    $perf['total_seconds'] = microtime(true) - $start;
    $perf['network_seconds'] = $perf['fetch_seconds'] + $perf['download_seconds'];

    if (is_array($metrics)) {
        $metrics = $perf;
    }

    return $items;
}
