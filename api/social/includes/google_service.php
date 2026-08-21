<?php
/**
 * Google Sheets + Google Drive tramite chiamate REST pure (cURL),
 * SENZA dipendenze Composer.
 *
 * Autenticazione, in ordine di preferenza:
 *  1. OAuth utente (credentials/oauth-client.json + oauth-token.json, creati da
 *     oauth_setup.php) — necessario per l'upload su Drive: i service account
 *     non hanno quota di archiviazione.
 *  2. Service account (credentials/service-account.json, firma JWT RS256 via
 *     OpenSSL) — sufficiente per Google Sheets.
 */

/**
 * Esegue una richiesta HTTP verso le API Google e decodifica la risposta JSON.
 *
 * @param string      $method  GET | POST | ...
 * @param string      $url
 * @param array       $headers Header HTTP (stringhe "Nome: valore")
 * @param string|null $body    Corpo della richiesta (gia' codificato)
 * @return array
 * @throws Exception
 */
function googleHttp(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $err) {
        throw new Exception("Errore di rete verso Google: $err");
    }

    $data = json_decode($response, true);

    if ($httpCode >= 400) {
        if (isset($data['error']['message'])) {
            $msg = $data['error']['message'];
        } elseif (isset($data['error']) && is_string($data['error'])) {
            $msg = $data['error'] . (isset($data['error_description']) ? ' - ' . $data['error_description'] : '');
        } else {
            $msg = $response;
        }
        throw new Exception("Errore Google API ($httpCode): $msg");
    }

    return is_array($data) ? $data : [];
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Legge il file del client OAuth (formato "web" o "installed" di Google Cloud).
 *
 * @return array{client_id:string, client_secret:string}
 * @throws Exception
 */
function loadOAuthClient(array $config): array
{
    $json = json_decode(file_get_contents($config['google_oauth_client_json']), true);
    $client = $json['web'] ?? $json['installed'] ?? null;

    if (!is_array($client) || empty($client['client_id']) || empty($client['client_secret'])) {
        throw new Exception(
            'File del client OAuth non valido: ' . $config['google_oauth_client_json'] .
            ' (atteso il JSON scaricato da Google Cloud Console, tipo "Applicazione web")'
        );
    }

    return $client;
}

/**
 * Restituisce un access token valido per le API Google.
 * Usa il token OAuth utente se presente (con refresh automatico),
 * altrimenti genera un token dal service account (JWT RS256).
 *
 * @throws Exception
 */
function getGoogleAccessToken(array $config): string
{
    static $cachedToken = null;
    static $cachedExpiry = 0;

    if ($cachedToken !== null && time() < $cachedExpiry - 60) {
        return $cachedToken;
    }

    if (file_exists($config['google_oauth_client_json']) && file_exists($config['google_oauth_token_json'])) {
        [$token, $expiry] = getOAuthUserToken($config);
    } else {
        [$token, $expiry] = getServiceAccountToken($config);
    }

    $cachedToken = $token;
    $cachedExpiry = $expiry;

    return $token;
}

/**
 * Token OAuth utente: usa quello salvato se ancora valido, altrimenti lo
 * rinnova con il refresh token e risalva il file.
 *
 * @return array{0:string, 1:int} [access_token, scadenza unix]
 * @throws Exception
 */
function getOAuthUserToken(array $config): array
{
    $token = json_decode(file_get_contents($config['google_oauth_token_json']), true);
    if (!is_array($token)) {
        throw new Exception('Token OAuth non valido: rifai la procedura su oauth_setup.php');
    }

    $obtainedAt = (int) ($token['obtained_at'] ?? $token['created'] ?? 0);
    $expiresAt = $obtainedAt + (int) ($token['expires_in'] ?? 0);

    if (!empty($token['access_token']) && time() < $expiresAt - 60) {
        return [$token['access_token'], $expiresAt];
    }

    if (empty($token['refresh_token'])) {
        throw new Exception(
            'Token OAuth scaduto e nessun refresh token disponibile: rifai la procedura su oauth_setup.php'
        );
    }

    $client = loadOAuthClient($config);

    $data = googleHttp('POST', 'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $token['refresh_token'],
            'client_id'     => $client['client_id'],
            'client_secret' => $client['client_secret'],
        ])
    );

    if (empty($data['access_token'])) {
        throw new Exception('Rinnovo del token OAuth fallito: rifai la procedura su oauth_setup.php');
    }

    // Google non sempre restituisce di nuovo il refresh token: conservalo
    $data['refresh_token'] = $data['refresh_token'] ?? $token['refresh_token'];
    $data['obtained_at'] = time();
    file_put_contents($config['google_oauth_token_json'], json_encode($data, JSON_PRETTY_PRINT));

    return [$data['access_token'], time() + (int) ($data['expires_in'] ?? 3600)];
}

