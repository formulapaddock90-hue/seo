<?php
/**
 * oauth_setup_linkedin.php
 *
 * Procedura per autorizzare l'app ad accedere alle API LinkedIn e pubblicare post.
 * Salva il token in credentials/linkedin-token.json e mostra il Person URN dell'utente.
 */

$config = require __DIR__ . '/config.php';

const LINKEDIN_AUTH_URL  = 'https://www.linkedin.com/oauth/v2/authorization';
const LINKEDIN_TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';
const LINKEDIN_SCOPES    = 'w_member_social openid profile email';

function page(string $title, string $bodyHtml): void
{
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font-family:sans-serif;background:#0a0a0f;color:#fff;padding:40px;}
    .box{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);padding:24px;border-radius:12px;max-width:720px;}
    a{color:#ffd100;} code{background:rgba(0,0,0,0.4);padding:2px 6px;border-radius:4px;}
    .ok{color:#4c8;} .err{color:#f66;} .info{background:rgba(0,120,255,0.1);border:1px solid #08f;padding:12px;border-radius:6px;margin:15px 0;}</style></head><body><div class="box">' . $bodyHtml . '</div></body></html>';
    exit;
}

if (empty($config['linkedin_client_id']) || empty($config['linkedin_client_secret'])) {
    page('Setup OAuth LinkedIn', '<h2 class="err">⚠️ Mancano le credenziali Client LinkedIn in config.php</h2>
        <p>Assicurati di aver configurato <code>linkedin_client_id</code> e <code>linkedin_client_secret</code> in <code>config.php</code>.</p>
        <p>L\'URI di reindirizzamento autorizzato da inserire sul portale sviluppatori di LinkedIn è:</p>
        <p><code>' . htmlspecialchars($config['linkedin_redirect_uri']) . '</code></p>');
}

function linkedinHttp(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 30,
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
        throw new Exception("Errore di rete verso LinkedIn: $err");
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['message'] ?? $data['error_description'] ?? $response;
        throw new Exception("Errore LinkedIn API ($httpCode): $msg");
    }

    return is_array($data) ? $data : [];
}

if (isset($_GET['error'])) {
    page('Setup OAuth LinkedIn', '<h2 class="err">⚠️ Autorizzazione negata da LinkedIn</h2>
        <p>Errore: <code>' . htmlspecialchars($_GET['error']) . '</code></p>
        <p>Descrizione: <code>' . htmlspecialchars($_GET['error_description'] ?? '') . '</code></p>
        <p><a href="oauth_setup_linkedin.php">Riprova</a></p>');
}

if (isset($_GET['code'])) {
    // Scambia il codice con l'access token
    try {
        $tokenData = linkedinHttp('POST', LINKEDIN_TOKEN_URL,
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query([
                'grant_type'    => 'authorization_code',
                'code'          => $_GET['code'],
                'client_id'     => $config['linkedin_client_id'],
                'client_secret' => $config['linkedin_client_secret'],
                'redirect_uri'  => $config['linkedin_redirect_uri'],
            ])
        );

        $tokenData['obtained_at'] = time();
        
        // Crea la cartella credentials se non esiste
        if (!is_dir(dirname($config['linkedin_oauth_token_json']))) {
            mkdir(dirname($config['linkedin_oauth_token_json']), 0777, true);
        }
        
        file_put_contents($config['linkedin_oauth_token_json'], json_encode($tokenData, JSON_PRETTY_PRINT));

        // Recupera le informazioni sul profilo utente (OIDC /userinfo) per determinare il Person URN
        $userInfo = linkedinHttp('GET', 'https://api.linkedin.com/v2/userinfo', [
            'Authorization: Bearer ' . $tokenData['access_token']
        ]);

        $personUrn = isset($userInfo['sub']) ? 'urn:li:person:' . $userInfo['sub'] : null;
        $name = ($userInfo['given_name'] ?? '') . ' ' . ($userInfo['family_name'] ?? '');

        $urnMessage = '';
        if ($personUrn) {
            $urnMessage = '<div class="info">
                <p><strong>Copia e inserisci questo valore in config.php:</strong></p>
                <p><code>\'linkedin_author_urn\' => \'' . htmlspecialchars($personUrn) . '\',</code></p>
                <p>Nome rilevato: <strong>' . htmlspecialchars($name) . '</strong></p>
            </div>';
        } else {
            $urnMessage = '<p class="err">⚠️ Non è stato possibile determinare il tuo URN autore. Potrebbe essere necessario configurarlo manualmente.</p>';
        }

        page('Setup OAuth LinkedIn', '<h2 class="ok">✅ Autorizzazione completata con successo!</h2>
            <p>Il token è stato salvato in <code>' . htmlspecialchars(basename($config['linkedin_oauth_token_json'])) . '</code>.</p>' 
            . $urnMessage .
            '<p><a href="index.php">&rarr; Torna al generatore</a></p>');

    } catch (Exception $e) {
        page('Setup OAuth LinkedIn', '<h2 class="err">⚠️ Errore nel completamento dell\'autenticazione</h2>
            <pre>' . htmlspecialchars($e->getMessage()) . '</pre>
            <p><a href="oauth_setup_linkedin.php">Riprova</a></p>');
    }
}

// Avvia il flusso OAuth
$authUrl = LINKEDIN_AUTH_URL . '?' . http_build_query([
    'response_type' => 'code',
    'client_id'     => $config['linkedin_client_id'],
    'redirect_uri'  => $config['linkedin_redirect_uri'],
    'state'         => bin2hex(random_bytes(8)),
    'scope'         => LINKEDIN_SCOPES,
]);

header('Location: ' . $authUrl);
exit;
