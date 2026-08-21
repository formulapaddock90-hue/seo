<?php
/**
 * linkedin_service.php
 *
 * Servizio di pubblicazione nativa su LinkedIn tramite il Posts API (/rest/posts).
 */

function linkedinRequest(string $method, string $url, array $headers = [], ?string $body = null): array
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

    // Le risposte di creazione (POST) a volte sono vuote con codice 201 Created.
    if ($httpCode === 201) {
        return ['status' => 'success', 'code' => 201];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['message'] ?? $response;
        throw new Exception("Errore API LinkedIn ($httpCode): $msg");
    }

    return is_array($data) ? $data : [];
}

function getLinkedInAccessToken(array $config): string
{
    $tokenFile = $config['linkedin_oauth_token_json'];
    if (!file_exists($tokenFile)) {
        throw new Exception('Token OAuth LinkedIn non trovato. Esegui la configurazione su oauth_setup_linkedin.php');
    }

    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if (empty($tokenData['access_token'])) {
        throw new Exception('Token di accesso non valido in linkedin-token.json');
    }

    // I token di LinkedIn durano tipicamente 60 giorni.
    // Nessun refresh token è fornito a meno che non si sia configurato appositamente,
    // quindi se scade, va rifatto l'oauth_setup.
    $obtained = $tokenData['obtained_at'] ?? 0;
    $expiresIn = $tokenData['expires_in'] ?? 5184000;
    
    if (time() > ($obtained + $expiresIn - 300)) {
        throw new Exception('Il token LinkedIn è scaduto. Per favore ri-autorizza l\'account su oauth_setup_linkedin.php');
    }

    return $tokenData['access_token'];
}

function publishToLinkedIn(string $text, ?string $linkUrl, array $config): array
{
    $token = getLinkedInAccessToken($config);

    if (empty($config['linkedin_author_urn'])) {
        throw new Exception('linkedin_author_urn non configurato in config.php. Deve essere del tipo urn:li:person:ID o urn:li:organization:ID');
    }

    $author = $config['linkedin_author_urn'];
    $fullText = $text;
    
    if (!empty($linkUrl)) {
        if (strpos($text, $linkUrl) === false) {
            $fullText = rtrim($text) . "\n\n" . $linkUrl;
        }
    }

    $headers = [
        'Authorization: Bearer ' . $token,
        'LinkedIn-Version: 202607', // Versione API corrente
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0',
    ];

    $payload = [
        'author'         => $author,
        'commentary'     => $fullText,
        'visibility'     => 'PUBLIC',
        'distribution'   => [
            'feedDistribution' => 'MAIN_FEED'
        ],
        'lifecycleState' => 'PUBLISHED'
    ];

    // Se fornito il link, possiamo indicarlo strutturalmente nell'oggetto content (opzionale)
    // ma la condivisione tramite testo con link incluso è la più stabile e autogenera l'anteprima.
    
    $res = linkedinRequest('POST', 'https://api.linkedin.com/rest/posts', $headers, json_encode($payload));

    return [
        'status'  => 'success',
        'urn'     => $author,
        'message' => 'Post pubblicato con successo su LinkedIn.'
    ];
}