/**
 * Token dal service account: costruisce un JWT firmato RS256 con la chiave
 * privata e lo scambia con un access token.
 *
 * @return array{0:string, 1:int} [access_token, scadenza unix]
 * @throws Exception
 */
function getServiceAccountToken(array $config): array
{
    if (!file_exists($config['google_service_account_json'])) {
        throw new Exception(
            'Nessuna credenziale Google disponibile: manca sia il token OAuth (oauth_setup.php) ' .
            'sia il file del service account: ' . $config['google_service_account_json']
        );
    }

    $sa = json_decode(file_get_contents($config['google_service_account_json']), true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
        throw new Exception('File del service account non valido: ' . $config['google_service_account_json']);
    }

    $now = time();
    $tokenUri = $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token';

    $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = base64UrlEncode(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/spreadsheets',
        'aud'   => $tokenUri,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $signature = '';
    if (!openssl_sign("$header.$claims", $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new Exception('Firma JWT fallita: chiave privata del service account non valida.');
    }

    $jwt = "$header.$claims." . base64UrlEncode($signature);

    $data = googleHttp('POST', $tokenUri,
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ])
    );

    if (empty($data['access_token'])) {
        throw new Exception('Ottenimento token dal service account fallito.');
    }

    return [$data['access_token'], $now + (int) ($data['expires_in'] ?? 3600)];
}

/**
 * Cerca nella cartella Drive configurata un file con il nome dato.
 * Restituisce l'id del file, oppure null se non esiste.
 *
 * @throws Exception
 */
function findDriveFileByName(string $name, string $token, array $config): ?string
{
    $q = sprintf(
        "name = '%s' and '%s' in parents and trashed = false",
        str_replace("'", "\\'", $name),
        $config['google_drive_folder_id']
    );

    $data = googleHttp('GET',
        'https://www.googleapis.com/drive/v3/files?q=' . rawurlencode($q)
        . '&fields=files(id)&pageSize=1&supportsAllDrives=true&includeItemsFromAllDrives=true',
        ["Authorization: Bearer $token"]
    );

    return $data['files'][0]['id'] ?? null;
}

/**
 * Carica un file su Google Drive nella cartella configurata e restituisce
 * info utili (id, link di visualizzazione, link diretto al contenuto).
 * Se nella cartella esiste gia' un file con lo stesso nome, ne SOVRASCRIVE
 * il contenuto (stesso id, stesso link) invece di creare un duplicato.
 *
 * @param string $filePath   Percorso locale del file da caricare
 * @param string $mimeType   Mime type (es. 'image/jpeg', 'video/mp4')
 * @return array{id:string, view_link:string, direct_link:string, name:string}
 * @throws Exception
 */
