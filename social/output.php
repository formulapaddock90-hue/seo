<?php
/**
 * output.php — Formula Paddock Social Visual Studio HD & Post Control Center
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/config.php';
$cloudUrl = $config['reel_cloud_url'] ?? 'https://reel-engine-dcnr.onrender.com';

// Se invocato direttamente senza passare da process.php, prova a caricare i dati dall'ultima sessione o file
$content = $content ?? ($_SESSION['last_social_content'] ?? null);
$images = $images ?? ($_SESSION['last_social_images'] ?? null);
$title = $title ?? ($_SESSION['last_social_title'] ?? 'Formula 1 News');
$sourceUrl = $sourceUrl ?? ($_SESSION['last_social_source_url'] ?? '');
$sheetError = $sheetError ?? ($_SESSION['last_social_sheet_error'] ?? null);
$bufferResults = $bufferResults ?? ($_SESSION['last_social_buffer_results'] ?? []);
$bufferErrors = $bufferErrors ?? ($_SESSION['last_social_buffer_errors'] ?? []);
$driveErrors = $driveErrors ?? ($_SESSION['last_social_drive_errors'] ?? []);
$hdImageDrive = $hdImageDrive ?? ($_SESSION['last_social_hd_drive'] ?? null);
$fbImageDrive = $fbImageDrive ?? ($_SESSION['last_social_fb_drive'] ?? null);
$igImageDrive = $igImageDrive ?? ($_SESSION['last_social_ig_drive'] ?? null);

$hdDriveLink = $hdImageDrive['view_link'] ?? ($fbImageDrive['view_link'] ?? ($igImageDrive['view_link'] ?? null));

if (!$content) {
    $content = [
        'facebook' => '',
        'twitter' => '',
        'linkedin' => '',
        'categoria' => 'F1 NEWS',
        'infografica_titolo' => '',
        'infografica_sottotitolo' => ''
    ];
}

$hdImageFile = 'output/images/visual_studio_hd.jpg';
if (!empty($images['hd_image']) && file_exists($images['hd_image'])) {
    $hdImageFile = 'output/images/' . basename($images['hd_image']);
} elseif (file_exists(__DIR__ . '/output/images/facebook.jpg')) {
    $hdImageFile = 'output/images/facebook.jpg';
}

$reelTargetUrl = $cloudUrl . '/?url=' . urlencode($sourceUrl !== '' ? $sourceUrl : 'https://www.formulapaddock.it');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formula Paddock Visual Studio HD — Output & Social Hub</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
            background: linear-gradient(135deg, #09090e 0%, #1c080b 50%, #0d0d12 100%);
            color: #fff;
            margin: 0;
            padding: 30px 20px;
            min-height: 100vh;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        
        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        h1 { font-size: 24px; margin: 0; }
        .accent { color: #ffd100; }
        
        .btn-top-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: #ffd100;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-top-back:hover {
            background: rgba(255,209,0,0.15);
            border-color: #ffd100;
        }

        .cloud-banner {
            background: linear-gradient(90deg, #e10600 0%, #8f0000 100%);
            border: 2px solid #ffd100;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(225,6,0,0.35);
            text-align: center;
        }
        .cloud-banner h2 { margin: 0 0 8px 0; font-size: 20px; color: #fff; }
        .cloud-banner p { margin: 0 0 16px 0; font-size: 14px; color: #ffeb3b; }
        
        .btn-launch {
            display: inline-block;
            padding: 12px 26px;
            background: #ffd100;
            color: #111;
            font-weight: 900;
            font-size: 15px;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(255,209,0,0.4);
            transition: transform 0.2s, background 0.2s;
        }
        .btn-launch:hover { transform: scale(1.04); background: #fff; }

        .section {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 22px;
            backdrop-filter: blur(10px);
        }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .section h2 { margin: 0; font-size: 17px; color: #ffd100; }
        
        .status-box {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ok { background: rgba(40,160,80,0.18); border: 1px solid #2a8; color: #4ade80; }
        .warn { background: rgba(180,40,40,0.2); border: 1px solid #b33; color: #f87171; }

        .post-box {
            position: relative;
            margin-top: 10px;
        }
        .post-textarea {
            width: 100%;
            min-height: 110px;
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            color: #fff;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.5;
            font-family: inherit;
            resize: vertical;
        }
        .post-textarea:focus {
            outline: 2px solid #ffd100;
        }

        .action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-copy {
            background: #2a2a38;
            color: #ddd;
            border: 1px solid #444;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-copy:hover { background: #3a3a4c; color: #fff; }

        .btn-pub {
            padding: 9px 18px;
            border-radius: 6px;
            border: none;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .btn-pub:hover { transform: translateY(-2px); filter: brightness(1.15); }
        .btn-pub:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .btn-facebook { background: linear-gradient(135deg, #1877f2, #0d5cb6); }
        .btn-twitter  { background: linear-gradient(135deg, #000000, #222222); border: 1px solid #444; }
        .btn-threads  { background: linear-gradient(135deg, #242526, #000000); border: 1px solid #666; }
        .btn-linkedin { background: linear-gradient(135deg, #0a66c2, #004182); }
        .btn-tiktok   { background: linear-gradient(135deg, #fe2c55, #25f4ee); color: #000; }

        .publish-status-msg {
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .hd-preview-container {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 850px) {
            .hd-preview-container { grid-template-columns: 1fr; }
        }

        .hd-img-wrapper {
            background: #000;
            border: 2px solid #ffd100;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            text-align: center;
        }
        .hd-img-wrapper img {
            width: 100%;
            height: auto;
            aspect-ratio: 1/1;
            object-fit: cover;
            display: block;
        }
        
        .hd-info-panel {
            padding: 10px 0;
        }
        .badge-hd {
            display: inline-block;
            background: #ffd100;
            color: #111;
            font-weight: 800;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }

        .reel-iframe-box {
            width: 100%;
            height: 680px;
            border: 2px solid rgba(225, 6, 0, 0.6);
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            margin-top: 12px;
        }
        .reel-iframe-box iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>
<div class="container">

    <div class="header-bar">
        <h1>🏎️ <span class="accent">Formula Paddock</span> Visual Studio HD</h1>
        <a class="btn-top-back" href="index.php">&larr; Inserisci nuova notizia</a>
    </div>

    <!-- NOTIFICHE DI ELABORAZIONE AUTOMATICA -->
    <?php if ($sheetError): ?>
        <div class="status-box warn">⚠️ Google Sheet: <?= htmlspecialchars($sheetError) ?></div>
    <?php else: ?>
        <div class="status-box ok">✅ Riga e dati sincronizzati su Google Sheet.</div>
    <?php endif; ?>

    <?php foreach ($bufferResults as $br): ?>
        <div class="status-box ok">🚀 Auto-Buffer: <?= htmlspecialchars($br) ?></div>
    <?php endforeach; ?>

    <?php foreach ($bufferErrors as $be): ?>
        <div class="status-box warn">⚠️ Auto-Buffer: <?= htmlspecialchars($be) ?></div>
    <?php endforeach; ?>

    <?php foreach ($driveErrors as $de): ?>
        <div class="status-box warn">⚠️ Google Drive: <?= htmlspecialchars($de) ?></div>
    <?php endforeach; ?>

    <!-- SEZIONE 1: FORMULA PADDOCK VISUAL STUDIO HD (1080x1080) -->
    <div class="section">
        <div class="section-header">
            <h2>🎨 2. Formula Paddock Visual Studio HD (1080x1080)</h2>
            <div>
                <span class="badge-hd">QUADRATO 1:1 HD — FACEBOOK & INSTAGRAM</span>
            </div>
        </div>

        <div class="hd-preview-container">
            <div class="hd-img-wrapper">
                <img src="<?= htmlspecialchars($hdImageFile) ?>" alt="Formula Paddock Visual Studio HD">
            </div>
            <div class="hd-info-panel">
                <h3 style="margin-top:0; color:#fff; font-size:18px;"><?= htmlspecialchars($title) ?></h3>
                <p style="color:#aaa; font-size:13px; line-height:1.6;">
                    Formato grafico unificato ad alta risoluzione <strong>1080x1080 pixel</strong>.
                    Ottimizzato per feed Facebook, feed Instagram, Threads e anteprime social.
                </p>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:16px;">
                    <a class="btn-top-back" href="<?= htmlspecialchars($hdImageFile) ?>" download style="color:#fff;">
                        ⬇️ Scarica HD (1080x1080)
                    </a>
                    <?php if ($hdDriveLink): ?>
                        <a class="btn-top-back" href="<?= htmlspecialchars($hdDriveLink) ?>" target="_blank" style="color:#ffd100; border-color:#ffd100;">
                            ☁️ Apri su Google Drive
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SEZIONE 2: PULSANTI E TESTI PER SINGOLO SOCIAL -->
    
    <!-- FACEBOOK -->
    <div class="section">
        <div class="section-header">
            <h2>📘 Formato Facebook</h2>
            <div class="publish-status-msg" id="status-facebook"></div>
        </div>
        <div class="post-box">
            <textarea id="text-facebook" class="post-textarea"><?= htmlspecialchars($content['facebook'] ?? '') ?></textarea>
            <div class="action-bar">
                <button type="button" class="btn-copy" onclick="copyToClipboard('text-facebook', this)">📋 Copia testo</button>
                <div class="action-buttons">
                    <button type="button" class="btn-pub btn-facebook" onclick="publishSocial('facebook', 'text-facebook')">
                        🚀 Pubblica su Facebook + immagine (Meta API)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TWITTER / X -->
    <div class="section">
        <div class="section-header">
            <h2>🐦 Formato Twitter / X</h2>
            <div class="publish-status-msg" id="status-twitter"></div>
        </div>
        <div class="post-box">
            <textarea id="text-twitter" class="post-textarea"><?= htmlspecialchars($content['twitter'] ?? '') ?></textarea>
            <div class="action-bar">
                <button type="button" class="btn-copy" onclick="copyToClipboard('text-twitter', this)">📋 Copia testo</button>
                <div class="action-buttons">
                    <button type="button" class="btn-pub btn-twitter" onclick="publishSocial('twitter', 'text-twitter')">
                        🚀 Pubblica su Twitter / X (Buffer)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- THREADS -->
    <div class="section">
        <div class="section-header">
            <h2>💬 Formato Threads</h2>
            <div class="publish-status-msg" id="status-threads"></div>
        </div>
        <div class="post-box">
            <textarea id="text-threads" class="post-textarea"><?= htmlspecialchars($content['twitter_modificato'] ?? ($content['twitter'] ?? '')) ?></textarea>
            <div class="action-bar">
                <button type="button" class="btn-copy" onclick="copyToClipboard('text-threads', this)">📋 Copia testo</button>
                <div class="action-buttons">
                    <button type="button" class="btn-pub btn-threads" onclick="publishSocial('threads', 'text-threads')">
                        🚀 Pubblica su Threads
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- LINKEDIN -->
    <div class="section">
        <div class="section-header">
            <h2>💼 Formato LinkedIn</h2>
            <div class="publish-status-msg" id="status-linkedin"></div>
        </div>
        <div class="post-box">
            <textarea id="text-linkedin" class="post-textarea"><?= htmlspecialchars($content['linkedin'] ?? '') ?></textarea>
            <div class="action-bar">
                <button type="button" class="btn-copy" onclick="copyToClipboard('text-linkedin', this)">📋 Copia testo</button>
                <div class="action-buttons">
                    <button type="button" class="btn-pub btn-linkedin" onclick="publishSocial('linkedin', 'text-linkedin')">
                        🚀 Pubblica su LinkedIn
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TIKTOK / REEL -->
    <div class="section">
        <div class="section-header">
            <h2>🎵 Formato TikTok & Reels (9:16)</h2>
            <div class="publish-status-msg" id="status-tiktok"></div>
        </div>
        <div class="post-box">
            <textarea id="text-tiktok" class="post-textarea"><?= htmlspecialchars(($content['infografica_titolo'] ?? $title) . " #f1 #formula1 #formulapaddock") ?></textarea>
            <div class="action-bar">
                <button type="button" class="btn-copy" onclick="copyToClipboard('text-tiktok', this)">📋 Copia didascalia</button>
                <div class="action-buttons">
                    <button type="button" class="btn-pub btn-tiktok" onclick="publishSocial('tiktok', 'text-tiktok')">
                        🚀 Invia Reel a TikTok
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SEZIONE 3: REEL ENGINE CLOUD & IFRAME -->
    <div class="cloud-banner">
        <h2>🎬 REEL ENGINE CLOUD PRONTO</h2>
        <p>Registra il video Reel 9:16 con l'articolo pre-caricato:</p>
        <a class="btn-launch" href="<?= htmlspecialchars($reelTargetUrl) ?>" target="_blank">🚀 APRI REEL ENGINE A SCHERMO INTERO &rarr;</a>
    </div>

    <div class="section">
        <h2>🎬 Reel Engine 9:16 FormulaPaddock (Integrato)</h2>
        <p style="font-size:13px; color:#ccc;">
            Il Reel Engine Cloud e' attivo qui sotto con l'articolo gia' estratto. Clicca su <strong>REC REEL MP4</strong> per registrare.
        </p>
        <div class="reel-iframe-box">
            <iframe src="<?= htmlspecialchars($reelTargetUrl) ?>" title="Reel Engine 9:16 FormulaPaddock" allow="autoplay; microphone; camera; display-capture"></iframe>
        </div>
    </div>

    <?php if ($sourceUrl): ?>
    <div class="section">
        <h2>🔗 Articolo di origine</h2>
        <pre style="white-space:pre-wrap; background:rgba(0,0,0,0.3); padding:10px; border-radius:6px; font-size:13px;"><?= htmlspecialchars($sourceUrl) ?></pre>
    </div>
    <?php endif; ?>

    <p style="text-align:center; margin-top:30px;">
        <a class="btn-top-back" href="index.php">&larr; Torna alla schermata di inserimento</a>
    </p>

</div>

<script>
const articleSourceUrl = <?= json_encode($sourceUrl) ?>;

function copyToClipboard(elementId, btnElement) {
    const textarea = document.getElementById(elementId);
    if (!textarea) return;
    textarea.select();
    navigator.clipboard.writeText(textarea.value).then(() => {
        const origText = btnElement.innerText;
        btnElement.innerText = '✅ Copiato!';
        btnElement.style.color = '#4ade80';
        setTimeout(() => {
            btnElement.innerText = origText;
            btnElement.style.color = '';
        }, 2000);
    });
}

function publishSocial(channel, textElementId) {
    const textarea = document.getElementById(textElementId);
    const statusEl = document.getElementById('status-' + channel);
    const textVal = textarea ? textarea.value.trim() : '';

    if (!textVal && channel !== 'tiktok') {
        alert('Il testo per ' + channel + ' non puo essere vuoto!');
        return;
    }

    if (statusEl) {
        statusEl.innerHTML = '<span style="color:#ffd100;">⏳ Pubblicazione in corso...</span>';
    }

    const formData = new FormData();
    formData.append('channel', channel);
    formData.append('text', textVal);
    formData.append('link', articleSourceUrl || '');

    fetch('publish_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Errore durante la pubblicazione (' + response.status + ')');
        }
        return data;
    })
    .then((data) => {
        if (statusEl) {
            statusEl.innerHTML = '<span style="color:#4ade80;">✅ ' + (data.message || 'Pubblicato con successo!') + '</span>';
        }
    })
    .catch((err) => {
        if (statusEl) {
            statusEl.innerHTML = '<span style="color:#f87171;">⚠️ ' + err.message + '</span>';
        }
    });
}
</script>
</body>
</html>
