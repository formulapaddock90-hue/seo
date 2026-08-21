<?php
/**
 * Auto-Sync Service per UndercutF1 Standings
 * Sincronizza il file locale nel database MySQL
 * Eseguibile via browser oppure come scheduled task
 */

// Setup Log
$log_dir = __DIR__ . '/logs';
@mkdir($log_dir, 0755, true);
$log_file = $log_dir . '/auto-sync.log';

function log_msg($msg) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

log_msg("=== Auto-Sync START ===");

try {
    // Connessione DB
    require __DIR__ . '/conn.php';

    $conn = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    log_msg("✅ Connessione DB OK");

    // File da leggere - prova percorsi diversi
    $file_paths = [
        'C:\\xampp\\htdocs\\seo\\data\\session-results.txt',
        __DIR__ . '\\data\\session-results.txt',
        __DIR__ . '/data/session-results.txt',
    ];

    $file = null;
    foreach ($file_paths as $path) {
        if (file_exists($path)) {
            $file = $path;
            log_msg("✅ File trovato: $file");
            break;
        }
    }

    if (!$file) {
        throw new Exception("File session-results.txt non trovato in nessun percorso");
    }

    // Leggi e parsa il file
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        throw new Exception("Errore lettura file");
    }

    // Estrai info sessione
    $race_number = time();
    foreach (array_slice($lines, 0, 5) as $line) {
        if (strpos($line, 'Aggiornato:') !== false) {
            $ts = trim(str_replace('Aggiornato:', '', $line));
            $race_number = strtotime($ts) ?: time();
        }
    }

    log_msg("Race number: $race_number");

    // Crea tabella
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
    log_msg("✅ Tabella verificata/creata");

    // Elimina dati vecchi per questa gara
    $conn->prepare("DELETE FROM f1_final_standings WHERE race_number = ?")
        ->execute([$race_number]);
    log_msg("✅ Record precedenti eliminati");

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

        $tla = trim($cols[2] ?? '');
        $driver_name = $TLA_MAP[$tla] ?? $tla;

        $stmt->execute([
            $race_number,
            (int)$cols[0],
            (int)($cols[1] ?? 0),
            $driver_name,
            trim($cols[3] ?? ''),
            trim($cols[4] ?? '') ?: '-',
            trim($cols[5] ?? '') ?: '-',
            (int)($cols[6] ?? 0),
            trim($cols[7] ?? '')
        ]);
        $count++;
    }

    log_msg("✅ Importati $count piloti nel DB");

    $message = "✅ Sincronizzazione: $count piloti importati";
    $status = "ok";

} catch (Exception $e) {
    $message = "❌ Errore: " . $e->getMessage();
    $status = "error";
    log_msg($message);
}

log_msg("=== Auto-Sync END ($status) ===\n");

// Risposta JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => $status === 'ok',
    'status' => $status,
    'message' => $message,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
?>
