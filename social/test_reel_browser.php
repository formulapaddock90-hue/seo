<?php
/**
 * Test rendering Reel direttamente nel browser, senza FFmpeg server e senza OnRender.
 * Genera un MP4/H.264 da canvas con MediaRecorder, quando supportato dal browser.
 */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Test Reel Browser — FormulaPaddock</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px 18px 50px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #09090d;
            color: #f5f5f5;
        }
        .wrap { max-width: 1050px; margin: 0 auto; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        h2 { margin-top: 0; }
        .sub { color: #aaa; margin: 0 0 22px; line-height: 1.5; }
        .card {
            border: 1px solid #2b2b34;
            border-radius: 14px;
            background: #131319;
            padding: 20px;
            margin-bottom: 16px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
        }
        .item {
            background: #0d0d12;
            border: 1px solid #24242d;
            border-radius: 10px;
            padding: 14px;
        }
        .label { color: #8f8f9c; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
        .value { margin-top: 6px; font-size: 14px; overflow-wrap: anywhere; }
        .ok { color: #57df96; }
        .bad { color: #ff7777; }
        .warn { color: #ffd166; }
        .pill {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .pill.ok { background: rgba(31,157,98,.16); color: #57df96; }
        .pill.bad { background: rgba(201,69,69,.16); color: #ff7777; }
        button, .btn {
            appearance: none;
            border: 0;
            border-radius: 9px;
            background: #ffd100;
            color: #111;
            font-weight: 900;
            padding: 12px 18px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .secondary { background: #292934; color: #fff; border: 1px solid #444; }
        .stage-grid {
            display: grid;
            grid-template-columns: minmax(220px, 330px) 1fr;
            gap: 22px;
            align-items: start;
        }
        @media (max-width: 760px) { .stage-grid { grid-template-columns: 1fr; } }
        canvas, video {
            width: 100%;
            aspect-ratio: 9 / 16;
            border-radius: 12px;
            background: #000;
            border: 1px solid #333;
            display: block;
        }
        .status {
            padding: 12px 14px;
            border-radius: 9px;
            background: #0d0d12;
            border: 1px solid #2b2b34;
            margin: 12px 0;
            min-height: 46px;
            line-height: 1.5;
        }
        code { color: #ffd166; word-break: break-word; }
        a { color: #ffd100; }
        .small { color: #aaa; font-size: 13px; line-height: 1.55; }
        ul { line-height: 1.65; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🎬 Test Reel Browser FormulaPaddock</h1>
    <p class="sub">Generazione locale sul tuo PC: 1080×1920, 30 fps, MP4/H.264 quando il browser lo supporta. Nessun OnRender e nessun FFmpeg sul server.</p>

    <section class="card">
        <h2>Compatibilità browser</h2>
        <div class="grid">
            <div class="item">
                <div class="label">Browser</div>
                <div class="value" id="browserInfo">—</div>
            </div>
            <div class="item">
                <div class="label">Canvas captureStream</div>
                <div class="value" id="captureSupport">—</div>
            </div>
            <div class="item">
                <div class="label">MediaRecorder</div>
                <div class="value" id="recorderSupport">—</div>
            </div>
            <div class="item">
                <div class="label">MP4 / H.264</div>
                <div class="value" id="mp4Support">—</div>
            </div>
        </div>
        <div class="status" id="compatStatus">Controllo in corso…</div>
    </section>

    <section class="card">
        <h2>Genera un Reel di prova</h2>
        <div class="stage-grid">
            <div>
                <canvas id="reelCanvas" width="1080" height="1920"></canvas>
            </div>
            <div>
                <p>Il test crea un video di <strong>5 secondi</strong> direttamente nel browser a <strong>1080×1920 / 30 fps</strong>, con bitrate richiesto di <strong>10 Mbps</strong>.</p>
                <button id="generateBtn" type="button" disabled>▶ Genera test MP4</button>
                <div class="status" id="renderStatus">In attesa.</div>
                <div id="resultBox" hidden>
                    <p><strong>Risultato</strong></p>
                    <video id="previewVideo" controls playsinline></video>
                    <p id="resultInfo" class="small"></p>
                    <a id="downloadBtn" class="btn" download="formulapaddock_test_reel.mp4">⬇ Scarica MP4 di prova</a>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Cosa significa il risultato</h2>
        <ul>
            <li><span class="ok"><strong>MP4/H.264 supportato</strong></span>: possiamo costruire il Reel direttamente nel Social Hub, senza servizi esterni.</li>
            <li><span class="bad"><strong>MP4/H.264 non supportato</strong></span>: valuteremo ffmpeg.wasm nel browser come fallback.</li>
        </ul>
        <p class="small">Questo test non carica il video su internet: il file viene creato nella memoria del browser e resta sul tuo PC finché non lo scarichi.</p>
        <p><a href="index.php">← Torna al Social Hub</a> &nbsp;·&nbsp; <a href="test_ffmpeg.php">Test FFmpeg server</a></p>
    </section>
</div>

<script>
(() => {
    const canvas = document.getElementById('reelCanvas');
    const ctx = canvas.getContext('2d');
    const generateBtn = document.getElementById('generateBtn');
    const renderStatus = document.getElementById('renderStatus');
    const compatStatus = document.getElementById('compatStatus');
    const resultBox = document.getElementById('resultBox');
    const previewVideo = document.getElementById('previewVideo');
    const resultInfo = document.getElementById('resultInfo');
    const downloadBtn = document.getElementById('downloadBtn');

    const mimeCandidates = [
        'video/mp4;codecs=avc1.42E01E',
        'video/mp4;codecs=avc1.42001f',
        'video/mp4;codecs=avc1.4D401F',
        'video/mp4'
    ];

    function pill(ok, yes = 'SÌ', no = 'NO') {
        return `<span class="pill ${ok ? 'ok' : 'bad'}">${ok ? yes : no}</span>`;
    }

    document.getElementById('browserInfo').textContent = navigator.userAgent;
    const captureOk = typeof canvas.captureStream === 'function';
    const recorderOk = typeof window.MediaRecorder !== 'undefined';
    document.getElementById('captureSupport').innerHTML = pill(captureOk);
    document.getElementById('recorderSupport').innerHTML = pill(recorderOk);

    let chosenMime = '';
    if (recorderOk && typeof MediaRecorder.isTypeSupported === 'function') {
        chosenMime = mimeCandidates.find(type => MediaRecorder.isTypeSupported(type)) || '';
    }
    document.getElementById('mp4Support').innerHTML = chosenMime
        ? `<span class="pill ok">SUPPORTATO</span><div class="small" style="margin-top:6px"><code>${chosenMime}</code></div>`
        : '<span class="pill bad">NON SUPPORTATO</span>';

    const compatible = captureOk && recorderOk && !!chosenMime;
    generateBtn.disabled = !compatible;
    compatStatus.innerHTML = compatible
        ? `<span class="ok"><strong>✅ Browser pronto.</strong></span> Il test userà <code>${chosenMime}</code>.`
        : '<span class="bad"><strong>❌ Questo browser non può creare direttamente il nostro MP4/H.264.</strong></span>';

    function roundedRect(x, y, w, h, r, fill, stroke = null, lineWidth = 1) {
        ctx.beginPath();
        ctx.roundRect(x, y, w, h, r);
        ctx.fillStyle = fill;
        ctx.fill();
        if (stroke) {
            ctx.strokeStyle = stroke;
            ctx.lineWidth = lineWidth;
            ctx.stroke();
        }
    }

    function wrapText(text, maxWidth, font) {
        ctx.font = font;
        const words = text.split(/\s+/);
        const lines = [];
        let current = '';
        for (const word of words) {
            const test = current ? current + ' ' + word : word;
            if (ctx.measureText(test).width > maxWidth && current) {
                lines.push(current);
                current = word;
            } else {
                current = test;
            }
        }
        if (current) lines.push(current);
        return lines;
    }

    function drawFrame(progress) {
        const w = canvas.width;
        const h = canvas.height;
        const t = progress * Math.PI * 2;

        const grad = ctx.createLinearGradient(0, 0, w, h);
        grad.addColorStop(0, '#09090f');
        grad.addColorStop(.55, '#22070b');
        grad.addColorStop(1, '#050507');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, w, h);

        // Fasce dinamiche stile velocità.
        ctx.save();
        ctx.globalAlpha = .22;
        ctx.translate(Math.sin(t) * 70, 0);
        ctx.rotate(-0.12);
        for (let i = -2; i < 8; i++) {
            ctx.fillStyle = i % 2 === 0 ? '#e10600' : '#ffffff';
            ctx.fillRect(i * 220, 260, 90, 1320);
        }
        ctx.restore();

        // Glow centrale.
        const glow = ctx.createRadialGradient(540, 820, 50, 540, 820, 700);
        glow.addColorStop(0, 'rgba(225,6,0,.28)');
        glow.addColorStop(1, 'rgba(225,6,0,0)');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, w, h);

        roundedRect(52, 58, 530, 88, 18, 'rgba(0,0,0,.78)', '#e10600', 4);
        ctx.fillStyle = '#fff';
        ctx.font = '900 38px Arial, sans-serif';
        ctx.fillText('FORMULAPADDOCK.IT', 82, 115);

        roundedRect(720, 58, 308, 154, 18, 'rgba(0,0,0,.82)', 'rgba(255,255,255,.24)', 3);
        ctx.fillStyle = '#ffeb3b';
        ctx.font = '900 52px Arial, sans-serif';
        ctx.fillText('334 KM/H', 755, 126);
        ctx.fillStyle = '#00e676';
        ctx.font = '800 24px Arial, sans-serif';
        ctx.fillText('DRS ATTIVO', 757, 174);

        // Simulazione pannello notizia con leggero zoom.
        const zoom = 1 + progress * .035;
        ctx.save();
        ctx.translate(w / 2, 820);
        ctx.scale(zoom, zoom);
        ctx.translate(-w / 2, -820);
        roundedRect(70, 400, 940, 810, 30, 'rgba(6,6,10,.86)', 'rgba(255,255,255,.12)', 3);
        ctx.fillStyle = '#e10600';
        ctx.fillRect(70, 400, 16, 810);
        ctx.fillStyle = '#ffd100';
        ctx.font = '900 30px Arial, sans-serif';
        ctx.fillText('FORMULA 1 • NEWS', 120, 480);

        ctx.fillStyle = '#fff';
        const titleFont = '900 72px Arial, sans-serif';
        const lines = wrapText('REEL FORMULAPADDOCK IN ALTA QUALITÀ', 800, titleFont);
        ctx.font = titleFont;
        let y = 610;
        for (const line of lines) {
            ctx.fillText(line, 120, y);
            y += 92;
        }

        ctx.fillStyle = '#cfcfd6';
        ctx.font = '500 38px Arial, sans-serif';
        const subLines = wrapText('Generato direttamente nel browser. Nessun OnRender, nessun FFmpeg sul server.', 800, ctx.font);
        y += 35;
        for (const line of subLines) {
            ctx.fillText(line, 120, y);
            y += 55;
        }
        ctx.restore();

        // Progress bar.
        roundedRect(70, 1320, 940, 14, 7, 'rgba(255,255,255,.12)');
        roundedRect(70, 1320, Math.max(18, 940 * progress), 14, 7, '#e10600');

        // CTA finale crescente.
        const ctaAlpha = Math.max(0, (progress - .62) / .18);
        ctx.save();
        ctx.globalAlpha = Math.min(1, ctaAlpha);
        roundedRect(105, 1450, 870, 260, 30, 'rgba(0,0,0,.88)', '#e10600', 5);
        ctx.fillStyle = '#fff';
        ctx.textAlign = 'center';
        ctx.font = '900 52px Arial, sans-serif';
        ctx.fillText('SEGUI FORMULAPADDOCK.IT', 540, 1555);
        ctx.fillStyle = '#ffd100';
        ctx.font = '800 34px Arial, sans-serif';
        ctx.fillText('Instagram • Facebook • TikTok', 540, 1625);
        ctx.restore();
        ctx.textAlign = 'left';

        ctx.fillStyle = 'rgba(255,255,255,.55)';
        ctx.font = '600 24px Arial, sans-serif';
        ctx.fillText('TEST BROWSER 1080×1920 • 30 FPS', 70, 1840);
    }

    drawFrame(0);

    generateBtn.addEventListener('click', async () => {
        generateBtn.disabled = true;
        resultBox.hidden = true;
        renderStatus.innerHTML = '<span class="warn"><strong>⏳ Generazione in corso…</strong></span> 5 secondi di Reel vengono creati sul tuo PC.';

        if (previewVideo.src) {
            URL.revokeObjectURL(previewVideo.src);
            previewVideo.removeAttribute('src');
        }

        try {
            const stream = canvas.captureStream(30);
            const recorder = new MediaRecorder(stream, {
                mimeType: chosenMime,
                videoBitsPerSecond: 10_000_000
            });
            const chunks = [];
            recorder.addEventListener('dataavailable', e => {
                if (e.data && e.data.size > 0) chunks.push(e.data);
            });

            const stopped = new Promise((resolve, reject) => {
                recorder.addEventListener('stop', resolve, { once: true });
                recorder.addEventListener('error', e => reject(e.error || new Error('Errore MediaRecorder')), { once: true });
            });

            const durationMs = 5000;
            const start = performance.now();
            recorder.start();

            await new Promise(resolve => {
                function animate(now) {
                    const elapsed = now - start;
                    const progress = Math.min(1, elapsed / durationMs);
                    drawFrame(progress);
                    renderStatus.innerHTML = `<span class="warn"><strong>⏳ Generazione ${Math.round(progress * 100)}%</strong></span>`;
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        resolve();
                    }
                }
                requestAnimationFrame(animate);
            });

            recorder.stop();
            await stopped;
            stream.getTracks().forEach(track => track.stop());

            if (!chunks.length) throw new Error('Il browser non ha prodotto dati video.');

            const blob = new Blob(chunks, { type: recorder.mimeType || chosenMime });
            const url = URL.createObjectURL(blob);
            previewVideo.src = url;
            downloadBtn.href = url;
            downloadBtn.download = 'formulapaddock_test_reel.mp4';

            const mb = (blob.size / 1024 / 1024).toFixed(2);
            resultInfo.innerHTML = `Formato effettivo: <code>${recorder.mimeType || blob.type || chosenMime}</code><br>Dimensione: <strong>${mb} MB</strong> per 5 secondi.<br>Bitrate richiesto: <strong>10 Mbps</strong>.`;
            resultBox.hidden = false;
            renderStatus.innerHTML = '<span class="ok"><strong>✅ Reel creato nel browser.</strong></span> Riproducilo qui sotto e controlla qualità e fluidità.';
        } catch (err) {
            console.error(err);
            renderStatus.innerHTML = `<span class="bad"><strong>❌ Errore:</strong> ${String(err && err.message ? err.message : err)}</span>`;
        } finally {
            generateBtn.disabled = !compatible;
        }
    });
})();
</script>
</body>
</html>
