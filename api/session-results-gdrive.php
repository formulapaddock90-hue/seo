<?php
header('Content-Type: application/json');

$docId = '1onps0fzAnyMeUscrTChV294dVVBeVwD8v9mBgseZkF4';
$gdriveTxtUrl = 'https://docs.google.com/document/d/' . $docId . '/export?format=txt';

error_log("Tentativo di scaricare da: " . $gdriveTxtUrl);

try {
    // Usa cURL per scaricare il file
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gdriveTxtUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $text = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($text === false) {
            throw new Exception('Errore cURL: ' . curl_error($ch));
        }

        curl_close($ch);
    } else {
        // Fallback a file_get_contents
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            'https' => [
                'timeout' => 15,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        $text = @file_get_contents($gdriveTxtUrl, false, $context);
    }

    if ($text === false || empty($text)) {
        error_log("Testo vuoto o false");
        throw new Exception('Impossibile scaricare il file da Google Drive.');
    }

    error_log("Downloaded " . strlen($text) . " bytes");

    // Check if we got HTML error page
    if (strpos($text, '<html') !== false || strpos($text, 'DOCTYPE') !== false || strpos($text, '<!DOCTYPE') !== false) {
        error_log("Ricevuto HTML, non testo");
        throw new Exception('Google Drive ha restituito HTML. Il file potrebbe non essere accessibile.');
    }

    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $rows = [];
    $skipCount = 0;

    foreach ($lines as $line) {
        if (empty($line)) continue;

        // Skip header e info rows
        if ($skipCount < 3 || strpos($line, 'Posizione') !== false || strpos($line, 'Classifica') !== false || strpos($line, 'Sessione') !== false || strpos($line, 'Aggiornato') !== false) {
            $skipCount++;
            continue;
        }

        $parts = [];
        if (strpos($line, ',') !== false) {
            $parts = array_map('trim', explode(',', $line));
        } elseif (strpos($line, "\t") !== false) {
            $parts = array_map('trim', explode("\t", $line));
        } else {
            $parts = array_map('trim', preg_split('/\s{2,}/', $line));
        }

        if (count($parts) >= 5) {
            $rows[] = [
                'position' => $parts[0] ?? '-',
                'number' => $parts[1] ?? '-',
                'driver_name' => $parts[2] ?? '-',
                'team_name' => $parts[3] ?? '-',
                'time' => $parts[4] ?? '-'
            ];
        }
    }

    error_log("Parsed " . count($rows) . " rows");

    if (empty($rows)) {
        throw new Exception('Nessun dato trovato nel file.');
    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'session_name' => 'Classifica',
        'race_name' => 'Da Google Drive',
        'date' => date('d/m/Y'),
        'source' => 'Google Drive'
    ]);

} catch (Exception $e) {
    error_log("Errore: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
