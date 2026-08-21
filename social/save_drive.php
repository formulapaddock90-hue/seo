<?php
/**
 * save_drive.php — Salva i Reel registrati direttamente nella cartella Google Drive "creatività"
 * Folder ID: 1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/includes/google_service.php';
$config = require __DIR__ . '/config.php';

try {
    $videoData = null;
    $filename = 'FormulaPaddock_Reel_' . date('Ymd_His') . '.mp4';

    // Leggi sia multipart file upload che raw POST stream
    if (!empty($_FILES['video']['tmp_name'])) {
        $tmpPath = $_FILES['video']['tmp_name'];
        $mime = $_FILES['video']['type'] ?? 'video/mp4';
    } else {
        $rawBytes = file_get_contents('php://input');
        if (empty($rawBytes) || strlen($rawBytes) < 500) {
            throw new Exception("Nessun dato video valido ricevuto.");
        }
        $tmpPath = $config['output_reels_dir'] . '/temp_upload_' . time() . '.mp4';
        if (!is_dir(dirname($tmpPath))) {
            mkdir(dirname($tmpPath), 0777, true);
        }
        file_put_contents($tmpPath, $rawBytes);
        $mime = 'video/mp4';
    }

    // Carica direttamente nella cartella Drive "creatività" (1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K)
    $config['google_drive_folder_id'] = '1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K';
    $driveRes = uploadFileToDrive($tmpPath, $mime, $config);

    // Pulisci file temporaneo locale
    if (file_exists($tmpPath)) {
        @unlink($tmpPath);
    }

    echo json_encode([
        'status'  => 'SUCCESS',
        'message' => 'Reel salvato con successo su Google Drive nella cartella creatività!',
        'drive'   => [
            'file_id'   => $driveRes['file_id'] ?? '',
            'view_link' => $driveRes['view_link'] ?? '',
            'folder_id' => '1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K'
        ]
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_SLASHES);
}
