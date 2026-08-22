<?php
/**
 * Test Reel FormulaPaddock generato direttamente nel browser.
 * Usa le immagini dell'articolo in slideshow. Nessun FFmpeg server e nessun servizio di rendering esterno.
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
$articleImages = [];
$imageUrls = [];

function fpAllowedArticleUrl(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return in_array($host, ['formulapaddock.it', 'www.formulapaddock.it'], true);
}

function fpAbsoluteUrl(string $baseUrl, string $src): string
{
    $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($src === '') return '';
    if (preg_match('~^https?://~i', $src)) return $src;
    if (str_starts_with($src, '//')) return 'https:' . $src;

    $parts = parse_url($baseUrl);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    if ($host === '') return '';
    if (str_starts_with($src, '/')) return $scheme . '://' . $host . $src;

    $path = $parts['path'] ?? '/';
    $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
    return $scheme . '://' . $host . ($dir !== '' ? $dir . '/' : '/') . ltrim($src, '/');
}

function fpBestSrcsetUrl(string $srcset, string $baseUrl): string
{
    $bestUrl = '';
    $bestWidth = 0;
    foreach (explode(',', $srcset) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') continue;
        $parts = preg_split('/\s+/', $candidate);
        $url = fpAbsoluteUrl($baseUrl, (string)($parts[0] ?? ''));
        $descriptor = (string)($parts[1] ?? '0w');
        $width = preg_match('/^(\d+)w$/', $descriptor, $m) ? (int)$m[1] : 0;
        if ($url !== '' && ($bestUrl === '' || $width >= $bestWidth)) {
            $bestUrl = $url;
            $bestWidth = $width;
        }
    }
    return $bestUrl;
}

function fpExtractArticleImages(string $url, int $maxImages = 12): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FormulaPaddock-Reel-Test/1.0)',
        CURLOPT_MAXREDIRS => 3,
    ]);
    $html = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($html === false || $err !== '' || $http >= 400) {
        throw new Exception('Impossibile leggere le immagini dell\'articolo.');
    }

    $encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // Cerca prima il vero corpo dell'articolo. In fallback usa article/main.
    $queries = [
        '//*[contains(concat(" ", normalize-space(@class), " "), " entry-content ")]//img',
        '//*[contains(concat(" ", normalize-space(@class), " "), " post-content ")]//img',
        '//*[contains(concat(" ", normalize-space(@class), " "), " article-content ")]//img',
        '//article//img',
        '//main//img',
    ];

    $nodes = null;
    foreach ($queries as $query) {
        $candidateNodes = $xpath->query($query);
        if ($candidateNodes && $candidateNodes->length > 0) {
            $nodes = $candidateNodes;
            break;
        }
    }
    if (!$nodes) return [];

    $result = [];
    $seen = [];
    foreach ($nodes as $img) {
        $class = strtolower((string)$img->getAttribute('class'));
        $alt = strtolower((string)$img->getAttribute('alt'));
        if (str_contains($class, 'avatar') || str_contains($class, 'emoji') || str_contains($class, 'logo') || str_contains($class, 'icon')) continue;
        if (str_contains($alt, 'avatar') || str_contains($alt, 'logo') || str_contains($alt, 'icona')) continue;

        $src = '';
        foreach (['data-srcset', 'srcset'] as $attr) {
            $value = trim((string)$img->getAttribute($attr));
            if ($value !== '') {
                $src = fpBestSrcsetUrl($value, $url);
                if ($src !== '') break;
            }
        }
        if ($src === '') {
            foreach (['data-lazy-src', 'data-src', 'data-original', 'src'] as $attr) {
                $value = trim((string)$img->getAttribute($attr));
                if ($value !== '' && !str_starts_with($value, 'data:')) {
                    $src = fpAbsoluteUrl($url, $value);
                    if ($src !== '') break;
                }
            }
        }
        if ($src === '' || !filter_var($src, FILTER_VALIDATE_URL)) continue;

        $host = strtolower((string)parse_url($src, PHP_URL_HOST));
        if (!in_array($host, ['formulapaddock.it', 'www.formulapaddock.it'], true)) continue;

        $path = strtolower((string)parse_url($src, PHP_URL_PATH));
        if (preg_match('~(?:logo|avatar|emoji|favicon|icon)[^/]*\.(?:jpe?g|png|webp)$~i', basename($path))) continue;

        $key = preg_replace('/[#?].*$/', '', $src);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $result[] = $src;
        if (count($result) >= $maxImages) break;
    }

    return $result;
}

function fpImageToDataUrl(string $url): string
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return '';
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

    // Limite per singola immagine, per non appesantire troppo la pagina di test.
    if ($bytes === false || $err !== '' || $http >= 400 || strlen($bytes) < 1000 || strlen($bytes) > 8 * 1024 * 1024) return '';

    $type = strtolower(trim(explode(';', $type)[0] ?? ''));
    if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? finfo_buffer($finfo, $bytes) : '';
        if ($finfo) finfo_close($finfo);
        if (in_array($detected, ['image/jpeg', 'image/png', 'image/webp'], true)) $type = $detected;
    }
    if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) return '';

    // Filtra immagini minuscole anche quando width/height non erano presenti nell'HTML.
    $size = @getimagesizefromstring($bytes);
    if (is_array($size) && (($size[0] ?? 0) < 320 || ($size[1] ?? 0) < 180)) return '';

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

        $imageUrls = fpExtractArticleImages($articleUrl, 12);
        if ($article['image_url'] !== '') {
            $featured = fpAbsoluteUrl($articleUrl, $article['image_url']);
            if ($featured !== '' && !in_array($featured, $imageUrls, true)) array_unshift($imageUrls, $featured);
        }
        $imageUrls = array_slice(array_values(array_unique($imageUrls)), 0, 12);

        foreach ($imageUrls as $imgUrl) {
            $dataUrl = fpImageToDataUrl($imgUrl);
            if ($dataUrl !== '') $articleImages[] = $dataUrl;
        }
    } catch (Throwable $e) {
        $articleError = $e->getMessage();
    }
} else {
    $articleError = 'Per sicurezza il test accetta solo URL di formulapaddock.it.';
}

$excerpt = preg_replace('/\s+/u', ' ', $article['text']);
$excerpt = trim((string)$excerpt);
if (mb_strlen($excerpt) > 230) $excerpt = rtrim(mb_substr($excerpt, 0, 227)) . '…';
$imageCount = count($articleImages);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Test Reel Articolo — FormulaPaddock</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:28px 18px 50px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#09090d;color:#f5f5f5}.wrap{max-width:1120px;margin:0 auto}h1{margin:0 0 8px;font-size:28px}h2{margin-top:0}.sub,.small{color:#aaa;line-height:1.55}.sub{margin:0 0 22px}.card{border:1px solid #2b2b34;border-radius:14px;background:#131319;padding:20px;margin-bottom:16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}.item{background:#0d0d12;border:1px solid #24242d;border-radius:10px;padding:14px}.label{color:#8f8f9c;font-size:12px;text-transform:uppercase;letter-spacing:.06em}.value{margin-top:6px;font-size:14px;overflow-wrap:anywhere}.ok{color:#57df96}.bad{color:#ff7777}.warn{color:#ffd166}.pill{display:inline-block;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:800}.pill.ok{background:rgba(31,157,98,.16);color:#57df96}.pill.bad{background:rgba(201,69,69,.16);color:#ff7777}button,.btn{appearance:none;border:0;border-radius:9px;background:#ffd100;color:#111;font-weight:900;padding:12px 18px;cursor:pointer;text-decoration:none;display:inline-block}button:disabled{opacity:.45;cursor:not-allowed}.stage-grid{display:grid;grid-template-columns:minmax(260px,360px) 1fr;gap:22px;align-items:start}@media(max-width:780px){.stage-grid{grid-template-columns:1fr}}canvas,video{width:100%;aspect-ratio:9/16;border-radius:12px;background:#000;border:1px solid #333;display:block}.status{padding:12px 14px;border-radius:9px;background:#0d0d12;border:1px solid #2b2b34;margin:12px 0;min-height:46px;line-height:1.5}code{color:#ffd166;word-break:break-word}a{color:#ffd100}.url-form{display:flex;gap:10px;flex-wrap:wrap}.url-form input{flex:1;min-width:280px;background:#09090d;color:#fff;border:1px solid #3a3a44;border-radius:9px;padding:12px}.article-title{font-size:18px;font-weight:800;line-height:1.35;margin:6px 0 0}.buttons{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;margin-top:12px}.thumb{aspect-ratio:16/10;object-fit:cover;width:100%;border-radius:8px;border:1px solid #333}
</style>
</head>
<body>
<div class="wrap">
<h1>🎬 Reel Browser — tutte le immagini dell’articolo</h1>
<p class="sub">Il Reel viene renderizzato sul tuo PC a 1080×1920 usando in sequenza le immagini del contenuto. Nessun OnRender e nessun FFmpeg server.</p>

<section class="card">
<h2>Articolo usato</h2>
<form class="url-form" method="get">
<input type="url" name="url" value="<?= htmlspecialchars($articleUrl, ENT_QUOTES) ?>" required>
<button type="submit">Carica articolo</button>
</form>
<?php if ($articleError): ?><p class="bad"><strong>⚠ <?= htmlspecialchars($articleError) ?></strong></p><?php endif; ?>
<div class="grid" style="margin-top:14px">
<div class="item"><div class="label">Titolo</div><div class="article-title"><?= htmlspecialchars($article['title']) ?></div></div>
<div class="item"><div class="label">Immagini utilizzabili</div><div class="value"><?= $imageCount > 0 ? '<span class="pill ok">' . $imageCount . ' CARICATE</span>' : '<span class="pill bad">NESSUNA</span>' ?></div></div>
</div>
<?php if ($imageCount > 0): ?>
<div class="thumbs"><?php foreach ($articleImages as $i => $data): ?><img class="thumb" src="<?= htmlspecialchars($data, ENT_QUOTES) ?>" alt="Immagine <?= $i + 1 ?>"><?php endforeach; ?></div>
<?php endif; ?>
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
<p>Il Reel usa <strong><?= $imageCount ?></strong> immagini, con zoom leggero e dissolvenza. Durata automatica in base al numero di foto, massimo 18 secondi. Output <strong>1080×1920 / 30 fps / 12 Mbps</strong>.</p>
<button id="generateBtn" type="button" disabled>▶ Genera Reel MP4</button>
<div class="status" id="renderStatus">In attesa.</div>
<div id="resultBox" hidden>
<p><strong>Anteprima MP4</strong></p>
<video id="previewVideo" controls playsinline></video>
<p id="resultInfo" class="small"></p>
<div class="buttons"><a id="downloadBtn" class="btn" download="formulapaddock_zandvoort_slideshow.mp4">⬇ Scarica MP4</a></div>
</div>
</div>
</div>
</section>

<section class="card"><p class="small">Sono escluse automaticamente immagini minuscole, logo, avatar, icone e duplicati. Per sicurezza il test usa al massimo 12 immagini dell’articolo.</p><p><a href="index.php">← Social Hub</a> · <a href="test_ffmpeg.php">Test server</a></p></section>
</div>
<script>
(() => {
const ARTICLE_TITLE = <?= json_encode($article['title'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const ARTICLE_EXCERPT = <?= json_encode($excerpt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const IMAGE_DATA = <?= json_encode($articleImages, JSON_UNESCAPED_SLASHES) ?>;
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

const photos=[];let loadedCount=0;
function loadPhotos(){return Promise.all(IMAGE_DATA.map((src,index)=>new Promise(resolve=>{const img=new Image();img.onload=()=>{photos[index]=img;loadedCount++;resolve()};img.onerror=()=>resolve();img.src=src}))).then(()=>{const clean=photos.filter(Boolean);photos.length=0;clean.forEach(x=>photos.push(x));});}

function roundRect(x,y,w,h,r,fill,stroke=null,lw=1){ctx.beginPath();ctx.roundRect(x,y,w,h,r);ctx.fillStyle=fill;ctx.fill();if(stroke){ctx.strokeStyle=stroke;ctx.lineWidth=lw;ctx.stroke()}}
function wrap(text,maxWidth,font,maxLines=10){ctx.font=font;const words=(text||'').split(/\s+/),lines=[];let cur='';for(const word of words){const test=cur?cur+' '+word:word;if(ctx.measureText(test).width>maxWidth&&cur){lines.push(cur);cur=word;if(lines.length>=maxLines-1)break}else cur=test}if(cur&&lines.length<maxLines)lines.push(cur);return lines}
function drawCover(img,x,y,w,h,scale=1,alpha=1){const ir=img.width/img.height,br=w/h;let sw,sh,sx,sy;if(ir>br){sh=img.height;sw=sh*br;sx=(img.width-sw)/2;sy=0}else{sw=img.width;sh=sw/br;sx=0;sy=(img.height-sh)/2}const dw=w*scale,dh=h*scale;ctx.save();ctx.globalAlpha=alpha;ctx.drawImage(img,sx,sy,sw,sh,x-(dw-w)/2,y-(dh-h)/2,dw,dh);ctx.restore()}
function slideshow(progress){if(!photos.length)return false;const count=photos.length;const pos=Math.min(count-0.0001,progress*count);const index=Math.min(count-1,Math.floor(pos));const local=pos-index;const next=Math.min(count-1,index+1);const fade=next!==index?Math.max(0,Math.min(1,(local-.70)/.30)):0;drawCover(photos[index],0,0,1080,1920,1+local*.055,1);if(fade>0)drawCover(photos[next],0,0,1080,1920,1+Math.max(0,local-.70)*.03,fade);return true}
function drawFrame(progress){const w=1080,h=1920;ctx.fillStyle='#08090d';ctx.fillRect(0,0,w,h);if(!slideshow(progress)){const g=ctx.createLinearGradient(0,0,w,h);g.addColorStop(0,'#14090c');g.addColorStop(1,'#050507');ctx.fillStyle=g;ctx.fillRect(0,0,w,h)}
let shade=ctx.createLinearGradient(0,0,0,h);shade.addColorStop(0,'rgba(0,0,0,.30)');shade.addColorStop(.45,'rgba(0,0,0,.08)');shade.addColorStop(.68,'rgba(0,0,0,.60)');shade.addColorStop(1,'rgba(0,0,0,.96)');ctx.fillStyle=shade;ctx.fillRect(0,0,w,h);
roundRect(48,52,500,82,17,'rgba(0,0,0,.78)','#e10600',4);ctx.fillStyle='#fff';ctx.font='900 36px Arial,sans-serif';ctx.fillText('FORMULAPADDOCK.IT',78,106);
roundRect(48,1380,984,410,28,'rgba(0,0,0,.82)','rgba(255,255,255,.14)',2);ctx.fillStyle='#e10600';ctx.fillRect(48,1380,14,410);ctx.fillStyle='#ffd100';ctx.font='900 27px Arial,sans-serif';ctx.fillText('ZANDVOORT • SPRINT QUALIFYING',92,1440);
const titleFont='900 54px Arial,sans-serif';ctx.fillStyle='#fff';let y=1510;const lines=wrap(ARTICLE_TITLE,860,titleFont,5);ctx.font=titleFont;for(const line of lines){ctx.fillText(line,92,y);y+=65}
if(y<1740){ctx.fillStyle='#d5d5dc';const exFont='500 28px Arial,sans-serif';const exLines=wrap(ARTICLE_EXCERPT,860,exFont,2);ctx.font=exFont;y+=12;for(const line of exLines){ctx.fillText(line,92,y);y+=39}}
const cta=Math.max(0,(progress-.78)/.12);ctx.save();ctx.globalAlpha=Math.min(1,cta);roundRect(240,1815,600,62,18,'#e10600');ctx.fillStyle='#fff';ctx.textAlign='center';ctx.font='900 28px Arial,sans-serif';ctx.fillText('SEGUI FORMULAPADDOCK.IT',540,1856);ctx.restore();ctx.textAlign='left';}

drawFrame(0);
const compatible=captureOk&&recorderOk&&!!chosenMime&&IMAGE_DATA.length>0;
compatStatus.innerHTML=(captureOk&&recorderOk&&!!chosenMime)?`<span class="ok"><strong>✅ Browser pronto.</strong></span> Useremo <code>${chosenMime}</code>.`:'<span class="bad"><strong>❌ MP4/H.264 non disponibile in questo browser.</strong></span>';

loadPhotos().then(()=>{drawFrame(0);if(compatible&&photos.length>0){generateBtn.disabled=false;renderStatus.innerHTML=`<span class="ok"><strong>✅ ${photos.length} immagini pronte.</strong></span> Puoi generare il Reel.`}else if(!photos.length){renderStatus.innerHTML='<span class="bad"><strong>❌ Nessuna immagine valida caricata.</strong></span>'}});

let objectUrl='';generateBtn.addEventListener('click',async()=>{if(!compatible||!photos.length)return;generateBtn.disabled=true;resultBox.hidden=true;if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=''}const duration=Math.min(18000,Math.max(8000,photos.length*1500+1500));renderStatus.innerHTML=`<span class="warn"><strong>🎬 Rendering in corso…</strong></span> ${photos.length} immagini · ${(duration/1000).toFixed(1)} secondi.`;
const stream=canvas.captureStream(30),chunks=[];let recorder;try{recorder=new MediaRecorder(stream,{mimeType:chosenMime,videoBitsPerSecond:12000000})}catch(e){renderStatus.innerHTML='<span class="bad"><strong>Errore MediaRecorder:</strong> '+e.message+'</span>';generateBtn.disabled=false;return}
recorder.ondataavailable=e=>{if(e.data&&e.data.size>0)chunks.push(e.data)};const stopped=new Promise(resolve=>recorder.onstop=resolve);recorder.start(1000);const start=performance.now();
await new Promise(resolve=>{function frame(now){const p=Math.min(1,(now-start)/duration);drawFrame(p);renderStatus.innerHTML=`<span class="warn"><strong>🎬 Rendering ${(p*100).toFixed(0)}%</strong></span> — foto ${Math.min(photos.length,Math.floor(p*photos.length)+1)}/${photos.length}`;if(p<1)requestAnimationFrame(frame);else resolve()}requestAnimationFrame(frame)});recorder.stop();await stopped;stream.getTracks().forEach(t=>t.stop());
const blob=new Blob(chunks,{type:chosenMime});if(blob.size<10000){renderStatus.innerHTML='<span class="bad"><strong>❌ Il browser ha prodotto un file troppo piccolo.</strong></span>';generateBtn.disabled=false;return}objectUrl=URL.createObjectURL(blob);previewVideo.src=objectUrl;downloadBtn.href=objectUrl;const mb=(blob.size/1024/1024).toFixed(2);resultInfo.innerHTML=`Formato: <code>${chosenMime}</code> · <strong>${photos.length} immagini</strong> · ${(duration/1000).toFixed(1)} s · <strong>${mb} MB</strong> · 1080×1920`;resultBox.hidden=false;renderStatus.innerHTML='<span class="ok"><strong>✅ Reel slideshow creato.</strong></span> Guardalo qui sotto e controlla qualità, ritmo e transizioni.';generateBtn.disabled=false;});
})();
</script>
</body>
</html>