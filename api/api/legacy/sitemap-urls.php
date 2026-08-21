<?php
/**
 * Endpoint API per caricare tutti gli URL dalle sitemap
 * Ritorna JSON con array di URL
 */

// Disabilita output di errori per ottenere JSON puro
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

// Carica configurazione app
$appConfig = [];
$configFile = __DIR__ . '/../../config.php';
if (file_exists($configFile)) {
    $appConfig = require $configFile;
}

$sitemapsDir = realpath(__DIR__ . '/../storage/sitemaps') ?: (__DIR__ . '/../storage/sitemaps');
if (!is_dir($sitemapsDir)) {
    @mkdir($sitemapsDir, 0755, true);
}

// Funzioni di parsing sitemap
function getXmlEntries(string $xml): array
{
    $entries = [];
    try {
        $dom = @new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return [];
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $nodes = $xpath->query('//ns:url/ns:loc');
        if ($nodes) {
            foreach ($nodes as $node) {
                $loc = trim((string)$node->nodeValue);
                if ($loc !== '') {
                    $entries[] = ['loc' => $loc, 'lastmod' => ''];
                }
            }
        }
    } catch (Exception $e) {
        // Ignora errori
    }
    return $entries;
}

function fetchAndCache(string $url, string $dir): string
{
    $filename = basename((string)(parse_url($url, PHP_URL_PATH) ?? 'sitemap.xml')) ?: 'sitemap.xml';
    $cachePath = $dir . DIRECTORY_SEPARATOR . $filename;
    $ttl = 6 * 3600;

    if (is_file($cachePath) && (time() - (int)@filemtime($cachePath)) < $ttl) {
        $raw = @file_get_contents($cachePath);
        if ($raw !== false) {
            return $raw;
        }
    }

    $raw = @file_get_contents($url);
    if ($raw !== false) {
        @file_put_contents($cachePath, $raw);
        return $raw;
    }

    return is_file($cachePath) ? (@file_get_contents($cachePath) ?: '') : '';
}

$allUrls = [];

// Carica tutte le sitemap configurate
if (isset($appConfig['sitemaps']) && is_array($appConfig['sitemaps'])) {
    foreach ($appConfig['sitemaps'] as $sitemapUrl) {
        if (empty($sitemapUrl)) continue;
        
        $raw = fetchAndCache($sitemapUrl, $sitemapsDir);
        $entries = getXmlEntries($raw);
        
        foreach ($entries as $entry) {
            if (!empty($entry['loc'])) {
                $allUrls[] = $entry['loc'];
            }
        }
    }
}

// Deduplicazione e ordinamento
$allUrls = array_values(array_unique($allUrls));
sort($allUrls);

// Ritorna JSON
echo json_encode([
    'success' => true,
    'count' => count($allUrls),
    'urls' => $allUrls
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
