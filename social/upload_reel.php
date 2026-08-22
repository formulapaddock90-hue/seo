<?php
/**
 * upload_reel.php — ricezione a chunk del Reel creato nel browser.
 * Salva SOLO il Reel della sessione social corrente e non accetta upload anonimi.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
error_reporting(E_ALL);

function reelJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reelJson(['ok' => false, 'error' => 'Metodo non consentito.'], 405);
}

$sessionSlug = trim((string)($_SESSION['last_social_slug'] ?? ''));
$sessionSource = trim((string)($_SESSION['last_social_source_url'] ?? ''));
$sessionToken = (string)($_SESSION['reel_upload_csrf'] ?? '');
$token = (string)($_POST['csrf'] ?? '');

if ($sessionSlug === '' || empty($_SESSION['last_social_content'])) {
    reelJson(['ok' => false, 'error' => 'Sessione social non valida. Genera prima la notizia dal Social Hub.'], 403);
}
if ($sessionToken === '' || $token === '' || !hash_equals($sessionToken, $token)) {
    reelJson(['ok' => false, 'error' => 'Token upload Reel non valido.'], 403);
}

$uploadId = strtolower(trim((string)($_POST['upload_id'] ?? '')));
$index = filter_input(INPUT_POST, 'index', FILTER_VALIDATE_INT);
$total = filter_input(INPUT_POST, 'total', FILTER_VALIDATE_INT);

if (!preg_match('/^[a-f0-9]{16,64}$/', $uploadId)) {
    reelJson(['ok' => false, 'error' => 'ID upload non valido.'], 400);
}
if ($index === false || $index === null || $total === false || $total === null || $index < 0 || $total < 1 || $index >= $total || $total > 200) {
    reelJson(['ok' => false, 'error' => 'Sequenza chunk non valida.'], 400);
}
if (empty($_FILES['chunk']) || !isset($_FILES['chunk']['tmp_name'])) {
    reelJson(['ok' => false, 'error' => 'Chunk mancante.'], 400);
}
if ((int)$_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    reelJson(['ok' => false, 'error' => 'Errore upload chunk: ' . (int)$_FILES['chunk']['error']], 400);
}

$chunkSize = (int)($_FILES['chunk']['size'] ?? 0);
if ($chunkSize <= 0 || $chunkSize > 2 * 1024 * 1024) {
    reelJson(['ok' => false, 'error' => 'Dimensione chunk non consentita.'], 400);
}

$config = require __DIR__ . '/config.php';
$reelsDir = $config['output_reels_dir'] ?? (__DIR__ . '/output/reels');
if (!is_dir($reelsDir) && !@mkdir($reelsDir, 0775, true) && !is_dir($reelsDir)) {
    reelJson(['ok' => false, 'error' => 'Impossibile creare la cartella Reel.'], 500);
}
if (!is_writable($reelsDir)) {
    reelJson(['ok' => false, 'error' => 'Cartella Reel non scrivibile.'], 500);
}

$safeSession = preg_replace('/[^a-zA-Z0-9_-]/', '_', session_id());
$chunkDir = $reelsDir . '/.chunks_' . $safeSession . '_' . $uploadId;
if (!is_dir($chunkDir) && !@mkdir($chunkDir, 0775, true) && !is_dir($chunkDir)) {
    reelJson(['ok' => false, 'error' => 'Impossibile creare la cartella temporanea.'], 500);
}

$partPath = $chunkDir . '/part_' . str_pad((string)$index, 4, '0', STR_PAD_LEFT);
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $partPath)) {
    reelJson(['ok' => false, 'error' => 'Impossibile salvare il chunk.'], 500);
}

// Non e' l'ultimo pezzo: conferma e termina.
if ($index < $total - 1) {
    reelJson(['ok' => true, 'complete' => false, 'received' => $index + 1, 'total' => $total]);
}

// Verifica che tutti i chunk siano arrivati.
for ($i = 0; $i < $total; $i++) {
    $expected = $chunkDir . '/part_' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
    if (!is_file($expected)) {
        reelJson(['ok' => false, 'error' => 'Upload incompleto: manca il chunk ' . $i . '.'], 409);
    }
}

$safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionSlug);
$finalName = $safeSlug . '_reel.mp4';
$finalPath = $reelsDir . '/' . $finalName;
$tempFinal = $reelsDir . '/.' . $safeSlug . '_' . $uploadId . '.tmp';
$out = @fopen($tempFinal, 'wb');
if (!$out) {
    reelJson(['ok' => false, 'error' => 'Impossibile creare il file Reel finale.'], 500);
}

$totalBytes = 0;
try {
    for ($i = 0; $i < $total; $i++) {
        $part = $chunkDir . '/part_' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
        $in = fopen($part, 'rb');
        if (!$in) throw new RuntimeException('Errore lettura chunk ' . $i);
        $copied = stream_copy_to_stream($in, $out);
        fclose($in);
        if ($copied === false) throw new RuntimeException('Errore composizione Reel.');
        $totalBytes += (int)$copied;
        if ($totalBytes > 100 * 1024 * 1024) throw new RuntimeException('Reel troppo grande (massimo 100 MB).');
    }
} catch (Throwable $e) {
    fclose($out);
    @unlink($tempFinal);
    reelJson(['ok' => false, 'error' => $e->getMessage()], 500);
}
fclose($out);

// Controllo minimo MP4: deve contenere un box ftyp nell'header.
$head = (string)@file_get_contents($tempFinal, false, null, 0, 128);
if ($totalBytes < 10000 || strpos($head, 'ftyp') === false) {
    @unlink($tempFinal);
    reelJson(['ok' => false, 'error' => 'Il file ricevuto non sembra un MP4 valido.'], 400);
}

if (is_file($finalPath)) @unlink($finalPath);
if (!@rename($tempFinal, $finalPath)) {
    @unlink($tempFinal);
    reelJson(['ok' => false, 'error' => 'Impossibile finalizzare il Reel.'], 500);
}

// Pulizia chunk.
for ($i = 0; $i < $total; $i++) {
    @unlink($chunkDir . '/part_' . str_pad((string)$i, 4, '0', STR_PAD_LEFT));
}
@rmdir($chunkDir);

$publicUrl = 'output/reels/' . rawurlencode($finalName);
$_SESSION['last_social_reel'] = $finalPath;
$_SESSION['last_social_reel_url'] = $publicUrl;
$_SESSION['last_social_reel_source_url'] = $sessionSource;
$_SESSION['last_social_reel_saved_at'] = time();

$drive = null;
$driveWarning = null;
try {
    require_once __DIR__ . '/includes/google_service.php';
    $drive = uploadFileToDrive($finalPath, 'video/mp4', $config);
    $_SESSION['last_social_reel_drive'] = $drive;
} catch (Throwable $e) {
    $driveWarning = $e->getMessage();
    $_SESSION['last_social_reel_drive'] = null;
}

reelJson([
    'ok' => true,
    'complete' => true,
    'path' => $finalPath,
    'url' => $publicUrl,
    'name' => $finalName,
    'bytes' => $totalBytes,
    'drive' => $drive,
    'drive_warning' => $driveWarning,
]);
