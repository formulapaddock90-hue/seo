<?php
/**
 * Servizio di Pubblicazione Nativa Diretta su Facebook Reels & Instagram Reels
 * via Meta Graph API v19.0
 */

function publishReelToFacebook(string $videoPath, string $caption, array $config): array
{
    $pageAccessToken = $config['facebook_page_access_token'] ?? '';
    $pageId = $config['facebook_page_id'] ?? '';

    if (empty($pageAccessToken) || empty($pageId)) {
        throw new Exception("Facebook Page ID o Access Token non configurati per Reels Nativo.");
    }

    if (!file_exists($videoPath)) {
        throw new Exception("File video Reel non trovato: $videoPath");
    }

    $initUrl = "https://graph.facebook.com/v19.0/{$pageId}/video_reels";
    $ch = curl_init($initUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'upload_phase' => 'start',
            'access_token' => $pageAccessToken,
        ]),
    ]);

    $initRes = curl_exec($ch);
    curl_close($ch);
    $initData = json_decode($initRes, true);

    if (empty($initData['video_id']) || empty($initData['upload_url'])) {
        $msg = $initData['error']['message'] ?? 'Errore inizializzazione Reel Facebook.';
        throw new Exception("Errore Meta Reels API (Step 1): $msg");
    }

    $videoId = $initData['video_id'];
    $uploadUrl = $initData['upload_url'];

    $fileSize = filesize($videoPath);
    $videoHandle = fopen($videoPath, 'rb');
    $videoContent = fread($videoHandle, $fileSize);
    fclose($videoHandle);

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $videoContent,
        CURLOPT_HTTPHEADER     => [
            'Authorization: OAuth ' . $pageAccessToken,
            'offset: 0',
            'file_size: ' . $fileSize,
            'Content-Type: application/octet-stream',
        ],
    ]);

    $uploadRes = curl_exec($ch);
    curl_close($ch);

    $ch = curl_init($initUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'upload_phase'     => 'finish',
            'video_id'         => $videoId,
            'video_state'      => 'PUBLISHED',
            'description'      => $caption,
            'access_token'     => $pageAccessToken,
        ]),
    ]);

    $finishRes = curl_exec($ch);
    curl_close($ch);
    $finishData = json_decode($finishRes, true);

    if (!isset($finishData['success']) || !$finishData['success']) {
        $msg = $finishData['error']['message'] ?? 'Errore finalizzazione Reel Facebook.';
        throw new Exception("Errore Meta Reels API (Step 3): $msg");
    }

    return [
        'video_id' => $videoId,
        'status'   => 'PUBLISHED',
        'platform' => 'Facebook Reels Nativo'
    ];
}
