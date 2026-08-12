<?php
/**
 * Script di Sincronizzazione: UndercutF1 → MySQL
 *
 * Problema: UndercutF1 salva in locale, non in DB
 * Soluzione: Sync automatico da API UndercutF1 a MySQL
 *
 * Uso:
 *   GET  api/sync-undercutf1-to-db.php?action=sync_now
 *   GET  api/sync-undercutf1-to-db.php?action=status
 *   POST api/sync-undercutf1-to-db.php?action=setup_auto_sync (ogni N minuti)
 */

require __DIR__ . '/../conn.php';

// Configurazione
define('UNDERCUTF1_HOST', 'localhost');
define('UNDERCUTF1_PORT', 61937);
define('UNDERCUTF1_URL', "http://" . UNDERCUTF1_HOST . ":" . UNDERCUTF1_PORT);

// Connessione DB
try {
    $conn = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'DB connection failed: ' . $e->getMessage()]));
}

/**
 * Sincronizza i dati da UndercutF1 API a MySQL
 */
function sync_undercutf1_to_db($conn) {
    // Step 1: Ottieni API key da UndercutF1
    $api_key = get_undercutf1_api_key();
    if (!$api_key) {
        return [
            'ok' => false,
            'error' => 'Impossibile ottenere API key da UndercutF1. Assicurati che sia in esecuzione su localhost:61937'
        ];
    }

    // Step 2: Fetch dati da UndercutF1
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "X-API-Key: $api_key\r\n"
        ]
    ]);

    $response = @file_get_contents(UNDERCUTF1_URL . "/export/standings/json", false, $context);
    if ($response === false) {
        return [
            'ok' => false,
            'error' => 'Timeout connessione a UndercutF1. Verifica che sia in esecuzione.'
        ];
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['drivers'])) {
        return [
            'ok' => false,
            'error' => 'Formato dati UndercutF1 non valido'
        ];
    }

    // Step 3: Crea tabella se non esiste
    try {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS f1_final_standings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            race_number INT NOT NULL,
            position INT NOT NULL,
            driver_number INT,
            driver_name VARCHAR(255),
            team_name VARCHAR(255),
            best_lap VARCHAR(20),
            last_lap VARCHAR(20),
            total_laps INT,
            gap VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_race_position (race_number, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $conn->exec($sql);
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Errore creazione tabella: ' . $e->getMessage()];
    }

    // Step 4: Trasforma e salva dati
    try {
        $race_number = time(); // Usa timestamp come race_number unico

        // Elimina vecchi record della stessa gara (se esiste)
        $conn->prepare("DELETE FROM f1_final_standings WHERE race_number = ?")
            ->execute([$race_number]);

        $stmt = $conn->prepare(<<<SQL
            INSERT INTO f1_final_standings
            (race_number, position, driver_number, driver_name, team_name, best_lap, last_lap, total_laps, gap)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $count = 0;
        foreach ($data['drivers'] as $driver) {
            $stmt->execute([
                $race_number,
                (int)($driver['position'] ?? 0),
                (int)($driver['racingNumber'] ?? 0),
                $driver['fullName'] ?? $driver['tla'] ?? 'N/D',
                $driver['team'] ?? 'N/D',
                $driver['bestLap'] ?? '-',
                $driver['lastLap'] ?? '-',
                (int)($driver['numberOfLaps'] ?? 0),
                $driver['gap'] ?? ''
            ]);
            $count++;
        }

        return [
            'ok' => true,
            'message' => "Sincronizzazione completata: $count piloti salvati nel DB",
            'race_number' => $race_number,
            'driver_count' => $count,
            'session' => $data['session'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Errore salvataggio: ' . $e->getMessage()];
    }
}

/**
 * Ottieni API key da UndercutF1
 */
function get_undercutf1_api_key() {
    $response = @file_get_contents(UNDERCUTF1_URL . "/api/info", false, stream_context_create([
        'http' => ['timeout' => 5]
    ]));

    if ($response === false) return null;

    $data = json_decode($response, true);
    return $data['apiKey'] ?? null;
}

/**
 * Ottieni status di sincronizzazione
 */
function get_sync_status($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM f1_final_standings ORDER BY race_number DESC LIMIT 1");
        $stmt->execute();
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$latest) {
            return ['ok' => true, 'message' => 'Nessun dato sincronizzato', 'last_sync' => null];
        }

        $count = $conn->query("SELECT COUNT(*) as total FROM f1_final_standings")->fetch()['total'];

        return [
            'ok' => true,
            'total_records' => $count,
            'last_sync' => $latest['updated_at'],
            'last_race_number' => $latest['race_number'],
            'message' => "Ultimi dati: $latest[driver_name] in posizione $latest[position]"
        ];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verifica se UndercutF1 è raggiungibile
 */
function check_undercutf1_status() {
    $api_key = get_undercutf1_api_key();

    if (!$api_key) {
        return [
            'ok' => false,
            'message' => 'UndercutF1 non raggiungibile su ' . UNDERCUTF1_URL,
            'expected' => UNDERCUTF1_URL
        ];
    }

    return [
        'ok' => true,
        'message' => 'UndercutF1 è in esecuzione e raggiungibile',
        'url' => UNDERCUTF1_URL,
        'api_key_present' => true
    ];
}

// ──────────────────────────────────────────
// ROUTE HANDLER
// ──────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

switch ($action) {
    case 'sync_now':
        $result = sync_undercutf1_to_db($conn);
        echo json_encode($result);
        break;

    case 'status':
        $result = get_sync_status($conn);
        echo json_encode($result);
        break;

    case 'check':
        $result = check_undercutf1_status();
        echo json_encode($result);
        break;

    case 'full_status':
        // Status completo: UndercutF1 + DB + ultimi dati
        $uc_status = check_undercutf1_status();
        $db_status = get_sync_status($conn);

        echo json_encode([
            'undercutf1' => $uc_status,
            'database' => $db_status,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Azione non riconosciuta',
            'available' => ['sync_now', 'status', 'check', 'full_status'],
            'usage' => [
                'sync_now' => 'Sincronizza ora da UndercutF1 a MySQL',
                'status' => 'Mostra status della sincronizzazione',
                'check' => 'Verifica se UndercutF1 è raggiungibile',
                'full_status' => 'Status completo'
            ]
        ]);
}
