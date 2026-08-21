<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../modules/foto/function.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['message' => 'Metodo non supportato'], 405);
}

$cacheDir = __DIR__ . '/../uploads/photo-wall';
if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
    jsonResponse(['message' => 'Impossibile creare la cartella uploads/photo-wall'], 500);
}

$sources = getSources();
$metrics = newPhotoMetrics();
$items = buildPhotoItems($sources, $cacheDir, $metrics);

jsonResponse([
    'ok' => true,
    'sources' => $sources,
    'items' => count($items),
    'target_folder' => 'uploads/photo-wall',
    'metrics' => $metrics,
]);
