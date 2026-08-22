<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generatore Contenuti Social F1 — FormulaPaddock</title>
<style>
*{box-sizing:border-box}body{font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:linear-gradient(135deg,#0a0a0f 0%,#2b0a0f 100%);color:#fff;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:40px;max-width:680px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4)}h1{margin-top:0;font-size:26px}.accent{color:#ffd100}p.desc{color:#ccc;font-size:14px;line-height:1.55}.reel-info{background:rgba(40,160,80,.12);border:1px solid #2a8;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px;color:#9df0b7}label{display:block;margin:20px 0 8px;font-weight:600;font-size:14px}textarea,input[type="text"]{width:100%;padding:12px 14px;border-radius:8px;border:1px solid rgba(255,255,255,.2);background:rgba(0,0,0,.3);color:#fff;font-size:14px;resize:vertical}textarea{min-height:140px}textarea:focus,input:focus{outline:2px solid #ffd100}button{margin-top:24px;width:100%;padding:14px;border:0;border-radius:8px;background:#ffd100;color:#1a1a1a;font-size:16px;font-weight:700;cursor:pointer}.hint{font-size:12px;color:#999;margin-top:6px}.loading{display:none;text-align:center;margin-top:20px;font-size:14px;color:#ffd100}
</style>
</head>
<body><div class="card">
<div class="reel-info">🎬 <strong>Reel Builder locale attivo:</strong> video 9:16 creato nel browser con immagini dell'articolo + musica, poi salvato automaticamente sul server. Nessun servizio di rendering esterno.</div>
<h1>🏎️ Generatore Contenuti <span class="accent">F1</span></h1>
<p class="desc">Inserisci un <strong>testo</strong> o un <strong>URL dell'articolo</strong>. Verranno generati post social, infografiche HD e il pannello per creare il Reel 1080×1920 direttamente dal browser.</p>
<form action="process.php" method="post" id="genForm">
<label for="input_text">Testo oppure URL articolo</label>
<textarea id="input_text" name="input_text" placeholder="Incolla qui il testo della notizia, oppure un link FormulaPaddock..." required><?php echo htmlspecialchars($_GET['url'] ?? ''); ?></textarea>
<p class="hint">Se inserisci un URL, testo e immagini verranno estratti automaticamente dalla pagina.</p>
<label for="article_url">Link Articolo da allegare ai post (opzionale)</label>
<input type="text" id="article_url" name="article_url" placeholder="https://www.formulapaddock.it/titolo-articolo...">
<p class="hint">Usalo se nel campo principale hai incollato solo testo.</p>
<button type="submit">Genera contenuti & Apri Social Hub</button>
<div class="loading" id="loadingMsg">⏳ Generazione contenuti in corso...</div>
</form>
</div>
<script>
document.getElementById('genForm').addEventListener('submit',()=>{document.getElementById('loadingMsg').style.display='block'});
<?php if (!empty($_GET['url'])): ?>document.getElementById('genForm').submit();<?php endif; ?>
</script>
</body></html>
