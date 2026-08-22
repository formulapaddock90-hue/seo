<?php

require_once __DIR__ . '/bootstrap.php';

function finalStandingsConnection(): PDO
{
    try {
        require __DIR__ . '/../conn.php';

        return new PDO(
            "mysql:host={$hostname};dbname={$dbname};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable $exception) {
        error_log('Final standings DB unavailable: ' . $exception->getMessage());
        jsonResponse([
            'ok' => false,
            'code' => 'database_unavailable',
            'error' => 'Database classifiche non configurato o non raggiungibile.',
        ], 503);
    }
}

function createFinalStandingsTable(PDO $conn): void
{
    $conn->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS f1_final_standings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            race_number BIGINT NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);
}

function saveFinalStandings(PDO $conn, int $raceNumber, array $standings): array
{
    createFinalStandingsTable($conn);
    $conn->beginTransaction();

    try {
        $conn->prepare('DELETE FROM f1_final_standings WHERE race_number = ?')->execute([$raceNumber]);
        $stmt = $conn->prepare(<<<SQL
            INSERT INTO f1_final_standings
                (race_number, position, driver_number, driver_name, team_name, best_lap, last_lap, total_laps, gap)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $saved = 0;
        foreach ($standings as $standing) {
            if (!is_array($standing) || !isset($standing['Posizione'])) {
                continue;
            }

            $stmt->execute([
                $raceNumber,
                (int) $standing['Posizione'],
                isset($standing['Numero Gara']) ? (int) $standing['Numero Gara'] : null,
                $standing['Pilota'] ?? null,
                $standing['Team'] ?? null,
                $standing['Best Lap'] ?? null,
                $standing['Ultimo Giro'] ?? null,
                isset($standing['Giri']) ? (int) $standing['Giri'] : null,
                $standing['Gap'] ?? null,
            ]);
            $saved++;
        }

        $conn->commit();
        return ['ok' => true, 'message' => 'Classifica salvata', 'count' => $saved];
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    }
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
if (!in_array($action, ['create_table', 'save', 'get'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Azione non riconosciuta'], 400);
}

$conn = finalStandingsConnection();

try {
    if ($action === 'create_table') {
        createFinalStandingsTable($conn);
        jsonResponse(['ok' => true, 'message' => 'Tabella disponibile']);
    }

    if ($action === 'save') {
        $raceNumber = filter_var($_POST['race_number'] ?? null, FILTER_VALIDATE_INT);
        $standingsRaw = $_POST['standings'] ?? [];
        $standings = is_string($standingsRaw) ? json_decode($standingsRaw, true) : $standingsRaw;

        if (!$raceNumber || !is_array($standings) || $standings === []) {
            jsonResponse(['ok' => false, 'error' => 'race_number e standings obbligatori'], 400);
        }

        jsonResponse(saveFinalStandings($conn, (int) $raceNumber, $standings));
    }

    createFinalStandingsTable($conn);
    $raceNumber = filter_var($_GET['race_number'] ?? null, FILTER_VALIDATE_INT);
    $sql = 'SELECT * FROM f1_final_standings';
    $params = [];
    if ($raceNumber) {
        $sql .= ' WHERE race_number = ?';
        $params[] = $raceNumber;
    }
    $sql .= ' ORDER BY race_number DESC, position ASC';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $exception) {
    error_log('Final standings error: ' . $exception->getMessage());
    jsonResponse(['ok' => false, 'error' => 'Operazione classifiche non riuscita.'], 500);
}
