<?php
/**
 * process.php — Generatore Contenuti Social FormulaPaddock (Collegato al Reel Engine Cloud)
 */

ini_set('max_execution_time', 300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

    if (isValidUrl($input)) {
        $extracted = extractTextFromUrl($input);
        $sourceUrl = $extracted['source_url'];
        $title = $extracted['title'];
        $sourceText = $extracted['text'];
    } else {
        $sourceText = $input;
        if (isValidUrl($articleUrlInput)) {
            $sourceUrl = resolveInputUrl($articleUrlInput);
        } else if (preg_match('/https?:\/\/[^\s<"\']+/', $input, $urlMatch)) {
            $sourceUrl = $urlMatch[0];
        } else {
            $sourceUrl = '';
        }
        $firstSentence = preg_split('/(?<=[.!?])\s+/u', $input)[0] ?? $input;
        $title = mb_substr($firstSentence, 0, 80);
    }

    // STEP 3: Generazione testi AI (Gemini)
    $content = generateSocialContent($sourceText, $title, $config);

    // STEP 4: Generazione infografiche Facebook & Instagram
    $slug = 'post_' . date('Ymd_His') . '_' . substr(md5($title . microtime()), 0, 6);
    $images = generateAllInfographics($content, $slug, $config);

    // STEP 5: Upload Infografiche su Google Drive (Folder ID: 1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K)
    $driveErrors = [];
    $fbImageDrive = null;
    $igImageDrive = null;

    try {
        $fbImageDrive = uploadFileToDrive($images['fb_image'], 'image/jpeg', $config);
    } catch (Throwable $e) {
        $driveErrors[] = 'Upload infografica Facebook: ' . $e->getMessage();
    }

    try {
        $igImageDrive = uploadFileToDrive($images['ig_image'], 'image/jpeg', $config);
    } catch (Throwable $e) {
        $driveErrors[] = 'Upload infografica Instagram: ' . $e->getMessage();
    }

    // STEP 6: Scrittura riga su Google Sheets
    $sheetError = null;
    try {
        appendRowToSheet([
            'data'               => date('Y-m-d H:i:s'),
            'facebook'           => $content['facebook'],
            'twitter'            => $content['twitter'],
            'linkedin'           => $content['linkedin'],
            'instagram'          => $igImageDrive['view_link'] ?? ($content['infografica_titolo'] . ' - ' . $content['infografica_sottotitolo']),
            'categoria'          => $content['categoria'],
            'img_evidenza'       => $fbImageDrive['view_link'] ?? '',
            'twitter_modificato' => $content['twitter_modificato'],
            'link'               => $sourceUrl !== '' ? $sourceUrl : 'https://www.formulapaddock.it',
        ], $config);
    } catch (Throwable $e) {
        $sheetError = $e->getMessage();
    }

    // STEP 7: Pubblicazione Buffer (Facebook & Twitter)
    $bufferResults = [];
    $bufferErrors = [];

    if (!empty($config['buffer_access_token'])) {
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

} catch (Throwable $e) {
    renderError($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contenuti generati — FormulaPaddock</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #0a0a0f 0%, #2b0a0f 100%);
            color: #fff;
            margin: 0;
            padding: 30px 20px;
        }
        .container { max-width: 1050px; margin: 0 auto; }
        h1 { font-size: 26px; }
        .accent { color: #ffd100; }

        .cloud-banner {
            background: linear-gradient(90deg, #e10600 0%, #b30000 100%);
            border: 2px solid #ffd100;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(225,6,0,0.4);
            text-align: center;
        }
        .cloud-banner h2 { margin: 0 0 10px 0; font-size: 22px; color: #fff; }
        .cloud-banner p { margin: 0 0 16px 0; font-size: 15px; color: #ffeb3b; }
        
        .btn-launch {
            display: inline-block;
            padding: 14px 28px;
            background: #ffd100;
            color: #111;
            font-weight: 900;
            font-size: 16px;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(255,209,0,0.4);
            transition: transform 0.2s;
        }
        .btn-launch:hover { transform: scale(1.05); }

        .section {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .section h2 { margin-top: 0; font-size: 16px; color: #ffd100; }
        .section pre {
            white-space: pre-wrap;
            font-family: inherit;
            background: rgba(0,0,0,0.3);
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }
        img { max-width: 100%; border-radius: 8px; margin-top: 10px; }
        a.btn {
            display: inline-block;
            margin-top: 10px;
            margin-right: 8px;
            padding: 8px 14px;
            background: #e10600;
            color: #ffffff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        .warn { background: rgba(180,40,40,0.2); border: 1px solid #b33; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .ok { background: rgba(40,160,80,0.15); border: 1px solid #2a8; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .back { color: #ffd100; text-decoration: none; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .reel-iframe-box {
            width: 100%;
            height: 720px;
            border: 2px solid rgba(225, 6, 0, 0.6);
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            margin-top: 12px;
        }
        .reel-iframe-box iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <h1>✅ Contenuti generati per: <span class="accent"><?= htmlspecialchars($title) ?></span></h1>

    <!-- BANNER CON PULSANTE PER APRIRE IL REEL ENGINE CLOUD CON URL PRE-CARICATO -->
    <div class="cloud-banner">
        <h2>🎬 REEL ENGINE CLOUD PRONTO PER QUESTA NOTIZIA</h2>
        <p>I post ed infografiche sono salvati! Clicca qui sotto per aprire il Reel Engine con l'articolo pre-caricato:</p>
        <a class="btn-launch" href="<?= htmlspecialchars($reelTargetUrl) ?>" target="_blank">🚀 APRI REEL ENGINE IN SCHEDA INTERA &rarr;</a>
    </div>

    <!-- REEL ENGINE INTEGRATO NELLA PAGINA VIA IFRAME -->
    <div class="section">
        <h2>🎬 Reel Engine 9:16 FormulaPaddock (Integrato)</h2>
        <p style="font-size:13px; color:#ccc;">
            Il Reel Engine Cloud e' attivo qui sotto con l'articolo gia' estratto! Clicca su <strong>REC REEL MP4</strong> per registrare.
        </p>

        <div class="reel-iframe-box">
            <iframe src="<?= htmlspecialchars($reelTargetUrl) ?>" title="Reel Engine 9:16 FormulaPaddock" allow="autoplay; microphone; camera; display-capture"></iframe>
        </div>
    </div>

    <?php if ($sheetError): ?>
        <div class="warn">⚠️ Riga NON scritta su Google Sheet: <?= htmlspecialchars($sheetError) ?></div>
    <?php else: ?>
        <div class="ok">✅ Riga e post pubblicati / scritti su Google Sheet.</div>
    <?php endif; ?>

    <?php foreach ($bufferResults as $br): ?>
        <div class="ok">🚀 <?= htmlspecialchars($br) ?></div>
    <?php endforeach; ?>

    <?php foreach ($bufferErrors as $be): ?>
        <div class="warn">⚠️ <?= htmlspecialchars($be) ?></div>
    <?php endforeach; ?>

    <?php foreach ($driveErrors as $de): ?>
        <div class="warn">⚠️ <?= htmlspecialchars($de) ?></div>
    <?php endforeach; ?>

    <div class="section">
        <h2>📘 Testo post Facebook</h2>
        <pre><?= htmlspecialchars($content['facebook']) ?></pre>
    </div>

    <div class="section">
        <h2>🐦 Testo Twitter / X</h2>
        <pre><?= htmlspecialchars($content['twitter']) ?></pre>
    </div>

    <div class="section">
        <h2>💼 Testo LinkedIn</h2>
        <pre><?= htmlspecialchars($content['linkedin']) ?></pre>
    </div>

    <div class="section grid">
        <div>
            <h2>🖼️ Infografica Facebook (1200x630)</h2>
            <img src="output/images/<?= htmlspecialchars(basename($images['fb_image'])) ?>" alt="Infografica Facebook">
            <?php if ($fbImageDrive): ?>
                <br><a class="btn" href="<?= htmlspecialchars($fbImageDrive['view_link']) ?>" target="_blank">Apri su Drive</a>
            <?php endif; ?>
        </div>
        <div>
            <h2>📸 Infografica Instagram (1080x1080)</h2>
            <img src="output/images/<?= htmlspecialchars(basename($images['ig_image'])) ?>" alt="Infografica Instagram">
            <?php if ($igImageDrive): ?>
                <br><a class="btn" href="<?= htmlspecialchars($igImageDrive['view_link']) ?>" target="_blank">Apri su Drive</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($sourceUrl): ?>
    <div class="section">
        <h2>🔗 Articolo di origine</h2>
        <pre><?= htmlspecialchars($sourceUrl) ?></pre>
    </div>
    <?php endif; ?>

    <a class="back" href="index.php">&larr; Genera un altro contenuto</a>
</div>
</body>
</html>
