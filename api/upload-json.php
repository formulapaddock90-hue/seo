<?php

require __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? 'list';
$root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
$candidates = [
    $root . DIRECTORY_SEPARATOR . 'upload',
    $root . DIRECTORY_SEPARATOR . 'uploads',
];

function uploadDirs(array $candidates): array
{
    $dirs = [];
    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            $real = realpath($dir);
            if ($real !== false) {
                $dirs[] = $real;
            }
        }
    }
    return array_values(array_unique($dirs));
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

$dirs = uploadDirs($candidates);

if ($action === 'list') {
    $files = [];

    foreach ($dirs as $dir) {
        $entries = @scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($full)) continue;
            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'json') continue;

            $token = basename($dir) . '/' . $entry;
            $files[] = [
                'token' => $token,
                'name' => $entry,
                'folder' => basename($dir),
            ];
        }
    }

    usort($files, static function ($a, $b) {
        return strcmp($a['token'], $b['token']);
    });

    jsonResponse(['files' => $files]);
}

if ($action === 'read') {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '' || str_contains($token, '..') || str_contains($token, '\\')) {
        jsonResponse(['message' => 'Token file non valido'], 400);
    }

    $parts = explode('/', $token, 2);
    if (count($parts) !== 2) {
        jsonResponse(['message' => 'Token file non valido'], 400);
    }

    [$folder, $file] = $parts;
    $file = basename($file);

    $baseDir = null;
    foreach ($dirs as $dir) {
        if (basename($dir) === $folder) {
            $baseDir = $dir;
            break;
        }
    }

    if ($baseDir === null) {
        jsonResponse(['message' => 'Cartella upload non trovata'], 404);
    }

    $filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $file);
    if ($filePath === false || !is_file($filePath)) {
        jsonResponse(['message' => 'File non trovato'], 404);
    }

    if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'json') {
        jsonResponse(['message' => 'File non JSON'], 400);
    }

    if (!str_starts_with(normalizePath($filePath), normalizePath($baseDir))) {
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

    $textKeys = ['raw_text', 'text', 'content', 'article', 'body', 'message'];
    $text = '';
    foreach ($textKeys as $key) {
        if (isset($decoded[$key]) && is_string($decoded[$key]) && trim($decoded[$key]) !== '') {
            $text = trim($decoded[$key]);
            break;
        }
    }

    if ($text === '') {
        $text = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    jsonResponse([
        'token' => $token,
        'text' => $text,
    ]);
}

jsonResponse(['message' => 'Azione non valida'], 400);
