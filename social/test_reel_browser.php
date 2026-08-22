<?php
/**
 * Test Reel FormulaPaddock generato direttamente nel browser.
 * Nessun FFmpeg server e nessun servizio di rendering esterno.
 */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$defaultUrl = 'https://www.formulapaddock.it/gran_premi/meteo-gp-olanda-f1-2026-previsioni-pioggia-e-insidie-a-zandvoort/zandvoort-sprint-qualifying-russell-illumina-mclaren-e-ferrari-a-ridosso-in-una-sessione-da-brividi/';
$articleUrl = trim((string)($_GET['url'] ?? $defaultUrl));
$article = [
    'title' => 'Zandvoort Sprint Qualifying: Russell illumina, McLaren e Ferrari a ridosso',
    'text' => 'Formula 1 a Zandvoort: prova reale del nuovo Reel FormulaPaddock generato direttamente nel browser.',
    'image_url' => '',
];
$articleError = null;
$imageDataUrl = '';

function fpAllowedArticleUrl(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return in_array($host, ['formulapaddock.it', 'www.formulapaddock.it'], true);
}

function fpImageToDataUrl(string $url): string
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return '';

    // Per il test accettiamo solo immagini FormulaPaddock, evitando proxy aperti/SSRF.
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (!in_array($host, ['formulapaddock.it', 'www.formulapaddock.it'], true)) return '';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FormulaPaddock-Reel-Test/1.0)',
        CURLOPT_MAXREDIRS => 3,
    ]);
    $bytes = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = trim((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    $err = curl_error($ch);
    curl_close($ch);

    if ($bytes === false || $err !== '' || $http >= 400 || strlen($bytes) < 1000 || strlen($bytes) > 12 * 1024 * 1024) {
        return '';
    }

    $type = strtolower(trim(explode(';', $type)[0] ?? ''));
    if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $finfo ? finfo_buffer($finfo, $bytes) : '';
            if ($finfo) finfo_close($finfo);
            if (in_array($detected, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $type = $detected;
            }
        }
    }
    if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) return '';

    return 'data:' . $type . ';base64,' . base64_encode($bytes);
}

if (fpAllowedArticleUrl($articleUrl)) {
    try {
        require_once __DIR__ . '/includes/url_extractor.php';
        $extracted = extractTextFromUrl($articleUrl);
        $article = [
            'title' => trim((string)($extracted['title'] ?? $article['title'])),
            'text' => trim((string)($extracted['text'] ?? $article['text'])),
            'image_url' => trim((string)($extracted['image_url'] ?? '')),
        ];
        $imageDataUrl = fpImageToDataUrl($article['image_url']);
    } catch (Throwable $e) {
        $articleError = $e->getMessage();
    }
} else {
    $articleError = 'Per sicurezza il test accetta solo URL di formulapaddock.it.';
}

