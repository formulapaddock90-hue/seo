<?php
/**
 * Endpoint API per caricare lap_duration di un pilota da OpenF1
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

$driverNumber = isset($_GET['driver']) ? (int)$_GET['driver'] : 0;
$sessionKey = trim((string)($_GET['session'] ?? ''));

if ($driverNumber <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro driver obbligatorio']);
    exit;
}

function fetchOpenF1Data(string $endpoint, array $params = []): array
{
    $url = 'https://api.openf1.org/v1' . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => 'User-Agent: F1-Content-Hub/1.0'
        ]
    ]);

    try {
        $response = @file_get_contents($url, false, $ctx);
        if ($response !== false) {
            return json_decode($response, true) ?? [];
        }
    } catch (Exception $e) {
        // Ignora errori
    }

    return [];
}

function extractLapTimes(array $laps): array
{
    $lapTimes = [];

    foreach ($laps as $lap) {
        $lapDuration = $lap['lap_duration'] ?? null;
        if ($lapDuration === null || !is_numeric($lapDuration)) {
            continue;
        }

        $lapTimes[] = round((float)$lapDuration, 3);
    }

    return $lapTimes;
}

try {
    $params = [
        'driver_number' => $driverNumber,
        'session_key' => $sessionKey !== '' ? $sessionKey : 'latest'
    ];

    $laps = fetchOpenF1Data('/laps', $params);

    if (empty($laps) && $params['session_key'] !== 'latest') {
        $laps = fetchOpenF1Data('/laps', [
            'driver_number' => $driverNumber,
            'session_key' => 'latest'
        ]);
    }

    $lapTimes = extractLapTimes($laps);

    echo json_encode([
        'success' => true,
        'driver_number' => $driverNumber,
        'lap_times' => array_slice($lapTimes, 0, 60)
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore nell\'elaborazione: ' . $e->getMessage()
    ]);
}
