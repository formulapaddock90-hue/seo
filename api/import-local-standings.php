<?php
/**
 * Importa Classifica da File Locale a MySQL
 *
 * Fonte: C:\xampp\htdocs\seo\data\session-results.txt
 * Destinazione: f1_final_standings (MySQL)
 *
 * Uso:
 *   GET  api/import-local-standings.php?action=import
 *   GET  api/import-local-standings.php?action=check_file
 */

require __DIR__ . '/../conn.php';

// Configurazione percorso file locale
define('STANDINGS_FILE', 'C:\\xampp\\htdocs\\seo\\data\\session-results.txt');

// Mapping TLA → Nome Completo (da aggiornare se necessario)
$TLA_TO_FULLNAME = [
    'VER' => 'Max Verstappen',
    'HAM' => 'Lewis Hamilton',
    'LEC' => 'Charles Leclerc',
    'SAI' => 'Carlos Sainz',
    'NOR' => 'Lando Norris',
    'PIA' => 'Oscar Piastri',
    'RUS' => 'George Russell',
    'BEA' => 'Oliver Bearman',
    'HAD' => 'Haas Driver',
    'ALB' => 'Alexander Albon',
    'STR' => 'Lance Stroll',
    'ALO' => 'Fernando Alonso',
    'GAS' => 'Pierre Gasly',
    'COL' => 'Yuki Tsunoda',
    'BOT' => 'Valtteri Bottas',
    'LAW' => 'Liam Lawson',
    'LIN' => 'Yuri Iwasa',
    'HUL' => 'Nico Hulkenberg',
    'OCO' => 'Esteban Ocon',
    'PER' => 'Sergio Perez',
    'ANT' => 'Antonio Giovinazzi',
    'BOR' => 'Guanyu Zhou',
    'MAG' => 'Kevin Magnussen',
];

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
 * Verifica se il file esiste
 */
function check_standings_file() {
    if (!file_exists(STANDINGS_FILE)) {
        return [
            'ok' => false,
            'message' => 'File non trovato',
            'expected_path' => STANDINGS_FILE
        ];
    }

    $size = filesize(STANDINGS_FILE);
    $modified = date('Y-m-d H:i:s', filemtime(STANDINGS_FILE));

    return [
        'ok' => true,
        'message' => 'File trovato',
        'path' => STANDINGS_FILE,
        'size' => $size,
        'last_modified' => $modified
    ];
}

/**
 * Importa classifica da file locale a MySQL
 */
function import_standings_to_db($conn) {
    // Verifica file
    if (!file_exists(STANDINGS_FILE)) {
        return [
            'ok' => false,
            'error' => 'File non trovato: ' . STANDINGS_FILE
        ];
    }

    try {
        // Leggi file
        $lines = file(STANDINGS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return ['ok' => false, 'error' => 'Errore lettura file'];
        }

        // Parsa header (righe 0-4)
        $sessionInfo = [];
        foreach (array_slice($lines, 0, 4) as $line) {
            if (strpos($line, 'Sessione:') !== false) {
                $sessionInfo['session'] = trim(str_replace('Sessione:', '', $line));
            }
            if (strpos($line, 'Aggiornato:') !== false) {
                $sessionInfo['timestamp'] = trim(str_replace('Aggiornato:', '', $line));
            }
        }

        // Usa timestamp come race_number univoco
        $race_number = strtotime($sessionInfo['timestamp'] ?? date('d/m/Y H:i:s'));
        if (!$race_number) {
            $race_number = time();
        }

        // Crea tabella se non esiste
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

        // Elimina vecchi dati della stessa gara
        $conn->prepare("DELETE FROM f1_final_standings WHERE race_number = ?")
            ->execute([$race_number]);

        // Parsa CSV (riga 5 in poi)
        $stmt = $conn->prepare(<<<SQL
            INSERT INTO f1_final_standings
            (race_number, position, driver_number, driver_name, team_name, best_lap, last_lap, total_laps, gap)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $count = 0;
        $TLA_MAP = $GLOBALS['TLA_TO_FULLNAME'] ?? [];

        for ($i = 5; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            $cols = str_getcsv($line);
            if (count($cols) < 8) continue;

            $position = (int)$cols[0];
            $driver_number = (int)($cols[1] ?? 0);
            $driver_tla = trim($cols[2] ?? '');
            $driver_name = $TLA_MAP[$driver_tla] ?? $driver_tla;
            $team_name = trim($cols[3] ?? 'N/D');
            $best_lap = trim($cols[4] ?? '-');
            $last_lap = trim($cols[5] ?? '-');
            $total_laps = (int)($cols[6] ?? 0);
            $gap = trim($cols[7] ?? '');

            // Normalizza gap (es: "1L" → "1L", "+1.952" → "+1.952")
            if (strpos($gap, 'L') === false && strpos($gap, 'Leader') === false) {
                if (empty($gap) || $gap === 'Leader') {
                    $gap = '';
                }
            }

            $stmt->execute([
                $race_number,
                $position,
                $driver_number,
                $driver_name,
                $team_name,
                $best_lap === '' ? '-' : $best_lap,
                $last_lap === '' ? '-' : $last_lap,
                $total_laps,
                $gap
            ]);
            $count++;
        }

        return [
            'ok' => true,
            'message' => "Importazione completata: $count piloti salvati",
            'race_number' => $race_number,
            'driver_count' => $count,
            'session' => $sessionInfo['session'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Ottieni ultimo import
 */
function get_last_import($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM f1_final_standings ORDER BY race_number DESC LIMIT 1");
        $stmt->execute();
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$latest) {
            return ['ok' => true, 'message' => 'Nessun import ancora', 'last_import' => null];
        }

        $count = $conn->query("SELECT COUNT(*) as total FROM f1_final_standings WHERE race_number = " . $latest['race_number'])->fetch()['total'];

        return [
            'ok' => true,
            'last_import' => [
                'race_number' => $latest['race_number'],
                'timestamp' => $latest['created_at'],
                'driver_count' => $count,
                'latest_driver' => $latest['driver_name']
            ]
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
    case 'import':
        $result = import_standings_to_db($conn);
        echo json_encode($result);
        break;

    case 'check_file':
        $result = check_standings_file();
        echo json_encode($result);
        break;

    case 'last_import':
        $result = get_last_import($conn);
        echo json_encode($result);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Azione non riconosciuta',
            'available' => ['import', 'check_file', 'last_import'],
            'usage' => [
                'import' => 'Importa da file locale a MySQL',
                'check_file' => 'Verifica se il file esiste',
                'last_import' => 'Mostra ultimo import'
            ]
        ]);
}
