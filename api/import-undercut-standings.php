<?php
/**
 * API per importare la classifica finale da UndercutF1
 * Legge il CSV esportato da undercut e lo importa nel database
 */

require __DIR__ . '/../conn.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// URL del CSV su undercut (pubblica)
$undercutCsvUrl = 'https://www.formulapaddock.it/undercut-f1/public/classifica/finale.csv';
$localCsvPath = __DIR__ . '/../storage/classifica/finale.csv';

try {
    $conn = new mysqli($hostname, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Errore DB: " . $conn->connect_error);
    }

    $action = $_GET['action'] ?? 'import';

    switch ($action) {
        case 'fetch':
            // Fetch e parse del CSV
            $csvData = readCsvFromUndercut();
            $standings = parseCsvStandings($csvData);

            echo json_encode([
                'ok' => true,
                'data' => $standings,
                'count' => count($standings),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'import':
            // Fetch, parse e salvataggio nel DB
            $csvData = readCsvFromUndercut();
            $standings = parseCsvStandings($csvData);

            if (empty($standings)) {
                throw new Exception("Nessun dato nel CSV");
            }

            // Salva nel DB
            $inserted = saveStandingsToDb($conn, $standings);

            echo json_encode([
                'ok' => true,
                'message' => "Classifica importata: $inserted piloti",
                'count' => $inserted,
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => $standings
            ]);
            break;

        case 'status':
            // Verifica stato della classifica
            $result = $conn->query("SELECT COUNT(*) as count FROM classifica LIMIT 1");
            $row = $result->fetch_assoc();

            echo json_encode([
                'ok' => true,
                'piloti_in_db' => $row['count'] ?? 0,
                'csv_url' => $undercutCsvUrl,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        default:
            throw new Exception("Action non valida: $action");
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'csv_url' => $undercutCsvUrl
    ]);
}

/**
 * Legge il CSV da undercut
 */
function readCsvFromUndercut() {
    global $undercutCsvUrl, $localCsvPath;

    // Prova prima il file locale cache
    if (file_exists($localCsvPath)) {
        $csv = @file_get_contents($localCsvPath);
        if ($csv) {
            return $csv;
        }
    }

    // Fallback: lettura da URL remota
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $undercutCsvUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $csv = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $csv) {
            // Cache il file
            @mkdir(dirname($localCsvPath), 0755, true);
            @file_put_contents($localCsvPath, $csv);
            return $csv;
        }
    }

    throw new Exception("Impossibile leggere CSV da $undercutCsvUrl");
}

/**
 * Parsa il CSV e ritorna array di standings
 */
function parseCsvStandings($csvData) {
    $lines = array_filter(array_map('str_getcsv', explode("\n", $csvData)));

    if (empty($lines)) {
        return [];
    }

    // Header (prima riga)
    $headers = array_shift($lines);
    $headers = array_map('trim', $headers);

    $standings = [];
    foreach ($lines as $row) {
        if (empty($row) || empty($row[0])) continue;

        // Crea array associativo
        $data = [];
        foreach ($headers as $idx => $header) {
            $data[$header] = $row[$idx] ?? '';
        }

        $standings[] = $data;
    }

    return $standings;
}

/**
 * Salva i dati nel database
 */
function saveStandingsToDb(&$conn, $standings) {
    $inserted = 0;

    // Pulisci la tabella vecchia
    $conn->query("TRUNCATE TABLE classifica");

    foreach ($standings as $stand) {
        $pos = $stand['Posizione'] ?? '';
        $num = $stand['N. Gara'] ?? '';
        $pilota = $stand['Pilota'] ?? '';
        $team = $stand['Team'] ?? '';
        $best = $stand['Best Lap'] ?? '';
        $ultimo = $stand['Ultimo Giro'] ?? '';
        $giri = $stand['Giri'] ?? '';
        $gap = $stand['Gap'] ?? '';

        if (!$pos || !$pilota) continue;

        $sql = "INSERT INTO classifica (posizione, n_gara, pilota, team, best_lap, ultimo_giro, giri, gap, timestamp)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Errore prepare: " . $conn->error);
        }

        $stmt->bind_param(
            "isssssss",
            $pos, $num, $pilota, $team, $best, $ultimo, $giri, $gap
        );

        if ($stmt->execute()) {
            $inserted++;
        } else {
            error_log("Errore insert: " . $stmt->error);
        }

        $stmt->close();
    }

    return $inserted;
}
?>
