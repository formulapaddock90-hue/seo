<?php
/**
 * publish_ajax.php — Endpoint AJAX per la pubblicazione su singoli canali social
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$config = require __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo non consentito. Richiesto POST.']);
    exit;
}

$channel = trim((string)($_POST['channel'] ?? ''));
$text = trim((string)($_POST['text'] ?? ''));
$link = trim((string)($_POST['link'] ?? ''));
$imageKey = trim((string)($_POST['image_key'] ?? ''));
if ($link === '') $link = null;

if ($channel === '') {
    echo json_encode(['ok' => false, 'error' => 'Canale social non specificato.']);
    exit;
}

function currentSocialReel(): string
{
    $videoPath = trim((string)($_SESSION['last_social_reel'] ?? ''));
    $reelSource = trim((string)($_SESSION['last_social_reel_source_url'] ?? ''));
    $currentSource = trim((string)($_SESSION['last_social_source_url'] ?? ''));

    if ($videoPath === '' || !is_file($videoPath)) {
        throw new Exception('Il Reel della notizia corrente non è ancora disponibile. Genera e salva prima il Reel.');
    }
    if ($reelSource !== $currentSource) {
        throw new Exception('Il Reel salvato appartiene a una notizia diversa. Rigenera il Reel corrente.');
    }

    $reelsDir = realpath(__DIR__ . '/output/reels');
    $realVideo = realpath($videoPath);
    if ($reelsDir === false || $realVideo === false || !str_starts_with($realVideo, $reelsDir . DIRECTORY_SEPARATOR)) {
        throw new Exception('Percorso Reel non valido.');
    }

    return $realVideo;
}

try {
    $result = [];

    switch ($channel) {
        case 'facebook':
            if ($text === '') throw new Exception('Testo post Facebook mancante.');

            $imagePath = '';
            if ($imageKey !== '') {
                $allowedKeys = ['top3', 'ferrari', 'top10'];
                if (!in_array($imageKey, $allowedKeys, true)) throw new Exception('Infografica Live non riconosciuta.');
                $liveImages = $_SESSION['last_live_social_images'] ?? [];
                $imagePath = (string)($liveImages[$imageKey] ?? '');
            }
            if ($imagePath === '') {
                $images = $_SESSION['last_social_images'] ?? [];
                $imagePath = $images['hd_image'] ?? ($images['fb_image'] ?? ($images['ig_image'] ?? ''));
            }
            if ($imagePath === '' || !file_exists($imagePath)) {
                throw new Exception('Immagine generata non disponibile. Rigenera prima il contenuto social.');
            }

            require_once __DIR__ . '/includes/facebook_page_service.php';
            $res = publishPhotoToFacebookPage($imagePath, $text, $link, $config);
            $result = ['ok' => true, 'channel' => 'facebook', 'image_key' => $imageKey, 'message' => 'Post con immagine pubblicato su Facebook tramite Meta API!', 'detail' => $res];
            break;

        case 'facebook_reel':
            $videoPath = currentSocialReel();
            require_once __DIR__ . '/includes/facebook_reels_service.php';
            $caption = $text !== '' ? $text : 'Formula 1 News #f1 #formula1 #formulapaddock';
            $res = publishReelToFacebook($videoPath, $caption, $config);
            $result = ['ok' => true, 'channel' => 'facebook_reel', 'message' => 'Reel pubblicato su Facebook tramite Meta API!', 'detail' => $res];
            break;

        case 'twitter':
        case 'x':
            if ($text === '') throw new Exception('Testo post Twitter / X mancante.');
            require_once __DIR__ . '/includes/buffer_service.php';
            $res = publishToBuffer($text, 'twitter', $link, $config);
            $result = ['ok' => true, 'channel' => 'twitter', 'message' => 'Post pubblicato con successo su Twitter / X (Buffer)!', 'detail' => $res];
            break;

        case 'threads':
            if ($text === '') throw new Exception('Testo post Threads mancante.');
            require_once __DIR__ . '/includes/threads_service.php';
            $res = publishToThreads($text, $link, $config);
            $result = ['ok' => true, 'channel' => 'threads', 'message' => 'Post pubblicato con successo su Threads!', 'detail' => $res];
            break;

        case 'linkedin':
            if ($text === '') throw new Exception('Testo post LinkedIn mancante.');
            $res = null;
            try {
                require_once __DIR__ . '/includes/buffer_service.php';
                $res = publishToBuffer($text, 'linkedin', $link, $config);
            } catch (Throwable $eBuffer) {
                if (!empty($config['linkedin_author_urn']) && file_exists($config['linkedin_oauth_token_json'])) {
                    require_once __DIR__ . '/includes/linkedin_service.php';
                    $res = publishToLinkedIn($text, $link, $config);
                } else {
                    throw new Exception('LinkedIn non configurato: ' . $eBuffer->getMessage());
                }
            }
            $result = ['ok' => true, 'channel' => 'linkedin', 'message' => 'Post pubblicato con successo su LinkedIn!', 'detail' => $res];
            break;

        case 'tiktok':
            $videoPath = currentSocialReel();
            require_once __DIR__ . '/includes/tiktok_service.php';
            $caption = $text !== '' ? $text : 'Formula 1 News #f1 #formula1 #formulapaddock';
            $res = publishReelToTikTok($videoPath, $caption, $config);
            $result = ['ok' => true, 'channel' => 'tiktok', 'message' => 'Reel corrente inviato con successo a TikTok!', 'detail' => $res];
            break;

        default:
            throw new Exception("Canale social '{$channel}' non riconosciuto.");
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'channel' => $channel,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
