<?php
/**
 * threads_service.php
 *
 * Servizio di pubblicazione nativa su Threads tramite Meta Threads API.
 */

function threadsApiRequest(string $method, string $url, array $params = []): array
{
    $ch = curl_init();
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } else {
        $queryUrl = $url;
        if (!empty($params)) {
            $queryUrl .= '?' . http_build_query($params);
        }
        curl_setopt($ch, CURLOPT_URL, $queryUrl);
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $err) {
        throw new Exception("Errore di rete verso Threads API: $err");
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['error']['message'] ?? $response;
        throw new Exception("Errore API Threads ($httpCode): $msg");
    }

    return is_array($data) ? $data : [];
}

function getThreadsAccessToken(array $config): array
{
    $tokenFile = $config['threads_oauth_token_json'];
    if (!file_exists($tokenFile)) {
        throw new Exception('Token OAuth Threads non trovato. Esegui la configurazione su oauth_setup_threads.php');
    }

    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if (empty($tokenData['access_token'])) {
        throw new Exception('Token di accesso non valido in threads-token.json');
    }

    $obtained = $tokenData['obtained_at'] ?? 0;
    $expiresIn = $tokenData['expires_in'] ?? 5184000;
    
    // Se mancano meno di 10 giorni alla scadenza, prova a fare il refresh automatico
    if (time() > ($obtained + $expiresIn - 864000)) {
        try {
            $refreshData = threadsApiRequest('GET', 'https://graph.threads.net/refresh_access_token', [
                'grant_type'   => 'th_refresh_token',
                'access_token' => $tokenData['access_token']
            ]);
            
            if (!empty($refreshData['access_token'])) {
                $tokenData['access_token'] = $refreshData['access_token'];
                $tokenData['expires_in']   = $refreshData['expires_in'] ?? 5184000;
                $tokenData['obtained_at']  = time();
                
                file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));
            }
        } catch (Throwable $e) {
            // Se fallisce il refresh e il token è proprio scaduto, lancia eccezione
            if (time() > ($obtained + $expiresIn - 300)) {
                throw new Exception('Il token Threads è scaduto e il refresh automatico è fallito. Riavvia oauth_setup_threads.php. Dettaglio: ' . $e->getMessage());
            }
        }
    }

    return $tokenData;
}

function publishToThreads(string $text, ?string $linkUrl, array $config): array
{
    $tokenData = getThreadsAccessToken($config);
    $accessToken = $tokenData['access_token'];
    $userId = $tokenData['user_id'];
    
    if (empty($userId)) {
        throw new Exception('User ID Threads non trovato nel file del token.');
    }

    $fullText = $text;
    if (!empty($linkUrl)) {
        if (strpos($text, $linkUrl) === false) {
            $fullText = rtrim($text) . "\n\n" . $linkUrl;
        }
    }

    // STEP 1: Creazione del contenitore multimediale (media container)
    $containerUrl = "https://graph.threads.net/v1.0/{$userId}/threads";
    $containerParams = [
        'media_type'   => 'TEXT',
        'text'         => $fullText,
        'access_token' => $accessToken
    ];
    
    $containerRes = threadsApiRequest('POST', $containerUrl, $containerParams);
    $creationId = $containerRes['id'] ?? null;
    
    if (empty($creationId)) {
        throw new Exception('Impossibile creare il media container su Threads.');
    }

    // Attendi un istante per l'elaborazione interna prima di pubblicare
    usleep(500000); // 0.5 secondi

    // STEP 2: Pubblicazione del contenitore
    $publishUrl = "https://graph.threads.net/v1.0/{$userId}/threads_publish";
    $publishParams = [
        'creation_id'  => $creationId,
        'access_token' => $accessToken
    ];
    
    $publishRes = threadsApiRequest('POST', $publishUrl, $publishParams);
    
    if (empty($publishRes['id'])) {
        throw new Exception('La pubblicazione del post su Threads è fallita.');
    }

    return [
        'status'  => 'success',
        'id'      => $publishRes['id'],
        'message' => 'Post pubblicato con successo su Threads.'
    ];
}
