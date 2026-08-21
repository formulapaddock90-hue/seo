<?php

require __DIR__ . '/bootstrap.php';

$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'];
$configuredMediaDirs = $appConfig['media_dirs'] ?? [];
$mediaDirs = [];

foreach ($configuredMediaDirs as $dir) {
    if (!is_string($dir) || trim($dir) === '') {
        continue;
    }

    if (is_dir($dir)) {
        $mediaDirs[] = $dir;
        continue;
    }

    $localFallback = realpath(__DIR__ . '/../' . basename($dir));
    if ($localFallback !== false && is_dir($localFallback)) {
        $mediaDirs[] = $localFallback;
    }
}

if (empty($mediaDirs)) {
    foreach ([__DIR__ . '/../immagini', __DIR__ . '/../uploads'] as $fallbackDir) {
        if (is_dir($fallbackDir)) {
            $mediaDirs[] = $fallbackDir;
        }
    }
}

$mediaDirs = array_values(array_unique($mediaDirs));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function toWebPath(string $absolutePath): string
{
    $real = realpath($absolutePath);
    $normalized = str_replace('\\', '/', $real !== false ? $real : $absolutePath);
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
    if ($root !== '' && str_starts_with($normalized, $root)) {
        return ltrim(substr($normalized, strlen($root)), '/');
    }
    return basename($normalized);
}

function sanitizeCategory(string $value): string
{
    $value = trim(strtolower($value));
    $value = preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
    return $value;
}

function collectImagesRecursive(string $baseDir, string $baseFolder, array $allowedExt): array
{
    $items = [];
    $categories = [];

    if (!is_dir($baseDir)) {
        return ['images' => [], 'categories' => []];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $entry) {
        /** @var SplFileInfo $entry */
        $path = $entry->getPathname();
        $relative = trim(str_replace('\\', '/', substr($path, strlen($baseDir))), '/');

        if ($entry->isDir()) {
            $categoryKey = $baseFolder . '/' . ($relative === '' ? 'root' : $relative);
            $categories[$categoryKey] = [
                'category' => $relative === '' ? 'root' : $relative,
                'folder' => $baseFolder,
                'has_images' => false,
                'files_count' => 0,
            ];
            continue;
        }

        $ext = strtolower(pathinfo($entry->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }

        $category = dirname($relative);
        if ($category === '.' || $category === '') {
            $category = 'root';
        }

        $token = $baseFolder . '/' . ltrim(str_replace('\\', '/', $relative), '/');
        $webPath = toWebPath($path);

        $items[] = [
            'folder' => $baseFolder,
            'category' => $category,
            'file' => $entry->getFilename(),
            'token' => $token,
            'url' => $webPath,
            'size' => $entry->getSize(),
        ];

        $categoryKey = $baseFolder . '/' . $category;
        if (!isset($categories[$categoryKey])) {
            $categories[$categoryKey] = [
                'category' => $category,
                'folder' => $baseFolder,
                'has_images' => true,
                'files_count' => 1,
            ];
        } else {
            $categories[$categoryKey]['has_images'] = true;
            $categories[$categoryKey]['files_count']++;
        }
    }

    return [
        'images' => $items,
        'categories' => array_values($categories),
    ];
}

if ($method === 'POST') {
    $category = sanitizeCategory((string)($_POST['category'] ?? ''));
    if ($category === '') {
        jsonResponse(['message' => 'Categoria upload mancante'], 400);
    }

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        jsonResponse(['message' => 'File immagine mancante'], 400);
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonResponse(['message' => 'Errore upload file'], 400);
    }

    $sourceName = basename((string)($file['name'] ?? ''));
    $ext = strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        jsonResponse(['message' => 'Estensione non supportata'], 400);
    }

    $baseDir = null;
    foreach ($mediaDirs as $dir) {
        if (is_string($dir) && is_dir($dir) && basename($dir) === 'immagini') {
            $baseDir = $dir;
            break;
        }
    }

    if ($baseDir === null) {
        $baseDir = __DIR__ . '/../immagini';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }
    }

    $categoryDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $category;
    if (!is_dir($categoryDir)) {
        @mkdir($categoryDir, 0775, true);
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($sourceName, PATHINFO_FILENAME)) ?: 'image';
    $targetName = $safeBase . '_' . date('Ymd_His') . '.' . $ext;
    $targetPath = $categoryDir . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
        jsonResponse(['message' => 'Impossibile salvare il file'], 500);
    }

    jsonResponse([
        'ok' => true,
        'token' => 'immagini/' . $category . '/' . $targetName,
        'url' => toWebPath($targetPath),
        'category' => $category,
    ]);
}

$items = [];
$categories = [];

foreach ($mediaDirs as $dir) {
    if (!is_string($dir) || !is_dir($dir)) {
        continue;
    }

    $baseFolder = basename($dir);
    $collected = collectImagesRecursive($dir, $baseFolder, $allowedExt);
    $items = array_merge($items, $collected['images']);
    $categories = array_merge($categories, $collected['categories']);
}

usort($items, static function ($a, $b) {
    return strcmp($a['token'], $b['token']);
});

usort($categories, static function ($a, $b) {
    $x = $a['folder'] . '/' . $a['category'];
    $y = $b['folder'] . '/' . $b['category'];
    return strcmp($x, $y);
});

$structure = [];
foreach ($categories as $cat) {
    $key = $cat['folder'] . '/' . $cat['category'];
    $structure[$key] = [
        'folder' => $cat['folder'],
        'category' => $cat['category'],
        'has_images' => $cat['has_images'],
        'files_count' => $cat['files_count'],
        'files' => [],
    ];
}

foreach ($items as $img) {
    $key = $img['folder'] . '/' . $img['category'];
    if (!isset($structure[$key])) {
        $structure[$key] = [
            'folder' => $img['folder'],
            'category' => $img['category'],
            'has_images' => true,
            'files_count' => 0,
            'files' => [],
        ];
    }

    $structure[$key]['files'][] = [
        'file' => $img['file'],
        'token' => $img['token'],
        'url' => $img['url'],
    ];
    $structure[$key]['files_count']++;
    $structure[$key]['has_images'] = true;
}

jsonResponse([
    'images' => $items,
    'categories' => array_values($structure),
]);
