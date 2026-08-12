<?php
// Verifica connettivita': provider AI, Google Sheets, Google Drive (PHP puro, senza Composer).
require_once __DIR__ . '/includes/ai_generator.php';
require_once __DIR__ . '/includes/google_service.php';
$config = require __DIR__ . '/config.php';

// 1) Provider AI attivo
try {
    $r = callAI('Rispondi solo con la parola OK.', 'test', $config);
    echo "[AI: " . strtoupper($config['ai_provider']) . "] OK -> " . trim($r) . "\n";
} catch (Exception $e) {
    echo "[AI: " . strtoupper($config['ai_provider']) . "] ERRORE: " . $e->getMessage() . "\n";
}

// 2) Autenticazione Google (OAuth utente se configurato, altrimenti service account)
$authMode = (file_exists($config['google_oauth_client_json']) && file_exists($config['google_oauth_token_json']))
    ? 'OAuth utente' : 'service account';
try {
    $token = getGoogleAccessToken($config);
    echo "[AUTH] OK -> modalita': $authMode\n";
} catch (Exception $e) {
    echo "[AUTH] ERRORE ($authMode): " . $e->getMessage() . "\n";
    exit(1);
}

// 3) Sheets: leggi titolo e primo tab
try {
    $data = googleHttp('GET',
        "https://sheets.googleapis.com/v4/spreadsheets/{$config['google_sheet_id']}"
        . '?fields=properties.title,sheets.properties.title',
        ["Authorization: Bearer $token"]);
    $tab = $data['sheets'][0]['properties']['title'] ?? '?';
    echo "[SHEETS] OK -> titolo: \"" . $data['properties']['title'] . "\", primo tab: \"$tab\"\n";
} catch (Exception $e) {
    echo "[SHEETS] ERRORE: " . $e->getMessage() . "\n";
}

// 4) Drive: leggi metadati della cartella
try {
    $folder = googleHttp('GET',
        "https://www.googleapis.com/drive/v3/files/{$config['google_drive_folder_id']}"
        . '?fields=id,name,capabilities/canAddChildren',
        ["Authorization: Bearer $token"]);
    $canAdd = !empty($folder['capabilities']['canAddChildren']) ? 'si' : 'NO';
    echo "[DRIVE] OK -> cartella: \"" . $folder['name'] . "\", permesso di scrittura: $canAdd\n";
    if ($authMode === 'service account') {
        echo "[DRIVE] NOTA: con il solo service account l'UPLOAD fallira' (nessuna quota di archiviazione):\n";
        echo "        completa la procedura OAuth su http://localhost/social/oauth_setup.php\n";
    }
} catch (Exception $e) {
    echo "[DRIVE] ERRORE: " . $e->getMessage() . "\n";
}
