<?php

require __DIR__ . '/bootstrap.php';

$sitemapsDir = realpath(__DIR__ . '/../storage/sitemaps') ?: (__DIR__ . '/../storage/sitemaps');
if (!is_dir($sitemapsDir)) {
    @mkdir($sitemapsDir, 0755, true);
    $sitemapsDir = __DIR__ . '/../storage/sitemaps';
}

function fetchAndCache(string $url, string $dir): string
{
    $filename = basename(parse_url($url, PHP_URL_PATH) ?? 'sitemap.xml') ?: 'sitemap.xml';
    $cachePath = $dir . DIRECTORY_SEPARATOR . $filename;
    $ttl = 6 * 3600;

    if (is_file($cachePath) && (time() - (int)@filemtime($cachePath)) < $ttl) {
        $raw = @file_get_contents($cachePath);
        if ($raw !== false) {
            return $raw;
        }
    }

    $res = httpRequest($url, 'GET');
    $raw = (string)($res['body'] ?? '');
    if (!empty($res['ok']) && $raw !== '') {
        @file_put_contents($cachePath, $raw);
        return $raw;
    }

    return is_file($cachePath) ? (@file_get_contents($cachePath) ?: '') : '';
}

function postTypeFromSitemapName(string $filename): string
{
    $base = preg_replace('/-sitemap\.xml$/i', '', $filename);
    return $base !== '' ? $base : $filename;
}

function titleFromUrl(string $url): string
{
    $path = trim((string)(parse_url($url, PHP_URL_PATH) ?? ''), '/');
    $segments = array_values(array_filter(explode('/', $path)));
    $slug = (string)(end($segments) ?: $path);
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

function extractCategoryLabels(array $entries): array
{
    $categories = [];
    foreach ($entries as $entry) {
        $path = trim((string)(parse_url($entry['loc'], PHP_URL_PATH) ?? ''), '/');
        $segments = array_values(array_filter(explode('/', $path)));

        if (!$segments) {
            continue;
        }

        if (($segments[0] ?? '') === 'category' && isset($segments[1])) {
            $slug = strtolower($segments[1]);
        } else {
            $slug = strtolower(end($segments) ?: '');
        }

        if ($slug !== '') {
            $categories[$slug] = ucwords(str_replace(['-', '_'], ' ', $slug));
        }
    }

    $out = [];
    foreach ($categories as $slug => $label) {
        $out[] = ['slug' => $slug, 'label' => $label];
    }
    usort($out, static fn($a, $b) => strcmp($a['label'], $b['label']));

    return $out;
}

$allLinks      = [];
$sitemapPages  = [];
$categoryEntries = [];
$postTypes     = [];

foreach ($appConfig['sitemaps'] as $sitemapUrl) {
    $raw          = fetchAndCache($sitemapUrl, $sitemapsDir);
    $entries      = getXmlEntries($raw);
    $sitemapName  = basename(parse_url($sitemapUrl, PHP_URL_PATH) ?? 'sitemap.xml') ?: 'sitemap.xml';
    $isCategory   = stripos($sitemapName, 'category') !== false;
    $postType     = postTypeFromSitemapName($sitemapName);

    foreach ($entries as $entry) {
        $allLinks[] = $entry['loc'];
    }

    if ($isCategory) {
        $categoryEntries = $entries;
    } else {
        if (!in_array($postType, $postTypes, true)) {
            $postTypes[] = $postType;
        }
        foreach ($entries as $entry) {
            $sitemapPages[] = [
                'url'       => $entry['loc'],
                'title'     => titleFromUrl($entry['loc']),
                'lastmod'   => $entry['lastmod'],
                'post_type' => $postType,
                'sitemap'   => $sitemapName,
            ];
        }
    }
}

$allLinks = array_values(array_unique($allLinks));
$categories = extractCategoryLabels($categoryEntries);

usort($sitemapPages, static function ($a, $b) {
    return strcmp($b['lastmod'], $a['lastmod']);
});

jsonResponse([
    'links'      => $allLinks,
    'categories' => $categories,
    'pages'      => $sitemapPages,
    'post_types' => $postTypes,
]);
