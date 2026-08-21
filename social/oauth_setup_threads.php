<?php
/**
 * oauth_setup_threads.php
 *
 * Procedura per autorizzare l'app ad accedere alle API Threads e pubblicare post.
 * Gestisce l'ottenimento del token a breve termine, lo scambia per il token a lungo termine
 * e lo salva in credentials/threads-token.json.
 */

$config = require __DIR__ . '/config.php';

const THREADS_AUTH_URL        = 'https://www.threads.net/oauth/authorize';
const THREADS_SHORT_TOKEN_URL = 'https://graph.threads.net/oauth/access_token';
const THREADS_LONG_TOKEN_URL  = 'https://graph.threads.net/access_token';
const THREADS_SCOPES          = 'threads_basic,threads_content_publish';

function page(string $title, string $bodyHtml): void
{
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font-family:sans-serif;background:#0a0a0f;color:#fff;padding:40px;}
    .box{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);padding:24px;border-radius:12px;max-width:720px;}
    a{color:#ffd100;} code{background:rgba(0,0,0,0.4);padding:2px 6px;border-radius:4px;}
    .ok{color:#4c8;} .err{color:#f66;} .info{background:rgba(0,120,255,0.1);border:1px solid #08f;padding:12px;border-radius:6px;margin:15px 0;}</style></head><body><div class="box">' . $bodyHtml . '</div></body></html>';
    exit;
}

if (empty($config['threads_client_id']) || empty($config['threads_client_secret'])) {
    page('Setup OAuth Threads', '<h2 class="err">⚠️ Mancano le credenziali Client Threads in config.php</h2>
        <p>Assicurati di aver configurato <code>threads_client_id</code> e <code>threads_client_secret</code> in <code>config.php</code>.</p>
        <p>L\'URI di reindirizzamento autorizzato da inserire nel pannello sviluppatori Meta per Threads è:</p>
        <p><code>' . htmlspecialchars($config['threads_redirect_uri']) . '</code></p>');
}

function threadsHttp(string $method, string $url, array $headers = [], ?array $postFields = null): array
{
    $ch = curl_init();
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        if ($postFields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }
    } else {
        $queryUrl = $url;
        if ($postFields !== null) {
            $queryUrl .= '?' . http_build_query($postFields);
        }
        curl_setopt($ch, CURLOPT_URL, $queryUrl);
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $err) {
        throw new Exception("Errore di rete verso Threads: $err");
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['error']['message'] ?? $data['error_message'] ?? $response;
        throw new Exception("Errore Threads API ($httpCode): $msg");
    }

    return is_array($data) ? $data : [];
}

if (isset($_GET['error'])) {
    page('Setup OAuth Threads', '<h2 class="err">⚠️ Autorizzazione negata da Threads</h2>
        <p>Errore: <code>' . htmlspecialchars($_GET['error']) . '</code></p>
        <p>Descrizione: <code>' . htmlspecialchars($_GET['error_description'] ?? '') . '</code></p>
        <p><a href="oauth_setup_threads.php">Riprova</a></p>');
}

if (isset($_GET['code'])) {
    // 1. Scambia il codice per un token a breve termine
    try {
        $code = str_replace('#_', '', $_GET['code']);
        
        $shortTokenData = threadsHttp('POST', THREADS_SHORT_TOKEN_URL, [], [
            'client_id'     => $config['threads_client_id'],
            'client_secret' => $config['threads_client_secret'],
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $config['threads_redirect_uri'],
            'code'          => $code,
        ]);

        if (empty($shortTokenData['access_token'])) {
            throw new Exception("Nessun access token a breve termine restituito da Threads.");
        }

        // 2. Scambia il token a breve termine con uno a lungo termine (60 giorni)
        $longTokenData = threadsHttp('GET', THREADS_LONG_TOKEN_URL, [], [
            'grant_type'    => 'th_exchange_token',
            'client_secret' => $config['threads_client_secret'],
            'access_token'  => $shortTokenData['access_token'],
        ]);

        if (empty($longTokenData['access_token'])) {
            throw new Exception("Nessun access token a lungo termine restituito.");
        }

        // Recupera le info utente (User ID e username)
        $userInfo = threadsHttp('GET', 'https://graph.threads.net/v1.0/me', [
            'Authorization: Bearer ' . $longTokenData['access_token']
        ], [
            'fields' => 'id,username'
        ]);

        $tokenPayload = [
            'access_token' => $longTokenData['access_token'],
            'expires_in'   => $longTokenData['expires_in'] ?? 5184000, // di default 60 giorni
            'obtained_at'  => time(),
            'user_id'      => $userInfo['id'] ?? $shortTokenData['user_id'] ?? null,
            'username'     => $userInfo['username'] ?? null,
        ];

        // Crea la cartella credentials se non esiste
        if (!is_dir(dirname($config['threads_oauth_token_json']))) {
            mkdir(dirname($config['threads_oauth_token_json']), 0777, true);
        }

        file_put_contents($config['threads_oauth_token_json'], json_encode($tokenPayload, JSON_PRETTY_PRINT));

        $userMessage = '';
        if (!empty($tokenPayload['username'])) {
            $userMessage = '<div class="info">
                <p>Profilo Threads associato: <strong>@' . htmlspecialchars($tokenPayload['username']) . '</strong></p>
                <p>User ID: <code>' . htmlspecialchars($tokenPayload['user_id']) . '</code></p>
            </div>';
        }

        page('Setup OAuth Threads', '<h2 class="ok">✅ Autorizzazione completata con successo!</h2>
            <p>Il token Threads a lungo termine è stato salvato in <code>' . htmlspecialchars(basename($config['threads_oauth_token_json'])) . '</code>.</p>'
            . $userMessage .
            '<p><a href="index.php">&rarr; Torna al generatore</a></p>');

    } catch (Exception $e) {
        page('Setup OAuth Threads', '<h2 class="err">⚠️ Errore nel completamento dell\'autenticazione Threads</h2>
            <pre>' . htmlspecialchars($e->getMessage()) . '</pre>
            <p><a href="oauth_setup_threads.php">Riprova</a></p>');
    }
}

// Avvia il flusso OAuth
$authUrl = THREADS_AUTH_URL . '?' . http_build_query([
    'client_id'     => $config['threads_client_id'],
    'redirect_uri'  => $config['threads_redirect_uri'],
    'scope'         => THREADS_SCOPES,
    'response_type' => 'code',
    'state'         => bin2hex(random_bytes(8)),
]);

header('Location: ' . $authUrl);
exit;
