<?php
/**
 * Accesso Diretto al Database MySQL
 * Legge/scrive la classifica direttamente dal/nel DB
 * NO dipendenza da UndercutF1 API
 *
 * Endpoints:
 *   GET  api/standings-direct-db.php?action=get
 *   GET  api/standings-direct-db.php?action=get_latest
 *   POST api/standings-direct-db.php?action=create_table
 *   POST api/standings-direct-db.php?action=sync_from_file
 */

require __DIR__ . '/../conn.php';

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
    die(json_encode(['ok' => false, 'error' => 'DB Connection Failed: ' . $e->getMessage()]));
}

/**
 * Crea tabella nel database
 */
function create_standings_table($conn) {
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

        return [
            'ok' => true,
            'message' => 'Tabella creata con successo',
            'table' => 'f1_final_standings'
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Ottieni tutti i dati dalla tabella
 */
function get_all_standings($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM f1_final_standings ORDER BY race_number DESC, position ASC LIMIT 100");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'ok' => true,
            'count' => count($data),
            'data' => $data
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Ottieni classifica più recente
 */
function get_latest_standings($conn) {
    try {
        // Ottieni il race_number più recente
        $stmt = $conn->prepare("SELECT MAX(race_number) as latest FROM f1_final_standings");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $latest_race = $result['latest'] ?? null;

        if (!$latest_race) {
            return [
                'ok' => false,
                'error' => 'Nessun dato nel database',
                'message' => 'Salva una classifica prima di accedere'
            ];
        }

        // Ottieni dati della gara più recente
        $stmt = $conn->prepare(
            "SELECT * FROM f1_final_standings WHERE race_number = ? ORDER BY position ASC"
        );
        $stmt->execute([$latest_race]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'ok' => true,
            'race_number' => $latest_race,
            'driver_count' => count($data),
            'data' => $data,
            'timestamp' => $data[0]['updated_at'] ?? null
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Sincronizza dal file locale
 */
function sync_from_file($conn) {
    $file = 'C:\\xampp\\htdocs\\seo\\data\\session-results.txt';

    if (!file_exists($file)) {
        return ['ok' => false, 'error' => 'File non trovato: ' . $file];
    }

    try {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return ['ok' => false, 'error' => 'Errore lettura file'];
        }

        // Parsa header
        $sessionInfo = [];
        foreach (array_slice($lines, 0, 4) as $line) {
            if (strpos($line, 'Sessione:') !== false) {
                $sessionInfo['session'] = trim(str_replace('Sessione:', '', $line));
            }
            if (strpos($line, 'Aggiornato:') !== false) {
                $sessionInfo['timestamp'] = trim(str_replace('Aggiornato:', '', $line));
            }
        }

        // Race number = timestamp
        $race_number = strtotime($sessionInfo['timestamp'] ?? date('d/m/Y H:i:s'));
        if (!$race_number) $race_number = time();

        // Crea tabella
        create_standings_table($conn);

        // Elimina vecchi dati
        $conn->prepare("DELETE FROM f1_final_standings WHERE race_number = ?")
            ->execute([$race_number]);

        // Mapping TLA
        $TLA_MAP = [
            'VER' => 'Max Verstappen', 'HAM' => 'Lewis Hamilton', 'LEC' => 'Charles Leclerc',
            'SAI' => 'Carlos Sainz', 'NOR' => 'Lando Norris', 'PIA' => 'Oscar Piastri',
            'RUS' => 'George Russell', 'ALB' => 'Alexander Albon', 'STR' => 'Lance Stroll',
            'ALO' => 'Fernando Alonso', 'GAS' => 'Pierre Gasly', 'COL' => 'Yuki Tsunoda',
            'BOT' => 'Valtteri Bottas', 'LAW' => 'Liam Lawson', 'LIN' => 'Yuri Iwasa',
            'HUL' => 'Nico Hulkenberg', 'OCO' => 'Esteban Ocon', 'PER' => 'Sergio Perez',
            'ANT' => 'Antonio Giovinazzi', 'BOR' => 'Guanyu Zhou', 'MAG' => 'Kevin Magnussen',
            'BEA' => 'Oliver Bearman', 'HAD' => 'Haas Driver'
        ];

        // Insert dati
        $stmt = $conn->prepare(<<<SQL
            INSERT INTO f1_final_standings
            (race_number, position, driver_number, driver_name, team_name, best_lap, last_lap, total_laps, gap)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $count = 0;
        for ($i = 5; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            $cols = str_getcsv($line);
            if (count($cols) < 8) continue;

            $stmt->execute([
                $race_number,
                (int)$cols[0],
                (int)($cols[1] ?? 0),
                $TLA_MAP[trim($cols[2] ?? '')] ?? trim($cols[2] ?? ''),
                trim($cols[3] ?? ''),
                trim($cols[4] ?? '') ?: '-',
                trim($cols[5] ?? '') ?: '-',
                (int)($cols[6] ?? 0),
                trim($cols[7] ?? '')
            ]);
            $count++;
        }

        return [
            'ok' => true,
            'message' => "Sincronizzazione completata: $count piloti",
            'race_number' => $race_number,
            'driver_count' => $count,
            'session' => $sessionInfo['session'] ?? 'Unknown'
        ];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Ottieni status del database
 */
function get_db_status($conn) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM f1_final_standings");
        $count = $stmt->fetch()['total'] ?? 0;

        $stmt = $conn->query("SELECT COUNT(DISTINCT race_number) as races FROM f1_final_standings");
        $races = $stmt->fetch()['races'] ?? 0;

        $stmt = $conn->prepare("SELECT MAX(created_at) as last_update FROM f1_final_standings");
        $stmt->execute();
        $last = $stmt->fetch()['last_update'] ?? null;

        return [
            'ok' => true,
            'database' => $dbname,
            'table' => 'f1_final_standings',
            'total_records' => $count,
            'total_races' => $races,
            'last_update' => $last
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ──────────────────────────────────────────
// ROUTE HANDLER
// ──────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

switch ($action) {
    case 'create_table':
        echo json_encode(create_standings_table($conn));
        break;

    case 'get':
        echo json_encode(get_all_standings($conn));
        break;

    case 'get_latest':
        echo json_encode(get_latest_standings($conn));
        break;

    case 'sync_from_file':
        echo json_encode(sync_from_file($conn));
        break;

    case 'status':
        echo json_encode(get_db_status($conn));
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Azione non riconosciuta',
            'available' => ['create_table', 'get', 'get_latest', 'sync_from_file', 'status'],
            'usage' => [
                'create_table' => 'Crea tabella nel database',
                'get' => 'Ottieni tutti i dati',
                'get_latest' => 'Ottieni classifica più recente',
                'sync_from_file' => 'Importa dal file locale',
                'status' => 'Stato del database'
            ]
        ]);
}
