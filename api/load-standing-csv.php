<?php
// Legge il CSV standing da URL remoto e lo restituisce in JSON

header('Content-Type: application/json; charset=utf-8');

$csvUrl = 'https://www.formulapaddock.it/standing/classifica.csv';
$csvLocalPath = __DIR__ . '/../../standing/classifica.csv';

try {
    $csvContent = null;

    // Prova il file locale prima
    if (file_exists($csvLocalPath) && filesize($csvLocalPath) > 50) {
        $csvContent = @file_get_contents($csvLocalPath);
    }

    // Fallback all'URL remoto
    if ($csvContent === null || empty($csvContent)) {
        if (function_exists('curl_init')) {
            $ch = curl_init($csvUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $csvContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($csvContent === false || $httpCode !== 200) {
                $csvContent = null;
            }
        }

        // Fallback a file_get_contents
        if ($csvContent === null) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
                ],
                'ssl' => [
                    'verify_peer' => false
                ]
            ]);
            $csvContent = @file_get_contents($csvUrl, false, $context);
        }
    }

    if ($csvContent === false || empty($csvContent)) {
        throw new Exception('Impossibile scaricare il file CSV da ' . $csvUrl . ' o da ' . $csvLocalPath);
    }

    $lines = explode("\n", $csvContent);
    if (count($lines) < 1) {
        throw new Exception('File CSV vuoto');
    }

    // Simula fgetcsv per le righe
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $csvContent);
    rewind($handle);

    // Leggi l'intestazione
    $header = fgetcsv($handle, 0, ';');
    if (!$header) {
        throw new Exception('File CSV vuoto o malformato');
    }

    $data = [];
    $sessionName = '';
    $raceName = '';
    $date = date('Y-m-d H:i');

    // Leggi le righe
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        // Salta righe vuote
        if (empty(array_filter($row))) continue;

        // Verifica che la riga abbia almeno la posizione (campo minimo)
        if (count($row) < 3) continue; // Almeno timestamp, sessione e posizione

        // Mappa i campi dal CSV ai campi della tabella
        $record = [];
        foreach ($header as $index => $fieldName) {
            $fieldName = trim($fieldName);
            $value = isset($row[$index]) ? trim($row[$index]) : '';

            switch ($fieldName) {
                case 'Timestamp':
                    if ($value) {
                        $date = $value;
                    }
                    break;
                case 'Sessione':
                    $sessionName = $value;
                    break;
                case 'Posizione':
                    $record['position'] = $value ?: '-';
                    break;
                case 'N. Gara':
                    $record['number'] = $value ?: '-';
                    break;
                case 'Pilota':
                    $record['driver_name'] = $value ?: '-';
                    break;
                case 'Team':
                    $record['team_name'] = $value ?: '-';
                    break;
                case 'Best Lap':
                    $record['best_lap'] = $value ?: '-';
                    break;
                case 'Ultimo Giro':
                    $record['last_lap'] = $value ?: '-';
                    break;
                case 'Giri':
                    $record['total_laps'] = $value ?: '-';
                    break;
                case 'Gap':
                    $record['gap'] = $value ?: '-';
                    break;
            }
        }

        // Aggiungi il record solo se ha una posizione valida
        if (!empty($record) && !empty($record['position'])) {
            $data[] = $record;
        }
    }

    fclose($handle);

    if (empty($data)) {
        throw new Exception('Nessun dato trovato nel CSV');
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'session_name' => $sessionName ?: 'Standing',
        'race_name' => $raceName ?: 'Classifica Standing',
        'date' => $date,
        'count' => count($data)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
