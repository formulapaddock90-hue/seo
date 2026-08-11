<?php
require __DIR__ . '/../conn.php';

$username = $username;
$dbname = $dbname;
$hostname = $hostname;
$password = $password;

try {
    $conn = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'Errore connessione DB: ' . $e->getMessage()]));
}

function create_final_standings_table($conn) {
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
        return ['ok' => true, 'message' => 'Tabella creata con successo'];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function save_final_standings($conn, $race_number, $standings) {
    try {
        // Elimina vecchia classifica di questa gara
        $conn->prepare("DELETE FROM f1_final_standings WHERE race_number = ?")->execute([$race_number]);

        $stmt = $conn->prepare(<<<SQL
            INSERT INTO f1_final_standings
            (race_number, position, driver_number, driver_name, team_name, best_lap, last_lap, total_laps, gap)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        foreach ($standings as $standing) {
            $stmt->execute([
                $race_number,
                $standing['Posizione'] ?? null,
                $standing['Numero Gara'] ?? null,
                $standing['Pilota'] ?? null,
                $standing['Team'] ?? null,
                $standing['Best Lap'] ?? null,
                $standing['Ultimo Giro'] ?? null,
                $standing['Giri'] ?? null,
                $standing['Gap'] ?? null
            ]);
        }

        return ['ok' => true, 'message' => 'Classifica salvata', 'count' => count($standings)];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function get_final_standings($conn, $race_number = null) {
    try {
        $sql = "SELECT * FROM f1_final_standings";
        $params = [];

        if ($race_number) {
            $sql .= " WHERE race_number = ?";
            $params[] = $race_number;
        }

        $sql .= " ORDER BY race_number DESC, position ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $standings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['ok' => true, 'data' => $standings];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ──────────────────────────────────────────
// ROUTE HANDLER
// ──────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? null;

switch ($action) {
    case 'create_table':
        echo json_encode(create_final_standings_table($conn));
        break;

    case 'save':
        $race_number = $_POST['race_number'] ?? null;
        $standings = $_POST['standings'] ?? [];

        if (!$race_number || empty($standings)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'race_number e standings obbligatori']);
            break;
        }

        $result = save_final_standings($conn, $race_number, is_array($standings) ? $standings : []);
        echo json_encode($result);
        break;

    case 'get':
        $race_number = $_GET['race_number'] ?? null;
        echo json_encode(get_final_standings($conn, $race_number));
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Azione non riconosciuta']);
}
