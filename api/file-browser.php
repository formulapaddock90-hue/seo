<?php

require __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';
$root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
$exclude = ['.git', '.vs', 'vendor', 'node_modules'];
$textExtensions = ['txt', 'md', 'json', 'php', 'js', 'css', 'html', 'xml', 'csv', 'log', 'yml', 'yaml', 'ini'];
$allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

function toWebPath(string $absolutePath, string $root): string
{
    $normalized = str_replace('\\', '/', $absolutePath);
    $rootNorm = str_replace('\\', '/', $root);
    if (str_starts_with($normalized, $rootNorm)) {
        return ltrim(substr($normalized, strlen($rootNorm)), '/');
    }
    return basename($normalized);
}

function sanitizeSegment(string $value): string
{
    $v = trim($value);
    if ($v === '' || str_contains($v, '..') || str_contains($v, '/') || str_contains($v, '\\')) {
        return '';
    }
    return $v;
}

/**
 * Sanitizza un percorso relativo composto da più segmenti (es. uploads/a/b).
 * Ogni segmento viene validato tramite sanitizeSegment.
 */
function sanitizeFolderPath(string $value): string
{
    $v = trim($value);
    if ($v === '' || str_contains($v, '..')) {
        return '';
    }

    $parts = preg_split('/[\/\\\\]+/', $v) ?: [];
    $safeParts = [];

    foreach ($parts as $part) {
        $safe = sanitizeSegment(trim((string)$part));
        if ($safe === '') {
            continue;
        }
        $safeParts[] = $safe;
    }

    if (!count($safeParts)) {
        return '';
    }

    return implode(DIRECTORY_SEPARATOR, $safeParts);
}

if ($action === 'folders') {
    $folders = [];

    $entries = @scandir($root) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (in_array($entry, $exclude, true)) continue;
        if (str_starts_with($entry, '.')) continue;

        $path = $root . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($path)) continue;

        $folders[] = ['name' => $entry];
    }

    usort($folders, static fn($a, $b) => strcmp($a['name'], $b['name']));
    jsonResponse(['folders' => $folders]);
}

if ($action === 'subfolders') {
    $parent = sanitizeSegment((string)($_GET['parent'] ?? ''));
    if ($parent !== 'uploads') {
        jsonResponse(['message' => 'Solo la cartella uploads supporta sottocartelle'], 400);
    }

    $path = realpath($root . DIRECTORY_SEPARATOR . $parent);
    if ($path === false || !is_dir($path)) {
        jsonResponse(['message' => 'Cartella non trovata'], 404);
    }

    $subfolders = [];
    $entries = @scandir($path) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (str_starts_with($entry, '.')) continue;
        $full = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($full)) {
            $subfolders[] = ['name' => $entry];
        }
    }

    usort($subfolders, static fn($a, $b) => strcmp($a['name'], $b['name']));
    jsonResponse(['subfolders' => $subfolders]);
}

if ($action === 'files') {
    $folder = sanitizeFolderPath((string)($_GET['folder'] ?? ''));
    if ($folder === '') {
        jsonResponse(['message' => 'Cartella non valida'], 400);
    }

    $path = realpath($root . DIRECTORY_SEPARATOR . $folder);
    if ($path === false || !is_dir($path) || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root))) {
        jsonResponse(['message' => 'Cartella non trovata'], 404);
    }

    $files = [];
    $entries = @scandir($path) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_file($full)) {
            $files[] = $entry;
        }
    }

    sort($files);
    jsonResponse(['files' => $files]);
}

if ($action === 'content') {
    $folder = sanitizeFolderPath((string)($_GET['folder'] ?? ''));
    $file = basename((string)($_GET['file'] ?? ''));

    if ($folder === '' || $file === '') {
        jsonResponse(['message' => 'Parametri non validi'], 400);
    }

    $folderPath = realpath($root . DIRECTORY_SEPARATOR . $folder);
    if ($folderPath === false || !is_dir($folderPath)) {
        jsonResponse(['message' => 'Cartella non trovata'], 404);
    }

    $filePath = realpath($folderPath . DIRECTORY_SEPARATOR . $file);
    if ($filePath === false || !is_file($filePath)) {
        jsonResponse(['message' => 'File non trovato'], 404);
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $size = @filesize($filePath) ?: 0;
    if ($size > 1024 * 1024) {
        jsonResponse(['content' => 'File troppo grande per anteprima (>1MB).']);
    }

    if (!in_array($ext, $textExtensions, true)) {
        $mime = @mime_content_type($filePath) ?: '';
        if (!str_starts_with($mime, 'text/')) {
            jsonResponse(['content' => 'File binario/non testuale: anteprima non disponibile.']);
        }
    }

    $content = @file_get_contents($filePath);
    if ($content === false) {
        jsonResponse(['message' => 'Impossibile leggere il file'], 500);
    }

    jsonResponse(['content' => $content]);
}

if ($action === 'uploads-browse') {
    $subfolder = sanitizeFolderPath((string)($_GET['subfolder'] ?? ''));

    $uploadsRoot = realpath($root . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploadsRoot === false || !is_dir($uploadsRoot)) {
        jsonResponse(['subfolder' => '', 'subfolders' => [], 'files' => []]);
    }

    $browsePath = $subfolder !== ''
        ? realpath($uploadsRoot . DIRECTORY_SEPARATOR . $subfolder)
        : $uploadsRoot;

    if (
        $browsePath === false
        || !is_dir($browsePath)
        || !str_starts_with(str_replace('\\', '/', $browsePath), str_replace('\\', '/', $uploadsRoot))
    ) {
        jsonResponse(['message' => 'Percorso non valido'], 404);
    }

    $subfolders = [];
    $files      = [];
    $entries    = @scandir($browsePath) ?: [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (str_starts_with($entry, '.')) continue;
        $full = $browsePath . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($full)) {
            $subfolders[] = ['name' => $entry];
        } elseif (is_file($full)) {
            $files[] = $entry;
        }
    }

    usort($subfolders, static fn($a, $b) => strcmp($a['name'], $b['name']));
    sort($files);

    jsonResponse([
        'subfolder'  => $subfolder,
        'subfolders' => $subfolders,
        'files'      => $files,
    ]);
}

