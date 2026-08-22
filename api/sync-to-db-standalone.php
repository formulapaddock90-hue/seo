<?php
/**
 * Sincronizzazione Standings → Database MySQL
 * Eseguibile via CLI: php sync-to-db-standalone.php
 * Oppure: apri nel browser sul server remoto
 */

// Configurazione DB privata/condivisa
require __DIR__ . '/../conn.php';
$host = $hostname;
$db = $dbname;
$user = $username;
$pass = $password;

// File da sincronizzare
$file = __DIR__ . '/data/session-results.txt';

echo "╔════════════════════════════════════════════╗\n";
echo "║  F1 STANDINGS SYNC → DATABASE              ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

// Check file
if (!file_exists($file)) {
    echo "❌ File non trovato: $file\n";
    exit(1);
}

echo "✅ File trovato: $file\n";

// Connessione DB
try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connessione DB OK\n\n";
} catch (Exception $e) {
    echo "❌ Errore connessione: " . $e->getMessage() . "\n";
    exit(1);
}

// Leggi file
$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$lines) {
    echo "❌ Errore lettura file\n";
    exit(1);
}

// Estrai race number
$race_number = time();
foreach (array_slice($lines, 0, 5) as $line) {
    if (strpos($line, 'Aggiornato:') !== false) {
        $ts = trim(str_replace('Aggiornato:', '', $line));
        $race_number = strtotime($ts) ?: time();
    }
}

echo "📊 Race Number: $race_number\n";

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

try {
    $conn->exec($sql);
    echo "✅ Tabella f1_final_standings verificata\n";
} catch (Exception $e) {
    echo "❌ Errore creazione tabella: " . $e->getMessage() . "\n";
    exit(1);
}

// Elimina vecchi dati
$conn->prepare("DELETE FROM f1_final_standings WHERE race_number = ?")
    ->execute([$race_number]);
echo "🗑️  Vecchi record eliminati\n";

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

    try {
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
    } catch (Exception $e) {
        echo "⚠️  Errore riga $i: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Sincronizzazione completata!\n";
echo "📌 Piloti importati: $count\n";
echo "🏎️  Gara: #$race_number\n";
echo "📅 Data: " . date('Y-m-d H:i:s', $race_number) . "\n";

// Verifica
$stmt = $conn->query("SELECT COUNT(*) as total FROM f1_final_standings");
$result = $stmt->fetch();
echo "\n📊 Totale record nel DB: " . $result['total'] . "\n";

echo "\n✅ FATTO!\n";
echo "Accedi a: https://www.formulapaddock.it/seo/ per vedere i dati\n";
?>
