<?php
/**
 * Helper per ottenere la classifica finale da OpenF1 API
 * Endpoint: api/openf1-final-standings.php?session_key=XXXX
 */

function get_race_results_from_openf1($session_key) {
    $url = "https://api.openf1.org/v1/race_results?session_key=$session_key";

    try {
        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0'
            ]
        ]));

        if ($response === false) {
            return ['ok' => false, 'error' => 'Impossibile contattare OpenF1 API'];
        }

        $data = json_decode($response, true);
        if (!$data) {
            return ['ok' => false, 'error' => 'Risposta OpenF1 non valida'];
        }

        // Trasforma dati OpenF1 nel formato atteso
        $standings = array_map(function ($result, $index) {
            return [
                'Posizione' => $result['position'] ?? $index + 1,
                'Numero Gara' => $result['driver_number'] ?? null,
                'Pilota' => $result['driver_name'] ?? 'N/D',
                'Team' => $result['team_name'] ?? 'N/D',
                'Best Lap' => $result['best_lap_time'] ?? '-',
                'Ultimo Giro' => $result['last_lap_time'] ?? '-',
                'Giri' => $result['laps_completed'] ?? 0,
                'Gap' => $result['time_gap'] ?? ''
            ];
        }, $data, array_keys($data));

        return ['ok' => true, 'data' => $standings];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// Route handler
$action = $_GET['action'] ?? null;

if ($action === 'get_results') {
    $session_key = $_GET['session_key'] ?? null;
    if (!$session_key) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'session_key mancante']);
        exit;
    }

    $result = get_race_results_from_openf1($session_key);
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Uso: ?action=get_results&session_key=XXXX']);
}