if ($action === 'create-folder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $parentFolder = sanitizeFolderPath((string)($_POST['parent_folder'] ?? ''));
    $folderName = sanitizeSegment((string)($_POST['folder_name'] ?? ''));

    if ($parentFolder === '' || $folderName === '') {
        jsonResponse(['message' => 'Parametri non validi'], 400);
    }

    $parentFolderNorm = str_replace('\\', '/', $parentFolder);
    if (!str_starts_with($parentFolderNorm, 'uploads')) {
        jsonResponse(['message' => 'Creazione consentita solo dentro uploads'], 400);
    }

    $uploadsRoot = realpath($root . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploadsRoot === false || !is_dir($uploadsRoot)) {
        jsonResponse(['message' => 'Cartella uploads non disponibile'], 500);
    }

    $parentPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $parentFolder);
    $resolvedParent = realpath($parentPath);

    if ($resolvedParent === false || !is_dir($resolvedParent)) {
        jsonResponse(['message' => 'Cartella padre non trovata'], 404);
    }

    if (!str_starts_with(str_replace('\\', '/', $resolvedParent), str_replace('\\', '/', $uploadsRoot))) {
        jsonResponse(['message' => 'Percorso non valido'], 400);
    }

    $newFolderPath = $resolvedParent . DIRECTORY_SEPARATOR . $folderName;

    if (is_dir($newFolderPath)) {
        jsonResponse(['message' => 'La cartella esiste già'], 409);
    }

    if (!@mkdir($newFolderPath, 0775, false)) {
        jsonResponse(['message' => 'Impossibile creare la cartella'], 500);
    }

    jsonResponse(['ok' => true, 'message' => 'Cartella creata con successo']);
}

if ($action === 'upload-images' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder = sanitizeFolderPath((string)($_POST['folder'] ?? ''));
    if ($folder === '') {
        jsonResponse(['message' => 'Cartella non valida'], 400);
    }

    $folderNorm = str_replace('\\', '/', $folder);
    if (!str_starts_with($folderNorm, 'uploads')) {
        jsonResponse(['message' => 'Upload consentito solo dentro uploads'], 400);
    }

    $uploadsRoot = realpath($root . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploadsRoot === false || !is_dir($uploadsRoot)) {
        jsonResponse(['message' => 'Cartella uploads non disponibile'], 500);
    }

    $targetDir = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $folderNorm);
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
        jsonResponse(['message' => 'Impossibile creare la cartella di destinazione'], 500);
    }

    $resolvedTarget = realpath($targetDir);
    if ($resolvedTarget === false || !str_starts_with(str_replace('\\', '/', $resolvedTarget), str_replace('\\', '/', $uploadsRoot))) {
        jsonResponse(['message' => 'Percorso di destinazione non valido'], 400);
    }

    if (!isset($_FILES['images'])) {
        jsonResponse(['message' => 'Nessun file ricevuto'], 400);
    }

    $names = $_FILES['images']['name'] ?? [];
    $tmpNames = $_FILES['images']['tmp_name'] ?? [];
    $errors = $_FILES['images']['error'] ?? [];

    if (!is_array($names) || !count($names)) {
        jsonResponse(['message' => 'Nessun file valido'], 400);
    }

    $uploaded = [];
    $skipped = [];

    foreach ($names as $i => $name) {
        $err = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $skipped[] = ['name' => (string)$name, 'reason' => 'upload_error'];
            continue;
        }

        $tmp = (string)($tmpNames[$i] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $skipped[] = ['name' => (string)$name, 'reason' => 'invalid_tmp'];
            continue;
        }

        $original = basename((string)$name);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedImageExtensions, true)) {
            $skipped[] = ['name' => $original, 'reason' => 'unsupported_extension'];
            continue;
        }

        $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($original, PATHINFO_FILENAME)) ?: 'image';
        $targetName = $base . '_' . date('Ymd_His') . '_' . $i . '.' . $ext;
        $targetPath = $resolvedTarget . DIRECTORY_SEPARATOR . $targetName;

        if (!move_uploaded_file($tmp, $targetPath)) {
            $skipped[] = ['name' => $original, 'reason' => 'move_failed'];
            continue;
        }

        $uploaded[] = [
            'name' => $targetName,
            'url' => toWebPath($targetPath, $root),
            'folder' => str_replace('\\', '/', trim(substr(str_replace('\\', '/', $resolvedTarget), strlen(str_replace('\\', '/', $root))), '/')),
        ];
    }

    jsonResponse([
        'ok' => count($uploaded) > 0,
        'uploaded' => $uploaded,
        'skipped' => $skipped,
    ]);
}

jsonResponse(['message' => 'Azione non valida'], 400);
