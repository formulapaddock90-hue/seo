<?php
/**
 * process.php — Motore di generazione contenuti social FormulaPaddock & Visual Studio HD
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('max_execution_time', 300);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/url_extractor.php';
require_once __DIR__ . '/includes/ai_generator.php';
require_once __DIR__ . '/includes/image_generator.php';
require_once __DIR__ . '/includes/live_image_generator.php';
require_once __DIR__ . '/includes/google_service.php';

$config = require __DIR__ . '/config.php';
$cloudUrl = $config['reel_cloud_url'] ?? 'https://reel-engine-dcnr.onrender.com';

function requestExpectsJson(): bool
{
    $format = strtolower(trim((string)($_GET['format'] ?? '')));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $fetchDest = strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($format === 'json') return true;
    if (strpos($contentType, 'application/json') !== false) return true;
    if (strpos($accept, 'application/json') !== false) return true;
    if ($requestedWith === 'xmlhttprequest') return true;

    // I fetch() moderni usano normalmente Sec-Fetch-Dest: empty,
    // mentre l'invio classico del form usa Sec-Fetch-Dest: document.
    if ($method === 'POST' && $fetchDest === 'empty') return true;

    return false;
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    $encoded = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    if ($encoded === false) {
        $encoded = '{"ok":false,"error":"Errore durante la codifica della risposta JSON."}';
    }

    echo $encoded;
    exit;
}

function readJsonPayload(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') === false) return [];

    $raw = trim((string)file_get_contents('php://input'));
    if ($raw === '') return [];

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new Exception('Payload JSON non valido.');
    }

    return $data;
}

function renderError(string $message, bool $asJson = false): void
{
    if ($asJson) {
        jsonResponse([
            'ok' => false,
            'error' => $message,
        ], 500);
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>Errore</title>';
    echo '<style>body{font-family:sans-serif;background:#1a0a0a;color:#fff;padding:40px;}
    .box{background:#2a1010;border:1px solid #b33;padding:20px;border-radius:8px;max-width:700px;}
    a{color:#ffd100;}</style></head><body>';
    echo '<div class="box"><h2>⚠️ Si e\' verificato un errore</h2><pre style="white-space:pre-wrap;">'
        . htmlspecialchars($message) . '</pre>';
    echo '<p><a href="index.php">&larr; Torna indietro</a></p></div></body></html>';
    exit;
}

function loadFreshLiveSocialContext(): ?array
{
    $file = dirname(__DIR__) . '/storage/live-social-context.json';
    if (!is_file($file)) return null;
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data) || empty($data['live'])) return null;
    $savedAt = (int)($data['saved_at'] ?? 0);
    if ($savedAt <= 0 || (time() - $savedAt) > 1800) return null;
    return $data;
}

$expectsJson = requestExpectsJson();

try {
    $jsonPayload = readJsonPayload();

    $input = trim((string)(
        $_GET['url']
        ?? $_POST['input_text']
        ?? $jsonPayload['input_text']
        ?? $jsonPayload['url']
        ?? ''
    ));

    if ($input === '') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && empty($_GET['url']) && !$expectsJson) {
            header('Location: index.php');
            exit;
        }
        throw new Exception('Nessun testo o URL fornito.');
    }

    $sourceUrl = '';
    $title = '';
    $sourceText = '';
    $articleUrlInput = trim((string)(
        $_POST['article_url']
        ?? $_GET['article_url']
        ?? $jsonPayload['article_url']
        ?? ''
    ));

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

    // STEP 4: Infografica standard + eventuali tre grafiche Sessione Live
    $slug = 'post_' . date('Ymd_His') . '_' . substr(md5($title . microtime()), 0, 6);
    $images = generateAllInfographics($content, $slug, $config, $bgImageUrl);

    $liveContext = loadFreshLiveSocialContext();
    $liveImages = [];
    if ($liveContext) {
        $liveImages = generateLiveSessionInfographics($liveContext, $config);
    }

    // STEP 5: Upload infografiche su Google Drive
    $driveErrors = [];
    $hdImageDrive = null;
    $liveDrive = [];

    try {
        $hdImageDrive = uploadFileToDrive($images['hd_image'] ?? $images['fb_image'], 'image/jpeg', $config);
    } catch (Throwable $e) {
        $driveErrors[] = 'Upload infografica Visual Studio HD: ' . $e->getMessage();
    }

    if ($liveImages) {
        foreach (['top3', 'ferrari', 'top10'] as $key) {
            if (empty($liveImages[$key]) || !is_file($liveImages[$key])) continue;
            try {
                $liveDrive[$key] = uploadFileToDrive($liveImages[$key], 'image/jpeg', $config);
            } catch (Throwable $e) {
                $driveErrors[] = 'Upload infografica Live ' . $key . ': ' . $e->getMessage();
            }
        }
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

    // STEP 7: Pubblicazione automatica opzionale
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

    $reelTargetUrl = $cloudUrl . '/?url=' . urlencode($sourceUrl !== '' ? $sourceUrl : 'https://www.formulapaddock.it');

    // Salvataggio in sessione per output/publish_ajax
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
    $_SESSION['last_live_social_context'] = $liveContext;
    $_SESSION['last_live_social_images'] = $liveImages;
    $_SESSION['last_live_social_drive'] = $liveDrive;

    if ($expectsJson) {
        jsonResponse([
            'ok' => true,
            'title' => $title,
            'source_url' => $sourceUrl,
            'content' => $content,
            'reel_url' => $reelTargetUrl,
            'redirect' => ($liveContext && $liveImages) ? 'output-live.php' : 'output.php',
            'live' => (bool)($liveContext && $liveImages),
            'drive' => [
                'hd_image' => $hdImageDrive['view_link'] ?? null,
                'live' => $liveDrive,
            ],
            'warnings' => [
                'sheet' => $sheetError,
                'drive' => $driveErrors,
                'buffer' => $bufferErrors,
            ],
        ]);
    }

    // In modalità Live apre il pannello dedicato con le tre infografiche.
    if ($liveContext && $liveImages) {
        require __DIR__ . '/output-live.php';
    } else {
        require __DIR__ . '/output.php';
    }
    exit;

} catch (Throwable $e) {
    renderError($e->getMessage(), $expectsJson);
}
