<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    file_put_contents(__DIR__ . '/../errori.txt',
        "❌ PHP Error\n" .
        "Timestamp: " . date('Y-m-d H:i:s') . "\n" .
        "File: " . $errfile . ":" . $errline . "\n" .
        "Error: " . $errstr . "\n\n",
        FILE_APPEND
    );
    return true;
});

set_exception_handler(function($exception) {
    file_put_contents(__DIR__ . '/../errori.txt',
        "❌ Exception\n" .
        "Timestamp: " . date('Y-m-d H:i:s') . "\n" .
        "Exception: " . get_class($exception) . "\n" .
        "Message: " . $exception->getMessage() . "\n" .
        "File: " . $exception->getFile() . ":" . $exception->getLine() . "\n\n",
        FILE_APPEND
    );
    jsonResponse(['error' => 'Errore interno del server', 'exception' => $exception->getMessage()], 500);
});

/**
 * Consente al frontend statico GitHub Pages di usare il backend Aruba.
 * La password non viene mai salvata nel repository: il browser la invia
 * nell'header X-Content-Hub-Key e il server la verifica contro l'hash
 * configurato per l'autenticazione.
 */
function contentHubApplyCors(): void
{
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowedOrigin = 'https://formulapaddock90-hue.github.io';

    if ($origin === $allowedOrigin) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Content-Hub-Key');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Max-Age: 600');
    }
}

contentHubApplyCors();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../auth.php';

$bridgeOrigin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$bridgeKey = trim((string)($_SERVER['HTTP_X_CONTENT_HUB_KEY'] ?? ''));
$bridgeAuthorized = (
    $bridgeOrigin === 'https://formulapaddock90-hue.github.io'
    && $bridgeKey !== ''
    && AUTH_PASSWORD_HASH !== ''
    && password_verify($bridgeKey, AUTH_PASSWORD_HASH)
);

define('CONTENT_HUB_BRIDGE', $bridgeAuthorized);

if (!$bridgeAuthorized) {
    checkAuth();
}

$appConfig = require __DIR__ . '/../config.php';
$sitesConfig = $appConfig;

date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

function jsonResponse($payload, int $status = 200): void
{
    http_response_code($status);
    contentHubApplyCors();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function httpRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null, int $timeout = 30): array
{
    $headerLines = ["User-Agent: F1ContentHub/1.0"];
    foreach ($headers as $key => $value) {
        $headerLines[] = is_string($key) ? ($key . ': ' . $value) : (string)$value;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response !== false) {
            $rawHeaders = substr($response, 0, $headerSize);
            $rawBody = substr($response, $headerSize);
            $responseHeaders = array_values(array_filter(preg_split('/\r\n|\n|\r/', $rawHeaders) ?: []));

            return [
                'ok' => true,
                'status' => $statusCode,
                'body' => $rawBody,
                'headers' => $responseHeaders,
            ];
        }

        file_put_contents(__DIR__ . '/../errori.txt',
            "❌ HTTP cURL Error\n" .
            "Timestamp: " . date('Y-m-d H:i:s') . "\n" .
            "URL: " . preg_replace('/([?&]key=)[^&]+/i', '$1***', $url) . "\n" .
            "Error: " . $curlError . "\n\n",
            FILE_APPEND
        );

        return [
            'ok' => false,
            'status' => $statusCode,
            'body' => '',
            'headers' => [],
            'error' => $curlError,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headerLines) . "\r\n",
            'content' => $body ?? '',
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    if ($raw === false) {
        $lastError = error_get_last();
        file_put_contents(__DIR__ . '/../errori.txt',
            "❌ HTTP Stream Error\n" .
            "Timestamp: " . date('Y-m-d H:i:s') . "\n" .
            "URL: " . preg_replace('/([?&]key=)[^&]+/i', '$1***', $url) . "\n" .
            "Error: " . (string)($lastError['message'] ?? 'Richiesta fallita') . "\n\n",
            FILE_APPEND
        );
    }

    $statusCode = 0;
    if (!empty($responseHeaders) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $statusCode = (int)$m[1];
    }

    return [
        'ok' => $raw !== false,
        'status' => $statusCode,
        'body' => $raw === false ? '' : $raw,
        'headers' => $responseHeaders,
    ];
}

function getJson(string $url): array
{
    $res = httpRequest($url, 'GET');
    if (!$res['ok']) {
        return [];
    }

    $decoded = json_decode($res['body'], true);
    return is_array($decoded) ? $decoded : [];
}

function stripPageMetaTags(string $html): string
{
    $html = preg_replace('/<meta\b[^>]*>/i', '', $html) ?? $html;
    $html = preg_replace('/<title\b[^>]*>.*?<\/title>/si', '', $html) ?? $html;
    $html = preg_replace('/<\/?(html|head|body)\b[^>]*>/i', '', $html) ?? $html;
    return trim($html);
}

function postJson(string $url, array $payload, array $headers = [], int $timeout = 90): array
{
    $headers['Content-Type'] = 'application/json';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $res = httpRequest($url, 'POST', $headers, $body === false ? '{}' : $body, $timeout);

    $decoded = json_decode($res['body'] ?? '', true);
    return [
        'ok' => (bool)($res['ok'] ?? false),
        'status' => $res['status'],
        'raw' => $res['body'],
        'json' => is_array($decoded) ? $decoded : [],
        'error' => (string)($res['error'] ?? ''),
    ];
}

function getXmlUrls(string $url): array
{
    $res = httpRequest($url, 'GET');
    $raw = (string)($res['body'] ?? '');
    if (!$res['ok'] || $raw === '') {
        return [];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    if ($xml === false) {
        return [];
    }

    $urls = [];
    foreach ($xml->url as $node) {
        $loc = (string)($node->loc ?? '');
        if ($loc !== '') {
            $urls[] = $loc;
        }
    }

    return $urls;
}

function getXmlEntries(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    if ($xml === false) {
        return [];
    }

    $entries = [];
    foreach ($xml->url as $node) {
        $loc = (string)($node->loc ?? '');
        if ($loc !== '') {
            $entries[] = [
                'loc'     => $loc,
                'lastmod' => (string)($node->lastmod ?? ''),
            ];
        }
    }

    return $entries;
}

function mapBy(array $rows, string $key): array
{
    $mapped = [];
    foreach ($rows as $row) {
        if (isset($row[$key])) {
            $mapped[$row[$key]] = $row;
        }
    }
    return $mapped;
}
