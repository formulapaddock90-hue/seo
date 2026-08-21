<?php

require __DIR__ . '/bootstrap.php';

$contextFile = __DIR__ . '/../storage/live-social-context.json';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function normalizeLiveRows(array $rows): array
{
    $rows = array_slice(array_values($rows), 0, 30);
    $cleanRows = array_map(static function ($row): array {
        if (!is_array($row)) return [];
        return [
            'position' => (string)($row['position'] ?? ''),
            'number' => (string)($row['number'] ?? ''),
            'driver_name' => (string)($row['driver_name'] ?? ''),
            'team_name' => (string)($row['team_name'] ?? ''),
            'time' => (string)($row['time'] ?? ($row['gap'] ?? '')),
            'gap' => (string)($row['gap'] ?? ''),
            'best_lap' => (string)($row['best_lap'] ?? ''),
            'last_lap' => (string)($row['last_lap'] ?? ''),
            'total_laps' => (string)($row['total_laps'] ?? ''),
        ];
    }, $rows);

    return array_values(array_filter($cleanRows, static fn($r) => !empty($r['driver_name'])));
}

function loadLatestSessionRowsFromServer(): array
{
    $url = 'https://www.formulapaddock.it/seo/api/session-results-gdrive.php';
    $ctx = stream_context_create([
        'http' => ['timeout' => 12, 'ignore_errors' => true],
        'https' => ['timeout' => 12, 'verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['success']) || !is_array($data['rows'] ?? null)) return [];
    return normalizeLiveRows($data['rows']);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $live = !empty($input['live']);

    if (!$live) {
        if (is_file($contextFile)) @unlink($contextFile);
        jsonResponse(['ok' => true, 'live' => false]);
    }

    $rows = isset($input['rows']) && is_array($input['rows']) ? normalizeLiveRows($input['rows']) : [];
    if (!$rows) {
        $rows = loadLatestSessionRowsFromServer();
    }

    if (!is_dir(dirname($contextFile))) {
        @mkdir(dirname($contextFile), 0775, true);
    }

    $payload = [
        'live' => true,
        'session_name' => trim((string)($input['session_name'] ?? 'Sessione Live')),
        'meeting_name' => trim((string)($input['meeting_name'] ?? '')),
        'saved_at' => time(),
        'rows' => $rows,
    ];

    if (file_put_contents($contextFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        jsonResponse(['ok' => false, 'message' => 'Impossibile salvare il contesto Live.'], 500);
    }

    jsonResponse(['ok' => true, 'live' => true, 'rows' => count($rows)]);
}

if ($method === 'GET') {
    if (!is_file($contextFile)) {
        jsonResponse(['ok' => true, 'live' => false]);
    }
    $data = json_decode((string)file_get_contents($contextFile), true) ?: [];
    jsonResponse(['ok' => true] + $data);
}

jsonResponse(['ok' => false, 'message' => 'Metodo non supportato'], 405);
