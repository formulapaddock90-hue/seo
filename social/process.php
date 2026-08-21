<?php
/**
 * process.php — Motore di generazione contenuti social FormulaPaddock & Visual Studio HD
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('max_execution_time', 300);
// In produzione non mostrare warning/deprecation sopra l'interfaccia.
// Gli errori applicativi vengono comunque gestiti da renderError().
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/url_extractor.php';
require_once __DIR__ . '/includes/ai_generator.php';
require_once __DIR__ . '/includes/image_generator.php';
require_once __DIR__ . '/includes/google_service.php';

$config = require __DIR__ . '/config.php';
$cloudUrl = $config['reel_cloud_url'] ?? 'https://reel-engine-dcnr.onrender.com';

function renderError(string $message): void
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>Errore</title>';
    echo '<style>body{font-family:sans-serif;background:#1a0a0a;color:#fff;padding:40px;}
    .box{background:#2a1010;border:1px solid #b33;padding:20px;border-radius:8px;max-width:700px;}
    a{color:#ffd100;}</style></head><body>';
    echo '<div class="box"><h2>⚠️ Si e\' verificato un errore</h2><pre style="white-space:pre-wrap;">'
        . htmlspecialchars($message) . '</pre>';
    echo '<p><a href="index.php">&larr; Torna indietro</a></p></div></body></html>';
    exit;
}

try {
    $input = trim($_GET['url'] ?? trim($_POST['input_text'] ?? ''));

    if ($input === '') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET['url'])) {
            header('Location: index.php');
            exit;
        }
        throw new Exception('Nessun testo o URL fornito.');
    }

    $sourceUrl = '';
    $title = '';
    $sourceText = '';
    $articleUrlInput = trim($_POST['article_url'] ?? $_GET['article_url'] ?? '');

    $bgImageUrl = null;
    if (isValidUrl($input)) {
        $extracted = extractTextFromUrl($input);
        $sourceUrl = $extracted['source_url'];
        $title = $extracted['title'];
        $sourceText = $extracted['text'];
        $bgImageUrl = $extracted['image_url'] ?? null;
    } else {
        $sourceText = $input;
        if (isValidUrl($articleUrlInput)) {
            $sourceUrl = resolveInputUrl($articleUrlInput);
            try {
                $extraExtracted = extractTextFromUrl($sourceUrl);
                $bgImageUrl = $extraExtracted['image_url'] ?? null;
            } catch (Throwable $e) {}
        } else if (preg_match('/https?:\/\/[^\s<"\']+/', $input, $urlMatch)) {
            $sourceUrl = $urlMatch[0];
            try {
                $extraExtracted = extractTextFromUrl($sourceUrl);
                $bgImageUrl = $extraExtracted['image_url'] ?? null;
            } catch (Throwable $e) {}
        } else {
            $sourceUrl = '';
        }
        $firstSentence = preg_split('/(?<=[.!?])\s+/u', $input)[0] ?? $input;
        $title = mb_substr($firstSentence, 0, 80);
    }

    // STEP 3: Generazione testi AI (Gemini)
    $content = generateSocialContent($sourceText, $title, $config);

    // STEP 4: Generazione infografica Formula Paddock Visual Studio HD (1080x1080)
    $slug = 'post_' . date('Ymd_His') . '_' . substr(md5($title . microtime()), 0, 6);
    $images = generateAllInfographics($content, $slug, $config, $bgImageUrl);

    // STEP 5: Upload Infografica su Google Drive (Folder ID: 1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K)
    $driveErrors = [];
    $hdImageDrive = null;

    try {
        $hdImageDrive = uploadFileToDrive($images['hd_image'] ?? $images['fb_image'], 'image/jpeg', $config);
    } catch (Throwable $e) {
        $driveErrors[] = 'Upload infografica Visual Studio HD: ' . $e->getMessage();
    }
    $fbImageDrive = $hdImageDrive;
    $igImageDrive = $hdImageDrive;

    // STEP 6: Scrittura riga su Google Sheets
    $sheetError = null;
    try {
        appendRowToSheet([
            'data'               => date('Y-m-d H:i:s'),
            'facebook'           => $content['facebook'],
            'twitter'            => $content['twitter'],
            'linkedin'           => $content['linkedin'],
            'instagram'          => $hdImageDrive['view_link'] ?? ($content['infografica_titolo'] . ' - ' . $content['infografica_sottotitolo']),
            'categoria'          => $content['categoria'],
            'img_evidenza'       => $hdImageDrive['view_link'] ?? '',
            'twitter_modificato' => $content['twitter_modificato'] ?? ($content['twitter'] ?? ''),
            'link'               => $sourceUrl !== '' ? $sourceUrl : 'https://www.formulapaddock.it',
        ], $config);
    } catch (Throwable $e) {
        $sheetError = $e->getMessage();
    }

    // STEP 7: Pubblicazione automatica opzionale (disattivata di default; i pulsanti pubblicano su richiesta)
    $bufferResults = [];
    $bufferErrors = [];

    if (!empty($config['buffer_auto_publish']) && !empty($config['buffer_access_token'])) {
        require_once __DIR__ . '/includes/buffer_service.php';
        $linkToPublish = $sourceUrl !== '' ? $sourceUrl : null;

        if (!empty($content['facebook'])) {
            try {
                $resFb = publishToBuffer($content['facebook'], 'facebook', $linkToPublish, $config);
                $modeLabel = ($config['buffer_share_mode'] ?? 'shareNow') === 'shareNow' ? 'Pubblicato ORA' : 'Inviato in coda';
                $bufferResults[] = "Facebook (Buffer): {$modeLabel}";
            } catch (Throwable $e) {
                $bufferErrors[] = "Buffer Facebook: " . $e->getMessage();
            }
        }

        if (!empty($content['twitter'])) {
            try {
                $resTw = publishToBuffer($content['twitter'], 'twitter', $linkToPublish, $config);
                $modeLabel = ($config['buffer_share_mode'] ?? 'shareNow') === 'shareNow' ? 'Pubblicato ORA' : 'Inviato in coda';
                $bufferResults[] = "Twitter/X (Buffer): {$modeLabel}";
            } catch (Throwable $e) {
                $bufferErrors[] = "Buffer Twitter: " . $e->getMessage();
            }
        }
    }

    // Target URL del Reel Engine Cloud con articolo pre-caricato
    $reelTargetUrl = $cloudUrl . '/?url=' . urlencode($sourceUrl !== '' ? $sourceUrl : 'https://www.formulapaddock.it');

    // Salvataggio in sessione per output.php
    $_SESSION['last_social_content'] = $content;
    $_SESSION['last_social_images'] = $images;
    $_SESSION['last_social_title'] = $title;
    $_SESSION['last_social_source_url'] = $sourceUrl;
    $_SESSION['last_social_sheet_error'] = $sheetError;
    $_SESSION['last_social_buffer_results'] = $bufferResults;
    $_SESSION['last_social_buffer_errors'] = $bufferErrors;
    $_SESSION['last_social_drive_errors'] = $driveErrors;
    $_SESSION['last_social_hd_drive'] = $hdImageDrive;
    $_SESSION['last_social_fb_drive'] = $fbImageDrive;
    $_SESSION['last_social_ig_drive'] = $igImageDrive;

    // Renderizza la dashboard output.php
    require __DIR__ . '/output.php';
    exit;

} catch (Throwable $e) {
    renderError($e->getMessage());
}