$excerpt = preg_replace('/\s+/u', ' ', $article['text']);
$excerpt = trim((string)$excerpt);
if (mb_strlen($excerpt) > 230) {
    $excerpt = rtrim(mb_substr($excerpt, 0, 227)) . '…';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Test Reel Articolo — FormulaPaddock</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:28px 18px 50px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#09090d;color:#f5f5f5}.wrap{max-width:1120px;margin:0 auto}h1{margin:0 0 8px;font-size:28px}h2{margin-top:0}.sub,.small{color:#aaa;line-height:1.55}.sub{margin:0 0 22px}.card{border:1px solid #2b2b34;border-radius:14px;background:#131319;padding:20px;margin-bottom:16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}.item{background:#0d0d12;border:1px solid #24242d;border-radius:10px;padding:14px}.label{color:#8f8f9c;font-size:12px;text-transform:uppercase;letter-spacing:.06em}.value{margin-top:6px;font-size:14px;overflow-wrap:anywhere}.ok{color:#57df96}.bad{color:#ff7777}.warn{color:#ffd166}.pill{display:inline-block;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:800}.pill.ok{background:rgba(31,157,98,.16);color:#57df96}.pill.bad{background:rgba(201,69,69,.16);color:#ff7777}button,.btn{appearance:none;border:0;border-radius:9px;background:#ffd100;color:#111;font-weight:900;padding:12px 18px;cursor:pointer;text-decoration:none;display:inline-block}button:disabled{opacity:.45;cursor:not-allowed}.secondary{background:#292934;color:#fff;border:1px solid #444}.stage-grid{display:grid;grid-template-columns:minmax(260px,360px) 1fr;gap:22px;align-items:start}@media(max-width:780px){.stage-grid{grid-template-columns:1fr}}canvas,video{width:100%;aspect-ratio:9/16;border-radius:12px;background:#000;border:1px solid #333;display:block}.status{padding:12px 14px;border-radius:9px;background:#0d0d12;border:1px solid #2b2b34;margin:12px 0;min-height:46px;line-height:1.5}code{color:#ffd166;word-break:break-word}a{color:#ffd100}.url-form{display:flex;gap:10px;flex-wrap:wrap}.url-form input{flex:1;min-width:280px;background:#09090d;color:#fff;border:1px solid #3a3a44;border-radius:9px;padding:12px}.article-title{font-size:18px;font-weight:800;line-height:1.35;margin:6px 0 0}.buttons{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
</style>
</head>
<body>
<div class="wrap">
<h1>🎬 Reel Browser — prova con articolo reale</h1>
<p class="sub">Il Reel viene renderizzato sul tuo PC a 1080×1920. Il server FormulaPaddock serve soltanto titolo e immagine dell’articolo: nessun OnRender e nessun FFmpeg server.</p>

<section class="card">
<h2>Articolo usato</h2>
<form class="url-form" method="get">
<input type="url" name="url" value="<?= htmlspecialchars($articleUrl, ENT_QUOTES) ?>" required>
<button type="submit">Carica articolo</button>
</form>
<?php if ($articleError): ?><p class="bad"><strong>⚠ <?= htmlspecialchars($articleError) ?></strong></p><?php endif; ?>
<div class="grid" style="margin-top:14px">
<div class="item"><div class="label">Titolo</div><div class="article-title"><?= htmlspecialchars($article['title']) ?></div></div>
<div class="item"><div class="label">Immagine in evidenza</div><div class="value"><?= $imageDataUrl !== '' ? '<span class="pill ok">CARICATA</span>' : '<span class="pill bad">NON CARICATA</span>' ?></div></div>
</div>
</section>

<section class="card">
<h2>Compatibilità del tuo browser</h2>
<div class="grid">
<div class="item"><div class="label">Browser</div><div class="value" id="browserInfo">—</div></div>
<div class="item"><div class="label">Canvas captureStream</div><div class="value" id="captureSupport">—</div></div>
<div class="item"><div class="label">MediaRecorder</div><div class="value" id="recorderSupport">—</div></div>
<div class="item"><div class="label">MP4 / H.264</div><div class="value" id="mp4Support">—</div></div>
</div>
<div class="status" id="compatStatus">Controllo in corso…</div>
</section>

<section class="card">
<h2>Genera il Reel Zandvoort</h2>
<div class="stage-grid">
<div><canvas id="reelCanvas" width="1080" height="1920"></canvas></div>
<div>
<p>Durata <strong>8 secondi</strong>, <strong>1080×1920</strong>, <strong>30 fps</strong>, bitrate richiesto <strong>12 Mbps</strong>. L’immagine ha un leggero movimento di zoom e il testo resta nell’area sicura verticale.</p>
<button id="generateBtn" type="button" disabled>▶ Genera Reel MP4</button>
<div class="status" id="renderStatus">In attesa.</div>
<div id="resultBox" hidden>
<p><strong>Anteprima MP4</strong></p>
<video id="previewVideo" controls playsinline></video>
<p id="resultInfo" class="small"></p>
<div class="buttons"><a id="downloadBtn" class="btn" download="formulapaddock_zandvoort_reel.mp4">⬇ Scarica MP4</a></div>
</div>
</div>
</div>
</section>

<section class="card"><p class="small">Il file video viene creato nella memoria del browser. In questa fase di prova non viene ancora caricato automaticamente su FormulaPaddock o sui social.</p><p><a href="index.php">← Social Hub</a> · <a href="test_ffmpeg.php">Test server</a></p></section>
</div>
<script>
(() => {
const ARTICLE_TITLE = <?= json_encode($article['title'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const ARTICLE_EXCERPT = <?= json_encode($excerpt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const IMAGE_DATA = <?= json_encode($imageDataUrl, JSON_UNESCAPED_SLASHES) ?>;
const canvas=document.getElementById('reelCanvas'),ctx=canvas.getContext('2d');
const generateBtn=document.getElementById('generateBtn'),renderStatus=document.getElementById('renderStatus'),compatStatus=document.getElementById('compatStatus');
const resultBox=document.getElementById('resultBox'),previewVideo=document.getElementById('previewVideo'),resultInfo=document.getElementById('resultInfo'),downloadBtn=document.getElementById('downloadBtn');
const mimeCandidates=['video/mp4;codecs=avc1.42E01E','video/mp4;codecs=avc1.42001f','video/mp4;codecs=avc1.4D401F','video/mp4'];
function pill(ok,yes='SÌ',no='NO'){return `<span class="pill ${ok?'ok':'bad'}">${ok?yes:no}</span>`}
document.getElementById('browserInfo').textContent=navigator.userAgent;
const captureOk=typeof canvas.captureStream==='function',recorderOk=typeof window.MediaRecorder!=='undefined';
document.getElementById('captureSupport').innerHTML=pill(captureOk);document.getElementById('recorderSupport').innerHTML=pill(recorderOk);
let chosenMime='';if(recorderOk&&typeof MediaRecorder.isTypeSupported==='function'){chosenMime=mimeCandidates.find(t=>MediaRecorder.isTypeSupported(t))||''}
document.getElementById('mp4Support').innerHTML=chosenMime?`<span class="pill ok">SUPPORTATO</span><div class="small" style="margin-top:6px"><code>${chosenMime}</code></div>`:'<span class="pill bad">NON SUPPORTATO</span>';
const compatible=captureOk&&recorderOk&&!!chosenMime;generateBtn.disabled=!compatible;
compatStatus.innerHTML=compatible?`<span class="ok"><strong>✅ Browser pronto.</strong></span> Useremo <code>${chosenMime}</code>.`:'<span class="bad"><strong>❌ MP4/H.264 non disponibile in questo browser.</strong></span> In quel caso passeremo al fallback WebCodecs/ffmpeg.wasm.';

const photo=new Image();let photoReady=false;
photo.onload=()=>{photoReady=true;drawFrame(0)};photo.onerror=()=>{photoReady=false;drawFrame(0)};if(IMAGE_DATA)photo.src=IMAGE_DATA;

function roundRect(x,y,w,h,r,fill,stroke=null,lw=1){ctx.beginPath();ctx.roundRect(x,y,w,h,r);ctx.fillStyle=fill;ctx.fill();if(stroke){ctx.strokeStyle=stroke;ctx.lineWidth=lw;ctx.stroke()}}
function wrap(text,maxWidth,font,maxLines=10){ctx.font=font;const words=(text||'').split(/\s+/),lines=[];let cur='';for(const word of words){const test=cur?cur+' '+word:word;if(ctx.measureText(test).width>maxWidth&&cur){lines.push(cur);cur=word;if(lines.length>=maxLines-1)break}else cur=test}if(cur&&lines.length<maxLines)lines.push(cur);return lines}
function drawCover(img,x,y,w,h,scale=1){const ir=img.width/img.height,br=w/h;let sw,sh,sx,sy;if(ir>br){sh=img.height;sw=sh*br;sx=(img.width-sw)/2;sy=0}else{sw=img.width;sh=sw/br;sx=0;sy=(img.height-sh)/2}const dw=w*scale,dh=h*scale;ctx.drawImage(img,sx,sy,sw,sh,x-(dw-w)/2,y-(dh-h)/2,dw,dh)}
function drawFrame(progress){const w=1080,h=1920;ctx.fillStyle='#08090d';ctx.fillRect(0,0,w,h);
if(photoReady){ctx.save();drawCover(photo,0,0,w,h,1+progress*.055);ctx.restore()}else{const g=ctx.createLinearGradient(0,0,w,h);g.addColorStop(0,'#14090c');g.addColorStop(1,'#050507');ctx.fillStyle=g;ctx.fillRect(0,0,w,h)}
let shade=ctx.createLinearGradient(0,0,0,h);shade.addColorStop(0,'rgba(0,0,0,.28)');shade.addColorStop(.38,'rgba(0,0,0,.12)');shade.addColorStop(.62,'rgba(0,0,0,.56)');shade.addColorStop(1,'rgba(0,0,0,.96)');ctx.fillStyle=shade;ctx.fillRect(0,0,w,h);
roundRect(48,52,500,82,17,'rgba(0,0,0,.78)','#e10600',4);ctx.fillStyle='#fff';ctx.font='900 36px Arial,sans-serif';ctx.fillText('FORMULAPADDOCK.IT',78,106);
roundRect(48,1380,984,410,28,'rgba(0,0,0,.82)','rgba(255,255,255,.14)',2);ctx.fillStyle='#e10600';ctx.fillRect(48,1380,14,410);
ctx.fillStyle='#ffd100';ctx.font='900 27px Arial,sans-serif';ctx.fillText('ZANDVOORT • SPRINT QUALIFYING',92,1440);
const titleFont='900 54px Arial,sans-serif';ctx.fillStyle='#fff';let y=1510;const lines=wrap(ARTICLE_TITLE,860,titleFont,5);ctx.font=titleFont;for(const line of lines){ctx.fillText(line,92,y);y+=65}
if(y<1740){ctx.fillStyle='#d5d5dc';const exFont='500 28px Arial,sans-serif';const exLines=wrap(ARTICLE_EXCERPT,860,exFont,2);ctx.font=exFont;y+=12;for(const line of exLines){ctx.fillText(line,92,y);y+=39}}
const cta=Math.max(0,(progress-.68)/.16);ctx.save();ctx.globalAlpha=Math.min(1,cta);roundRect(240,1815,600,62,18,'#e10600');ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 28px Arial,sans-serif';ctx.fillText('SEGUI FORMULAPADDOCK.IT',540,1856);ctx.restore();ctx.textAlign='left';}
drawFrame(0);

let objectUrl='';generateBtn.addEventListener('click',async()=>{if(!compatible)return;generateBtn.disabled=true;resultBox.hidden=true;if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=''}renderStatus.innerHTML='<span class="warn"><strong>🎬 Rendering in corso…</strong></span> Il browser sta registrando 8 secondi reali.';
const stream=canvas.captureStream(30),chunks=[];let recorder;try{recorder=new MediaRecorder(stream,{mimeType:chosenMime,videoBitsPerSecond:12000000})}catch(e){renderStatus.innerHTML='<span class="bad"><strong>Errore MediaRecorder:</strong> '+e.message+'</span>';generateBtn.disabled=false;return}
recorder.ondataavailable=e=>{if(e.data&&e.data.size>0)chunks.push(e.data)};const stopped=new Promise(resolve=>recorder.onstop=resolve);recorder.start(1000);const duration=8000,start=performance.now();
await new Promise(resolve=>{function frame(now){const p=Math.min(1,(now-start)/duration);drawFrame(p);renderStatus.innerHTML=`<span class="warn"><strong>🎬 Rendering ${(p*100).toFixed(0)}%</strong></span> — 1080×1920 / 30 fps`;if(p<1)requestAnimationFrame(frame);else resolve()}requestAnimationFrame(frame)});recorder.stop();await stopped;stream.getTracks().forEach(t=>t.stop());
const blob=new Blob(chunks,{type:chosenMime});if(blob.size<10000){renderStatus.innerHTML='<span class="bad"><strong>❌ Il browser ha prodotto un file troppo piccolo.</strong></span>';generateBtn.disabled=false;return}objectUrl=URL.createObjectURL(blob);previewVideo.src=objectUrl;downloadBtn.href=objectUrl;const mb=(blob.size/1024/1024).toFixed(2);resultInfo.innerHTML=`Formato: <code>${chosenMime}</code> · Dimensione: <strong>${mb} MB</strong> · 1080×1920 · 8 s`;resultBox.hidden=false;renderStatus.innerHTML='<span class="ok"><strong>✅ Reel creato nel browser.</strong></span> Guardalo qui sotto e controlla soprattutto nitidezza e fluidità.';generateBtn.disabled=false;});
})();
</script>
</body>
</html>
