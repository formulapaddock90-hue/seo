<?php

require __DIR__ . '/bootstrap.php';

$storageDir = realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
$postgaraDir = rtrim($storageDir, '/\\') . '/postgara';
$dataFile = $postgaraDir . '/team-data.json';
$uploadDir = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$uploadDir = rtrim($uploadDir, '/\\') . '/postgara';

if (!is_dir($postgaraDir)) {
    @mkdir($postgaraDir, 0775, true);
}
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

function loadPostGaraData(string $dataFile): array
{
    if (!is_file($dataFile)) {
        return [];
    }

    $raw = @file_get_contents($dataFile);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function savePostGaraData(string $dataFile, array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return @file_put_contents($dataFile, $json, LOCK_EX) !== false;
}

function normalizeTeamName(string $name): string
{
    $name = trim($name);
    return $name;
}

function slugifyFileName(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'team';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    jsonResponse([
        'ok' => true,
        'data' => loadPostGaraData($dataFile),
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_FILES['image'])) {
    $team = normalizeTeamName((string)($_POST['team'] ?? ''));
    if ($team === '') {
        jsonResponse(['ok' => false, 'message' => 'Team mancante'], 400);
    }

    $file = $_FILES['image'];
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'message' => 'Errore upload immagine'], 400);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $original = basename((string)($file['name'] ?? 'image.jpg'));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        jsonResponse(['ok' => false, 'message' => 'Formato immagine non supportato'], 400);
    }

    $safeTeam = slugifyFileName($team);
    $targetName = $safeTeam . '_' . date('Ymd_His') . '_' . substr(md5((string)mt_rand()), 0, 6) . '.' . $ext;
    $targetPath = rtrim($uploadDir, '/\\') . '/' . $targetName;

    if (!@move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
        jsonResponse(['ok' => false, 'message' => 'Impossibile salvare immagine'], 500);
    }

    $publicUrl = rtrim(BASE_PATH, '/') . '/uploads/postgara/' . $targetName;

    $data = loadPostGaraData($dataFile);
    $data[$team] = is_array($data[$team] ?? null) ? $data[$team] : [];
    $data[$team]['image'] = $publicUrl;

    if (!savePostGaraData($dataFile, $data)) {
        jsonResponse(['ok' => false, 'message' => 'Errore salvataggio dati'], 500);
    }

    jsonResponse([
        'ok' => true,
        'team' => $team,
        'image' => $publicUrl,
        'data' => $data,
    ]);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    jsonResponse(['ok' => false, 'message' => 'Payload non valido'], 400);
}

$action = (string)($payload['action'] ?? '');
$data = loadPostGaraData($dataFile);

if ($action === 'clear_comments') {
    foreach ($data as $teamName => $teamData) {
        if (!is_array($teamData)) {
            $teamData = [];
        }
        $teamData['comment'] = '';
        $teamData['image'] = '';
        $data[$teamName] = $teamData;
    }

    if (!savePostGaraData($dataFile, $data)) {
        jsonResponse(['ok' => false, 'message' => 'Errore salvataggio dati'], 500);
    }

    jsonResponse([
        'ok' => true,
        'data' => $data,
    ]);
}

$team = normalizeTeamName((string)($payload['team'] ?? ''));

if ($team === '') {
    jsonResponse(['ok' => false, 'message' => 'Team mancante'], 400);
}

$data[$team] = is_array($data[$team] ?? null) ? $data[$team] : [];

if ($action === 'save_comment') {
    $comment = trim((string)($payload['comment'] ?? ''));
    $data[$team]['comment'] = $comment;
} elseif ($action === 'delete_comment') {
    $data[$team]['comment'] = '';
} elseif ($action === 'remove_image') {
    $data[$team]['image'] = '';
} elseif ($action === 'set_image_url') {
    $image = trim((string)($payload['image'] ?? ''));
    if ($image === '') {
        jsonResponse(['ok' => false, 'message' => 'URL immagine mancante'], 400);
    }

    $normalized = str_replace('\\', '/', $image);
    $baseUploads = rtrim(BASE_PATH, '/') . '/uploads/';
    $isUploadsPath = str_starts_with($normalized, 'uploads/') || str_starts_with($normalized, $baseUploads);

    if (!$isUploadsPath) {
        jsonResponse(['ok' => false, 'message' => 'Immagine non valida: usare solo file da uploads'], 400);
    }

    if (str_starts_with($normalized, 'uploads/')) {
        $normalized = rtrim(BASE_PATH, '/') . '/' . ltrim($normalized, '/');
    }

    $data[$team]['image'] = $normalized;
} else {
    jsonResponse(['ok' => false, 'message' => 'Azione non supportata'], 400);
}

if (!savePostGaraData($dataFile, $data)) {
    jsonResponse(['ok' => false, 'message' => 'Errore salvataggio dati'], 500);
}

jsonResponse([
    'ok' => true,
    'team' => $team,
    'image' => $data[$team]['image'] ?? '',
    'data' => $data,
]);
