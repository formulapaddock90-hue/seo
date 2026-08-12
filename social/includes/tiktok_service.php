<?php
/**
 * Servizio di Pubblicazione Nativa Diretta su TikTok
 * via TikTok Content Posting API v2
 */

function publishReelToTikTok(string $videoPath, string $caption, array $config): array
{
    $token = $config['tiktok_creator_token'] ?? '';
    if (empty($token)) {
        throw new Exception("TikTok Access Token non configurato in config.php");
    }

    if (!file_exists($videoPath)) {
        throw new Exception("File video Reel non trovato: $videoPath");
    }

    $fileSize = filesize($videoPath);

    $initUrl = "https://open.tiktokapis.com/v2/post/publish/video/init/";
    $payload = json_encode([
        'post_info' => [
            'title'                 => mb_substr($caption, 0, 150),
            'privacy_level'         => 'PUBLIC_TO_EVERYONE',
            'disable_duet'          => false,
            'disable_stitch'        => false,
            'disable_comment'       => false,
            'video_cover_timestamp_ms' => 1000
        ],
        'source_info' => [
            'source'            => 'FILE_UPLOAD',
            'video_size'        => $fileSize,
            'chunk_size'        => $fileSize,
            'total_chunk_count' => 1
        ]
    ]);

    $ch = curl_init($initUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8'
        ]
    ]);

    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);

    if (empty($data['data']['publish_id']) || empty($data['data']['upload_url'])) {
        $msg = $data['error']['message'] ?? 'Errore inizializzazione TikTok API.';
        throw new Exception("Errore TikTok API: $msg");
    }

    $uploadUrl = $data['data']['upload_url'];
    $publishId = $data['data']['publish_id'];

    $videoHandle = fopen($videoPath, 'rb');
    $videoData = fread($videoHandle, $fileSize);
    fclose($videoHandle);

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PUT            => true,
        CURLOPT_POSTFIELDS     => $videoData,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: video/mp4',
            'Content-Length: ' . $fileSize,
            'Content-Range: bytes 0-' . ($fileSize - 1) . '/' . $fileSize
        ]
    ]);

    $uploadRes = curl_exec($ch);
    curl_close($ch);

    return [
        'publish_id' => $publishId,
        'status'     => 'PUBLISHED',
        'platform'   => 'TikTok Nativo'
    ];
}
