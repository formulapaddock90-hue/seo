<?php
/**
 * process.php
 *
 * Flusso:
 * 1. Legge l'input (testo libero oppure URL)
 * 2. Se e' un URL, estrae il testo dell'articolo
 * 3. Genera i testi (Facebook, Twitter, Twitter modificato, LinkedIn, categoria, copy infografica, script reel) via Claude
 * 4. Genera le infografiche JPG (Facebook 1200x630, Instagram 1080x1080)
 * 5. Genera il video Reel (mp4 verticale) a partire dall'infografica Instagram
 * 6. Carica le immagini e il reel su Google Drive
 * 7. Scrive una nuova riga su Google Sheets con tutti i dati e i link
 * 8. Mostra un riepilogo all'utente
 */

ini_set('max_execution_time', 300); // generazione reel puo' richiedere tempo
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/url_extractor.php';
require_once __DIR__ . '/includes/ai_generator.php';
require_once __DIR__ . '/includes/image_generator.php';
require_once __DIR__ . '/includes/video_generator.php';
require_once __DIR__ . '/includes/google_service.php';

$config = require __DIR__ . '/config.php';

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
    // Leggi input da GET (parametro 'url') o POST (form 'input_text')
    $input = trim($_GET['url'] ?? trim($_POST['input_text'] ?? ''));

    if ($input === '') {
        // Se nessun input, redirige al form se non è POST/GET con dati
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET['url'])) {
            header('Location: index.php');
            exit;
        }
        throw new Exception('Nessun testo o URL fornito.');
    }

    // ---------------------------------------------------------------
    // STEP 1-2: ottieni il testo sorgente (da URL o testo diretto)
    // ---------------------------------------------------------------
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
        // Usa la prima frase come titolo provvisorio
        $firstSentence = preg_split('/(?<=[.!?])\s+/u', $input)[0] ?? $input;
        $title = mb_substr($firstSentence, 0, 80);
    }

    // ---------------------------------------------------------------
    // STEP 3: generazione testi via Claude
    // ---------------------------------------------------------------
    $content = generateSocialContent($sourceText, $title, $config);

    // ---------------------------------------------------------------
    // STEP 4: generazione infografiche
    // ---------------------------------------------------------------
    $slug = 'post_' . date('Ymd_His') . '_' . substr(md5($title . microtime()), 0, 6);

    $images = generateAllInfographics($content, $slug, $config);

    // ---------------------------------------------------------------
    // STEP 5: generazione reel (video verticale) - opzionale, se ffmpeg disponibile
    // ---------------------------------------------------------------
    // Nome fisso: ogni generazione sovrascrive il reel precedente (anche su Drive)
    $reelPath = $config['output_reels_dir'] . "/reel.mp4";
    $reelError = null;
    try {
        generateReelVideo($images['ig_image'], $reelPath, $content['reel_script'] ?? '', $config, 8);
    } catch (Throwable $e) {
        $reelError = $e->getMessage();
        $reelPath = null;
    }

    // ---------------------------------------------------------------
    // STEP 6: upload su Google Drive
    // ---------------------------------------------------------------
    $driveErrors = [];
    $fbImageDrive = null;
    $igImageDrive = null;
    $reelDrive = null;

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

    if ($reelPath !== null) {
        try {
            $reelDrive = uploadFileToDrive($reelPath, 'video/mp4', $config);
        } catch (Throwable $e) {
            $driveErrors[] = 'Upload reel: ' . $e->getMessage();
        }
    }

    // ---------------------------------------------------------------
    // STEP 7: scrittura su Google Sheets
    // ---------------------------------------------------------------
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
            'link'               => $sourceUrl !== '' ? $sourceUrl : ($reelDrive['view_link'] ?? ''),
        ], $config);
    } catch (Throwable $e) {
        $sheetError = $e->getMessage();
    }

    // ---------------------------------------------------------------
    // STEP 7.5: pubblicazione/programmazione su Buffer (Facebook & Twitter)
    // ---------------------------------------------------------------
    $bufferResults = [];
    $bufferErrors = [];

    if (!empty($config['buffer_access_token'])) {
        require_once __DIR__ . '/includes/buffer_service.php';

        $linkToPublish = $sourceUrl !== '' ? $sourceUrl : null;

        if (!empty($content['facebook'])) {
            try {
                $resFb = publishToBuffer($content['facebook'], 'facebook', $linkToPublish, $config);
                $modeLabel = ($config['buffer_share_mode'] ?? 'shareNow') === 'shareNow' ? 'Pubblicato ORA' : 'Inviato in coda';
                $bufferResults[] = "Facebook (Testo + Link): {$modeLabel} tramite Buffer (" . ($resFb['channel'] ?? 'Canale FB') . ")";
            } catch (Throwable $e) {
                $bufferErrors[] = "Buffer Facebook: " . $e->getMessage();
            }
        }

        if (!empty($content['twitter'])) {
            try {
                $resTw = publishToBuffer($content['twitter'], 'twitter', $linkToPublish, $config);
                $modeLabel = ($config['buffer_share_mode'] ?? 'shareNow') === 'shareNow' ? 'Pubblicato ORA' : 'Inviato in coda';
                $bufferResults[] = "Twitter/X (Testo + Link): {$modeLabel} tramite Buffer (" . ($resTw['channel'] ?? 'Canale Twitter') . ")";
            } catch (Throwable $e) {
                $bufferErrors[] = "Buffer Twitter: " . $e->getMessage();
            }
        }
    }

    // ---------------------------------------------------------------
    // STEP 7.6: pubblicazione nativa (LinkedIn & Threads)
    // ---------------------------------------------------------------
    $nativeResults = [];
    $nativeErrors = [];

    if (file_exists($config['linkedin_oauth_token_json'])) {
        require_once __DIR__ . '/includes/linkedin_service.php';
        if (!empty($content['linkedin'])) {
            try {
                $resLi = publishToLinkedIn($content['linkedin'], $linkToPublish, $config);
                $nativeResults[] = "LinkedIn Nativo (Testo + Link): Pubblicato con successo";
            } catch (Throwable $e) {
                $nativeErrors[] = "LinkedIn Nativo: " . $e->getMessage();
            }
        }
    }

    if (file_exists($config['threads_oauth_token_json'])) {
        require_once __DIR__ . '/includes/threads_service.php';
        // Utilizziamo il testo Facebook per Threads (supporta fino a 500 caratteri, ottimale per post ricchi)
        $threadsText = $content['facebook'] ?? $content['twitter'] ?? '';
        if (!empty($threadsText)) {
            try {
                $resTh = publishToThreads($threadsText, $linkToPublish, $config);
                $threadsUserLabel = !empty($resTh['username']) ? " (@{$resTh['username']})" : "";
                $nativeResults[] = "Threads Nativo (Testo + Link): Pubblicato con successo{$threadsUserLabel}";
            } catch (Throwable $e) {
                $nativeErrors[] = "Threads Nativo: " . $e->getMessage();
            }
        }
    }

    // ---------------------------------------------------------------
    // STEP 8: pulizia file temporanei locali (gia' caricati su Drive)
    // ---------------------------------------------------------------
    // Commentato di default: decommenta se vuoi rimuovere i file locali dopo l'upload
    // if ($fbImageDrive) @unlink($images['fb_image']);
    // if ($igImageDrive) @unlink($images['ig_image']);
    // if ($reelDrive && $reelPath) @unlink($reelPath);

} catch (Throwable $e) {
    renderError($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contenuti generati</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #0a0a0f 0%, #2b0a0f 100%);
            color: #fff;
            margin: 0;
            padding: 30px 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 26px; }
        .accent { color: #ffd100; }
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
            padding: 8px 14px;
            background: #ffd100;
            color: #1a1a1a;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        .warn {
            background: rgba(180,40,40,0.2);
            border: 1px solid #b33;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .ok {
            background: rgba(40,160,80,0.15);
            border: 1px solid #2a8;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .back { color: #ffd100; text-decoration: none; }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <h1>✅ Contenuti generati per: <span class="accent"><?= htmlspecialchars($title) ?></span></h1>

    <?php if ($sheetError): ?>
        <div class="warn">⚠️ Riga NON scritta su Google Sheet: <?= htmlspecialchars($sheetError) ?></div>
    <?php else: ?>
        <div class="ok">✅ Riga scritta correttamente su Google Sheet.</div>
    <?php endif; ?>

    <?php foreach ($bufferResults as $br): ?>
        <div class="ok">🚀 <?= htmlspecialchars($br) ?></div>
    <?php endforeach; ?>

    <?php foreach ($nativeResults as $nr): ?>
        <div class="ok">🚀 <?= htmlspecialchars($nr) ?></div>
    <?php endforeach; ?>

    <?php foreach ($bufferErrors as $be): ?>
        <div class="warn">⚠️ <?= htmlspecialchars($be) ?></div>
    <?php endforeach; ?>

    <?php foreach ($nativeErrors as $ne): ?>
        <div class="warn">⚠️ <?= htmlspecialchars($ne) ?></div>
    <?php endforeach; ?>

    <?php foreach ($driveErrors as $de): ?>
        <div class="warn">⚠️ <?= htmlspecialchars($de) ?></div>
    <?php endforeach; ?>

    <?php if ($reelError): ?>
        <div class="warn">⚠️ Reel non generato: <?= htmlspecialchars($reelError) ?></div>
    <?php endif; ?>

    <div class="section">
        <h2>📘 Testo post Facebook</h2>
        <pre><?= htmlspecialchars($content['facebook']) ?></pre>
    </div>

    <div class="section">
        <h2>🐦 Testo Twitter / X</h2>
        <pre><?= htmlspecialchars($content['twitter']) ?></pre>
    </div>

    <div class="section">
        <h2>🐦 Twitter modificato (variante)</h2>
        <pre><?= htmlspecialchars($content['twitter_modificato']) ?></pre>
    </div>

    <div class="section">
        <h2>💼 Testo LinkedIn</h2>
        <pre><?= htmlspecialchars($content['linkedin']) ?></pre>
    </div>

    <div class="section">
        <h2>🏷️ Categoria</h2>
        <pre><?= htmlspecialchars($content['categoria']) ?></pre>
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

    <div class="section">
        <h2>🎬 Reel verticale (mp4)</h2>
        <?php if ($reelDrive): ?>
            <video controls style="max-width:300px; border-radius:8px;">
                <source src="output/reels/<?= htmlspecialchars(basename($reelPath)) ?>" type="video/mp4">
            </video>
            <br><a class="btn" href="<?= htmlspecialchars($reelDrive['view_link']) ?>" target="_blank">Apri su Drive</a>
        <?php elseif ($reelPath): ?>
            <video controls style="max-width:300px; border-radius:8px;">
                <source src="output/reels/<?= htmlspecialchars(basename($reelPath)) ?>" type="video/mp4">
            </video>
            <p>Reel generato localmente ma non caricato su Drive (vedi avviso sopra).</p>
        <?php else: ?>
            <p>Reel non disponibile.</p>
        <?php endif; ?>
        <p style="font-size:13px;color:#ccc;">Script reel: <em><?= htmlspecialchars($content['reel_script']) ?></em></p>
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

