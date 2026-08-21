<?php

require __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? 'list';

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function uploadDirs(array $candidates): array
{
    $dirs = [];
    $seen = [];

    foreach ($candidates as $key => $dir) {
        if (!is_dir($dir)) continue;

        $real = realpath($dir);
        if ($real === false) continue;

        $normalized = normalizePath($real);
        if (isset($seen[$normalized])) continue;

        $seen[$normalized] = true;
        $dirs[$key] = $real;
    }

    return $dirs;
}

function collectTextFragments($value, int $depth = 0, bool $allowString = false): array
{
    if ($depth > 12 || $value === null) return [];

    if (is_string($value)) {
        $text = trim($value);
        return ($allowString && $text !== '') ? [$text] : [];
    }

    if (!is_array($value) || $value === []) return [];

    $textKeys = [
        'raw_text', 'text', 'content', 'article', 'body', 'message',
        'description', 'transcript', 'comment', 'note', 'caption', 'summary'
    ];

    $keys = array_keys($value);
    $isList = $keys === range(0, count($value) - 1);

    if ($isList) {
        $allStrings = true;
        foreach ($value as $item) {
            if (!is_string($item)) {
                $allStrings = false;
                break;
            }
        }

        if ($allStrings) {
            $out = [];
            foreach ($value as $item) {
                $item = trim($item);
                if ($item !== '') $out[] = $item;
            }
            return $out;
        }
    }

    $fragments = [];

    foreach ($value as $key => $child) {
        if ($allowString) {
            $fragments = array_merge($fragments, collectTextFragments($child, $depth + 1, true));
            continue;
        }

        $keyName = is_string($key) ? strtolower(trim($key)) : '';
        if ($keyName !== '' && in_array($keyName, $textKeys, true)) {
            $fragments = array_merge($fragments, collectTextFragments($child, $depth + 1, true));
        } elseif (is_array($child)) {
            $fragments = array_merge($fragments, collectTextFragments($child, $depth + 1, false));
        }
    }

    return $fragments;
}

$parent = dirname(__DIR__);
$root = basename($parent) === 'api' ? dirname($parent) : $parent;
$apiRoot = $root . DIRECTORY_SEPARATOR . 'api';

$candidates = [
    'upload' => $root . DIRECTORY_SEPARATOR . 'upload',
    'uploads' => $root . DIRECTORY_SEPARATOR . 'uploads',
    'api/upload' => $apiRoot . DIRECTORY_SEPARATOR . 'upload',
    'api/uploads' => $apiRoot . DIRECTORY_SEPARATOR . 'uploads',
];

$dirs = uploadDirs($candidates);

if ($action === 'list') {
    $files = [];

    foreach ($dirs as $folderKey => $dir) {
        $entries = @scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($full)) continue;
            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'json') continue;

            $files[] = [
                'token' => $folderKey . '/' . $entry,
                'name' => $entry,
                'folder' => $folderKey,
                'modified_at' => @filemtime($full) ?: 0,
            ];
        }
    }

    usort($files, static function ($a, $b) {
        $timeCompare = ($b['modified_at'] ?? 0) <=> ($a['modified_at'] ?? 0);
        return $timeCompare !== 0 ? $timeCompare : strcmp($a['token'], $b['token']);
    });

    jsonResponse([
        'files' => $files,
        'folders_checked' => array_keys($dirs),
    ]);
}

if ($action === 'read') {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '' || str_contains($token, '..') || str_contains($token, '\\')) {
        jsonResponse(['message' => 'Token file non valido'], 400);
    }

    $baseDir = null;
    $file = '';

    foreach ($dirs as $folderKey => $dir) {
        $prefix = $folderKey . '/';
        if (!str_starts_with($token, $prefix)) continue;

        $candidateFile = substr($token, strlen($prefix));
        if ($candidateFile === '' || str_contains($candidateFile, '/')) {
            jsonResponse(['message' => 'Token file non valido'], 400);
        }

        $file = basename($candidateFile);
        if ($file !== $candidateFile) {
            jsonResponse(['message' => 'Token file non valido'], 400);
        }

        $baseDir = $dir;
        break;
    }

    if ($baseDir === null || $file === '') {
        jsonResponse(['message' => 'Cartella upload non trovata'], 404);
    }

    $filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $file);
    if ($filePath === false || !is_file($filePath)) {
        jsonResponse(['message' => 'File non trovato'], 404);
    }

    if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'json') {
        jsonResponse(['message' => 'File non JSON'], 400);
    }

    $normalizedBase = rtrim(normalizePath($baseDir), '/') . '/';
    $normalizedFile = normalizePath($filePath);
    if (!str_starts_with($normalizedFile, $normalizedBase)) {
        jsonResponse(['message' => 'Percorso file non valido'], 400);
    }

    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        jsonResponse(['message' => 'Impossibile leggere file JSON'], 500);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(['message' => 'JSON non valido'], 400);
    }

    $fragments = collectTextFragments($decoded);
    $unique = [];
    $seen = [];

    foreach ($fragments as $fragment) {
        $fragment = trim((string)$fragment);
        if ($fragment === '' || isset($seen[$fragment])) continue;
        $seen[$fragment] = true;
        $unique[] = $fragment;
    }

    $text = implode("\n\n", $unique);
    if ($text === '') {
        $text = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    jsonResponse([
        'token' => $token,
        'text' => $text,
    ]);
}

jsonResponse(['message' => 'Azione non valida'], 400);
