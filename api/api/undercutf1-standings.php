<?php
/**
 * Proxy API per UndercutF1
 * Chiama l'API locale di UndercutF1 (porta 61937) e trasforma i dati
 *
 * Endpoint: api/undercutf1-standings.php?action=fetch_standings
 */

// Configurazione UndercutF1 API
define('UNDERCUTF1_HOST', 'localhost');
define('UNDERCUTF1_PORT', 61937);
define('UNDERCUTF1_BASE_URL', "http://" . UNDERCUTF1_HOST . ":" . UNDERCUTF1_PORT);

/**
 * Recupera la chiave API da UndercutF1
 */
function get_undercutf1_api_key() {
    try {
        $response = file_get_contents(UNDERCUTF1_BASE_URL . "/api/info", false, stream_context_create([
            'http' => ['timeout' => 5, 'user_agent' => 'Mozilla/5.0']
        ]));

        if ($response === false) return null;

        $data = json_decode($response, true);
        return $data['apiKey'] ?? null;
    } catch (Exception $e) {
        error_log('❌ Errore recupero API key UndercutF1: ' . $e->getMessage());
        return null;
    }
}

/**
 * Fetch della classifica finale da UndercutF1
 */
function fetch_undercutf1_standings() {
    $api_key = get_undercutf1_api_key();

    if (!$api_key) {
        return [
            'ok' => false,
            'error' => 'Impossibile ottenere chiave API da UndercutF1. Assicurati che sia in esecuzione su localhost:61937'
        ];
    }

    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0',
                'header' => "X-API-Key: $api_key\r\n"
            ]
        ]);

        $response = file_get_contents(UNDERCUTF1_BASE_URL . "/export/standings/json", false, $context);

        if ($response === false) {
            return [
                'ok' => false,
                'error' => 'Timeout o errore connessione a UndercutF1. Verifica che sia in esecuzione.'
            ];
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['drivers'])) {
            return [
                'ok' => false,
                'error' => 'Formato dati UndercutF1 non valido',
                'raw_response' => $response
            ];
        }

        // Trasforma dati UndercutF1 nel formato atteso dalla classifica finale
        $standings = array_map(function ($driver) {
            return [
                'Posizione' => (int)$driver['position'] ?? 0,
                'Numero Gara' => (int)$driver['racingNumber'] ?? 0,
                'Pilota' => $driver['fullName'] ?? $driver['tla'] ?? 'N/D',
                'Team' => $driver['team'] ?? 'N/D',
                'Best Lap' => $driver['bestLap'] ?? '-',
                'Ultimo Giro' => $driver['lastLap'] ?? '-',
                'Giri' => (int)$driver['numberOfLaps'] ?? 0,
                'Gap' => $driver['gap'] ?? ''
            ];
        }, $data['drivers']);

        return [
            'ok' => true,
            'data' => $standings,
            'session' => $data['session'] ?? 'Unknown',
            'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
            'driver_count' => count($standings)
        ];

    } catch (Exception $e) {
        return [
            'ok' => false,
            'error' => 'Eccezione: ' . $e->getMessage()
        ];
    }
}

/**
 * Testa la connessione a UndercutF1
 */
function test_undercutf1_connection() {
    $api_key = get_undercutf1_api_key();

    if (!$api_key) {
        return [
            'ok' => false,
            'message' => 'UndercutF1 non raggiungibile su ' . UNDERCUTF1_BASE_URL,
            'expected_url' => UNDERCUTF1_BASE_URL,
            'port' => UNDERCUTF1_PORT
        ];
    }

    return [
        'ok' => true,
        'message' => 'UndercutF1 connesso',
        'api_key_present' => !!$api_key,
        'base_url' => UNDERCUTF1_BASE_URL
    ];
}

// ──────────────────────────────────────────
// ROUTE HANDLER
// ──────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? null;

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'fetch_standings':
        $result = fetch_undercutf1_standings();
        echo json_encode($result);
        break;

    case 'test':
        $result = test_undercutf1_connection();
        echo json_encode($result);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Azione non riconosciuta',
            'available' => ['fetch_standings', 'test']
        ]);
}
