<?php
/**
 * Pubblicazione diretta dei post Facebook tramite Meta Graph API.
 * Usa il Page ID e il Page Access Token presenti in social/config.php sul server Aruba.
 */

function publishPhotoToFacebookPage(string $imagePath, string $message, ?string $linkUrl, array $config): array
{
    $pageId = trim((string)($config['facebook_page_id'] ?? ''));
    $accessToken = trim((string)($config['facebook_page_access_token'] ?? ''));

    if ($pageId === '' || $accessToken === '') {
        throw new Exception('Facebook Page ID o Page Access Token non configurati.');
    }

    $realImagePath = realpath($imagePath);
    if ($realImagePath === false || !is_file($realImagePath)) {
        throw new Exception('Immagine generata non trovata sul server.');
    }

    // Consenti l'upload solo dalla cartella immagini configurata.
    $outputDir = realpath((string)($config['output_images_dir'] ?? ''));
    if ($outputDir !== false) {
        $allowedPrefix = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($realImagePath, $allowedPrefix) !== 0) {
            throw new Exception('Percorso immagine non autorizzato per la pubblicazione Facebook.');
        }
    }

    $fullMessage = trim($message);
    if (!empty($linkUrl) && strpos($fullMessage, $linkUrl) === false) {
        $fullMessage = rtrim($fullMessage) . "\n\n" . $linkUrl;
    }

    $graphVersion = trim((string)($config['facebook_graph_version'] ?? ''));
    $baseUrl = 'https://graph.facebook.com';
    if ($graphVersion !== '') {
        $baseUrl .= '/' . ltrim($graphVersion, '/');
    }
    $endpoint = $baseUrl . '/' . rawurlencode($pageId) . '/photos';

    $mime = function_exists('mime_content_type') ? (mime_content_type($realImagePath) ?: 'image/jpeg') : 'image/jpeg';
    $payload = [
        'source'       => new CURLFile($realImagePath, $mime, basename($realImagePath)),
        'message'      => $fullMessage,
        'published'    => 'true',
        'access_token' => $accessToken,
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        throw new Exception('Errore di connessione verso Meta: ' . $curlError);
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400 || !empty($data['error'])) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        throw new Exception('Meta Facebook API: ' . $msg);
    }

    return [
        'id'       => $data['id'] ?? null,
        'post_id'  => $data['post_id'] ?? null,
        'platform' => 'Facebook Graph API',
    ];
}
