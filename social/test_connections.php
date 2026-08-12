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

// 5) LinkedIn: verifica token e URN
if (file_exists($config['linkedin_oauth_token_json'])) {
    try {
        require_once __DIR__ . '/includes/linkedin_service.php';
        $liToken = getLinkedInAccessToken($config);
        $userInfo = linkedinRequest('GET', 'https://api.linkedin.com/v2/userinfo', [
            'Authorization: Bearer ' . $liToken
        ]);
        echo "[LINKEDIN] OK -> Utente connesso: " . ($userInfo['given_name'] ?? '') . " " . ($userInfo['family_name'] ?? '') . " (URN configurato: " . $config['linkedin_author_urn'] . ")\n";
    } catch (Exception $e) {
        echo "[LINKEDIN] ERRORE: " . $e->getMessage() . "\n";
    }
} else {
    echo "[LINKEDIN] NOTA: non ancora configurato (linkedin-token.json mancante)\n";
}

// 6) Threads: verifica token e info utente
if (file_exists($config['threads_oauth_token_json'])) {
    try {
        require_once __DIR__ . '/includes/threads_service.php';
        $thTokenData = getThreadsAccessToken($config);
        $thUserInfo = threadsApiRequest('GET', 'https://graph.threads.net/v1.0/me', [
            'Authorization: Bearer ' . $thTokenData['access_token']
        ], [
            'fields' => 'id,username'
        ]);
        echo "[THREADS] OK -> Connesso come @" . ($thUserInfo['username'] ?? '') . " (ID: " . ($thUserInfo['id'] ?? '') . ")\n";
    } catch (Exception $e) {
        echo "[THREADS] ERRORE: " . $e->getMessage() . "\n";
    }
} else {
    echo "[THREADS] NOTA: non ancora configurato (threads-token.json mancante)\n";
}
