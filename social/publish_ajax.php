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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo non consentito. Richiesto POST.']);
    exit;
}

$channel = trim($_POST['channel'] ?? '');
$text    = trim($_POST['text'] ?? '');
$link    = trim($_POST['link'] ?? '');
if ($link === '') {
    $link = null;
}

if ($channel === '') {
    echo json_encode(['ok' => false, 'error' => 'Canale social non specificato.']);
    exit;
}

try {
    $result = [];

    switch ($channel) {
        case 'facebook':
            if (empty($text)) {
                throw new Exception('Testo post Facebook mancante.');
            }

            $images = $_SESSION['last_social_images'] ?? [];
            $imagePath = $images['hd_image'] ?? ($images['fb_image'] ?? ($images['ig_image'] ?? ''));
            if ($imagePath === '' || !file_exists($imagePath)) {
                throw new Exception('Immagine HD generata non disponibile. Rigenera prima il contenuto social.');
            }

            require_once __DIR__ . '/includes/facebook_page_service.php';
            $res = publishPhotoToFacebookPage($imagePath, $text, $link, $config);
            $result = [
                'ok'      => true,
                'channel' => 'facebook',
                'message' => 'Post con immagine pubblicato su Facebook tramite Meta API!',
                'detail'  => $res
            ];
            break;

        case 'twitter':
        case 'x':
            if (empty($text)) {
                throw new Exception('Testo post Twitter / X mancante.');
            }
            require_once __DIR__ . '/includes/buffer_service.php';
            $res = publishToBuffer($text, 'twitter', $link, $config);
            $result = [
                'ok'      => true,
                'channel' => 'twitter',
                'message' => 'Post pubblicato con successo su Twitter / X (Buffer)!',
                'detail'  => $res
            ];
            break;

        case 'threads':
            if (empty($text)) {
                throw new Exception('Testo post Threads mancante.');
            }
            require_once __DIR__ . '/includes/threads_service.php';
            $res = publishToThreads($text, $link, $config);
            $result = [
                'ok'      => true,
                'channel' => 'threads',
                'message' => 'Post pubblicato con successo su Threads!',
                'detail'  => $res
            ];
            break;

        case 'linkedin':
            if (empty($text)) {
                throw new Exception('Testo post LinkedIn mancante.');
            }
            $res = null;
            try {
                require_once __DIR__ . '/includes/buffer_service.php';
                $res = publishToBuffer($text, 'linkedin', $link, $config);
            } catch (Throwable $eBuffer) {
                if (!empty($config['linkedin_author_urn']) && file_exists($config['linkedin_oauth_token_json'])) {
                    require_once __DIR__ . '/includes/linkedin_service.php';
                    $res = publishToLinkedIn($text, $link, $config);
                } else {
                    throw new Exception("LinkedIn non configurato: " . $eBuffer->getMessage());
                }
            }

            $result = [
                'ok'      => true,
                'channel' => 'linkedin',
                'message' => 'Post pubblicato con successo su LinkedIn!',
                'detail'  => $res
            ];
            break;

        case 'tiktok':
            $videoPath = trim($_POST['video_path'] ?? '');
            if ($videoPath === '' || !file_exists($videoPath)) {
                $reelsDir = $config['output_reels_dir'] ?? (__DIR__ . '/output/reels');
                $files = glob($reelsDir . '/*.mp4');
                if (!empty($files)) {
                    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                    $videoPath = $files[0];
                }
            }

            if (empty($videoPath) || !file_exists($videoPath)) {
                throw new Exception('Nessun video Reel MP4 trovato per la pubblicazione su TikTok. Registralo prima nel Reel Engine.');
            }

            require_once __DIR__ . '/includes/tiktok_service.php';
            $caption = !empty($text) ? $text : 'Formula 1 News #f1';
            $res = publishReelToTikTok($videoPath, $caption, $config);

            $result = [
                'ok'      => true,
                'channel' => 'tiktok',
                'message' => 'Video inviato con successo a TikTok!',
                'detail'  => $res
            ];
            break;

        default:
            throw new Exception("Canale social '{$channel}' non riconosciuto.");
    }

    echo json_encode($result);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'channel' => $channel,
        'error'   => $e->getMessage()
    ]);
}