function uploadFileToDrive(string $filePath, string $mimeType, array $config): array
{
    if (!file_exists($filePath)) {
        throw new Exception("File non trovato per l'upload su Drive: $filePath");
    }

    $token = getGoogleAccessToken($config);
    $name = basename($filePath);

    $existingId = findDriveFileByName($name, $token, $config);

    if ($existingId !== null) {
        // Aggiorna il contenuto del file esistente (i parents non si toccano in update)
        $metadata = json_encode(['name' => $name]);
        $method = 'PATCH';
        $url = "https://www.googleapis.com/upload/drive/v3/files/{$existingId}"
            . '?uploadType=multipart&fields=id,webViewLink,webContentLink&supportsAllDrives=true';
    } else {
        $metadata = json_encode([
            'name'    => $name,
            'parents' => [$config['google_drive_folder_id']],
        ]);
        $method = 'POST';
        $url = 'https://www.googleapis.com/upload/drive/v3/files'
            . '?uploadType=multipart&fields=id,webViewLink,webContentLink&supportsAllDrives=true';
    }

    $boundary = 'social_upload_' . md5(uniqid('', true));
    $body = "--$boundary\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $metadata . "\r\n"
        . "--$boundary\r\n"
        . "Content-Type: $mimeType\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . base64_encode(file_get_contents($filePath)) . "\r\n"
        . "--$boundary--";

    $file = googleHttp($method, $url,
        [
            "Authorization: Bearer $token",
            "Content-Type: multipart/related; boundary=$boundary",
            'Content-Length: ' . strlen($body),
        ],
        $body
    );

    if (empty($file['id'])) {
        throw new Exception('Upload su Drive fallito: risposta senza id file.');
    }

    // Rende il file leggibile tramite link (solo alla prima creazione)
    if ($existingId === null) {
        try {
            googleHttp('POST', "https://www.googleapis.com/drive/v3/files/{$file['id']}/permissions",
                ["Authorization: Bearer $token", 'Content-Type: application/json'],
                json_encode(['type' => 'anyone', 'role' => 'reader'])
            );
        } catch (Exception $e) {
            // Se i permessi a livello di organizzazione non lo consentono, si ignora:
            // il file rimane comunque accessibile da chi ha accesso alla cartella condivisa.
        }
    }

    return [
        'id'          => $file['id'],
        'view_link'   => $file['webViewLink'] ?? "https://drive.google.com/file/d/{$file['id']}/view",
        'direct_link' => $file['webContentLink'] ?? "https://drive.google.com/uc?id={$file['id']}",
        'name'        => $name,
    ];
}

/**
 * Determina il nome del foglio (tab) per la scrittura.
 * Se 'google_sheet_tab' e' vuoto, usa il primo foglio del file.
 *
 * @throws Exception
 */
function resolveSheetTabName(string $token, array $config): string
{
    if (!empty($config['google_sheet_tab'])) {
        return $config['google_sheet_tab'];
    }

    $data = googleHttp('GET',
        "https://sheets.googleapis.com/v4/spreadsheets/{$config['google_sheet_id']}"
        . '?fields=sheets.properties.title',
        ["Authorization: Bearer $token"]
    );

    $title = $data['sheets'][0]['properties']['title'] ?? null;
    if ($title === null) {
        throw new Exception('Impossibile determinare il foglio (tab) del Google Sheet.');
    }

    return $title;
}

/**
 * Aggiunge una nuova riga al Google Sheet, secondo l'ordine di colonne:
 * data | facebook | twitter | linkedin | instagram | categoria | img evidenza | twitter modificato | link
 *
 * @param array $rowData Array associativo con le chiavi corrispondenti alle colonne
 * @throws Exception
 */
function appendRowToSheet(array $rowData, array $config): void
{
    $token = getGoogleAccessToken($config);
    $tabName = resolveSheetTabName($token, $config);

    // Ordine ESATTO delle colonne del foglio
    $row = [
        $rowData['data']               ?? '',
        $rowData['facebook']           ?? '',
        $rowData['twitter']            ?? '',
        $rowData['linkedin']           ?? '',
        $rowData['instagram']          ?? '',
        $rowData['categoria']          ?? '',
        $rowData['img_evidenza']       ?? '',
        $rowData['twitter_modificato'] ?? '',
        $rowData['link']               ?? '',
    ];

    $range = rawurlencode("'{$tabName}'!A1");

    googleHttp('POST',
        "https://sheets.googleapis.com/v4/spreadsheets/{$config['google_sheet_id']}"
        . "/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS",
        ["Authorization: Bearer $token", 'Content-Type: application/json'],
        json_encode(['values' => [$row]])
    );
}
