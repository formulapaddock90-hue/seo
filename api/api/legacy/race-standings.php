<?php
/**
 * Endpoint API per caricare risultati completi di una gara F1
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Parametri
$raceYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$raceRound = isset($_GET['round']) ? (int)$_GET['round'] : null;

/**
 * Funzione per caricare dati da OpenF1 API
 */
function fetchOpenF1Data(string $endpoint, array $params = []): array
{
    $url = 'https://api.openf1.org/v1' . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
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

try {
    // Carica gare dell'anno
    $races = fetchOpenF1Data('/races', ['year' => $raceYear]);

    if (empty($races)) {
        http_response_code(404);
        echo json_encode(['error' => "Nessuna gara trovata per l'anno $raceYear"]);
        exit;
    }

    // Se round non specificato, prendi l'ultima gara completata
    if ($raceRound === null) {
        $race = end($races);
    } else {
        $race = null;
        foreach ($races as $r) {
            if ($r['round'] == $raceRound) {
                $race = $r;
                break;
            }
        }
        if (!$race) {
            http_response_code(404);
            echo json_encode(['error' => "Gara round $raceRound non trovata"]);
            exit;
        }
    }

    $raceKey = $race['session_key'] ?? $race['key'] ?? null;
    if (!$raceKey) {
        http_response_code(400);
        echo json_encode(['error' => 'Race key non trovato']);
        exit;
    }

    // Carica risultati della gara
    $results = fetchOpenF1Data('/results', ['session_key' => $raceKey]);

    if (empty($results)) {
        // Se nessun risultato, genera dati demo
        $results = [];
    }

    // Formatta risultati
    $formatted = [];
    foreach (array_slice($results, 0, 20) as $idx => $result) {
        $formatted[] = [
            'position' => $result['position'] ?? ($idx + 1),
            'driver_number' => $result['driver_number'] ?? '-',
            'first_name' => $result['first_name'] ?? 'Unknown',
            'last_name' => $result['last_name'] ?? '',
            'team_name' => $result['team_name'] ?? 'Unknown',
            'points' => $result['points'] ?? 0,
            'status' => $result['status'] ?? 'Unknown',
            'time_ms' => $result['time_ms'] ?? null
        ];
    }

    echo json_encode([
        'success' => true,
        'race' => [
            'year' => $race['year'] ?? $raceYear,
            'round' => $race['round'] ?? $raceRound,
            'name' => $race['name'] ?? 'Race',
            'date' => $race['date_start'] ?? ''
        ],
        'results' => $formatted
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore nell\'elaborazione: ' . $e->getMessage()
    ]);
}
