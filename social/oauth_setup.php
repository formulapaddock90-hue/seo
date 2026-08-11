<?php
/**
 * oauth_setup.php
 *
 * Procedura una-tantum per autorizzare l'app a caricare file su Google Drive
 * a nome del tuo account (i service account non hanno quota di archiviazione).
 * Implementata in PHP puro (cURL), senza dipendenze Composer.
 *
 * Prerequisito: credentials/oauth-client.json (ID client OAuth di tipo
 * "Applicazione web" con URI di reindirizzamento http://localhost/social/oauth_setup.php).
 *
 * Apri questa pagina nel browser, autorizza l'account Google proprietario della
 * cartella Drive, e il token verra' salvato in credentials/oauth-token.json.
 */

require_once __DIR__ . '/includes/google_service.php';

$config = require __DIR__ . '/config.php';

const GOOGLE_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_SCOPES    = 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/spreadsheets';

function page(string $title, string $bodyHtml): void
{
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font-family:sans-serif;background:#0a0a0f;color:#fff;padding:40px;}
    .box{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);padding:24px;border-radius:12px;max-width:720px;}
    a{color:#ffd100;} code{background:rgba(0,0,0,0.4);padding:2px 6px;border-radius:4px;}
    .ok{color:#4c8;} .err{color:#f66;}</style></head><body><div class="box">' . $bodyHtml . '</div></body></html>';
    exit;
}

if (!file_exists($config['google_oauth_client_json'])) {
    page('Setup OAuth', '<h2 class="err">⚠️ Manca il file del client OAuth</h2>
        <p>Crea un <strong>ID client OAuth</strong> su Google Cloud Console
        (API e servizi &rarr; Credenziali &rarr; Crea credenziali &rarr; ID client OAuth,
        tipo <strong>Applicazione web</strong>) con questo URI di reindirizzamento autorizzato:</p>
        <p><code>' . htmlspecialchars($config['google_oauth_redirect_uri']) . '</code></p>
        <p>Scarica il JSON e salvalo come <code>credentials/oauth-client.json</code>, poi ricarica questa pagina.</p>');
}

$client = loadOAuthClient($config);

if (isset($_GET['error'])) {
    page('Setup OAuth', '<h2 class="err">⚠️ Autorizzazione negata</h2>
        <p>Errore: <code>' . htmlspecialchars($_GET['error']) . '</code></p>
        <p><a href="oauth_setup.php">Riprova</a></p>');
}

if (isset($_GET['code'])) {
    // Scambia il codice di autorizzazione con i token
    try {
        $token = googleHttp('POST', GOOGLE_TOKEN_URL,
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query([
                'grant_type'    => 'authorization_code',
                'code'          => $_GET['code'],
                'client_id'     => $client['client_id'],
                'client_secret' => $client['client_secret'],
                'redirect_uri'  => $config['google_oauth_redirect_uri'],
            ])
        );
    } catch (Exception $e) {
        page('Setup OAuth', '<h2 class="err">⚠️ Errore nello scambio del codice</h2>
            <pre>' . htmlspecialchars($e->getMessage()) . '</pre>
            <p><a href="oauth_setup.php">Riprova</a></p>');
    }

    $token['obtained_at'] = time();
    file_put_contents($config['google_oauth_token_json'], json_encode($token, JSON_PRETTY_PRINT));

    $warnRefresh = empty($token['refresh_token'])
        ? '<p class="err">⚠️ Google non ha restituito un refresh token: il token scadra\' dopo un\'ora.
           Rimuovi l\'accesso dell\'app da <a href="https://myaccount.google.com/permissions" target="_blank">
           myaccount.google.com/permissions</a> e rifai la procedura.</p>'
        : '';

    // Verifica immediata: prova a leggere la cartella Drive configurata
    $check = '';
    try {
        $folder = googleHttp('GET',
            "https://www.googleapis.com/drive/v3/files/{$config['google_drive_folder_id']}?fields=name",
            ['Authorization: Bearer ' . $token['access_token']]
        );
        $check = '<p class="ok">✅ Accesso verificato alla cartella Drive: <strong>'
            . htmlspecialchars($folder['name']) . '</strong></p>';
    } catch (Exception $e) {
        $check = '<p class="err">⚠️ Token salvato, ma la cartella Drive configurata non e\' accessibile
            da questo account: assicurati di aver autorizzato l\'account Google giusto
            (proprietario della cartella) oppure condividi la cartella con questo account.</p>';
    }

    page('Setup OAuth', '<h2 class="ok">✅ Autorizzazione completata</h2>
        <p>Token salvato in <code>credentials/oauth-token.json</code>.
        Da ora i file verranno caricati su Drive a nome del tuo account,
        con rinnovo automatico del token.</p>' . $warnRefresh . $check .
        '<p><a href="index.php">&rarr; Vai al generatore</a></p>');
}

// Nessun codice: avvia il flusso di autorizzazione
$authUrl = GOOGLE_AUTH_URL . '?' . http_build_query([
    'client_id'     => $client['client_id'],
    'redirect_uri'  => $config['google_oauth_redirect_uri'],
    'response_type' => 'code',
    'scope'         => GOOGLE_SCOPES,
    'access_type'   => 'offline',   // serve il refresh token per i rinnovi automatici
    'prompt'        => 'consent',   // forza il rilascio del refresh token
]);

header('Location: ' . $authUrl);
exit;
