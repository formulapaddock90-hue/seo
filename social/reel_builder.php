<?php
/**
 * reel_builder.php — Generatore Reel browser-side di produzione.
 * Crea il video sul PC dell'utente e lo salva poi sul server via upload_reel.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$sessionUrl = trim((string)($_SESSION['last_social_source_url'] ?? ''));
$url = trim((string)($_GET['url'] ?? $sessionUrl));
$embed = !empty($_GET['embed']);
$content = $_SESSION['last_social_content'] ?? [];
$category = trim((string)($content['categoria'] ?? 'FORMULA 1 • NEWS'));
$article = [
    'title' => trim((string)($_SESSION['last_social_title'] ?? 'Formula 1 News')),
    'text' => '',
    'image_url' => '',
];
$error = null;
$images = [];

if (empty($_SESSION['reel_upload_csrf'])) {
    $_SESSION['reel_upload_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['reel_upload_csrf'];

function fpHostOk(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $h = strtolower((string)parse_url($url, PHP_URL_HOST));
    return in_array($h, ['formulapaddock.it', 'www.formulapaddock.it'], true);
}
function fpAbs(string $base, string $src): string
{
    $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($src === '') return '';
    if (preg_match('~^https?://~i', $src)) return $src;
    if (str_starts_with($src, '//')) return 'https:' . $src;
    $p = parse_url($base);
    $scheme = $p['scheme'] ?? 'https';
    $host = $p['host'] ?? '';
    if ($host === '') return '';
    if (str_starts_with($src, '/')) return "$scheme://$host$src";
    $dir = rtrim(str_replace('\\', '/', dirname($p['path'] ?? '/')), '/');
    return "$scheme://$host" . ($dir !== '' ? $dir . '/' : '/') . ltrim($src, '/');
}
function fpCurl(string $url, int $max = 0): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (FormulaPaddock-Reel-Builder)',
    ]);
    $b = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($b === false || $err !== '' || $http >= 400) return ['', ''];
    if ($max > 0 && strlen($b) > $max) return ['', ''];
    return [$b, strtolower(trim(explode(';', $type)[0] ?? ''))];
}
function fpBestSrcset(string $srcset, string $base): string
{
    $best = '';
    $bw = -1;
    foreach (explode(',', $srcset) as $part) {
        $p = preg_split('/\s+/', trim($part));
        $u = fpAbs($base, (string)($p[0] ?? ''));
        $d = (string)($p[1] ?? '0w');
        $w = preg_match('/^(\d+)w$/', $d, $m) ? (int)$m[1] : 0;
        if ($u !== '' && $w >= $bw) { $best = $u; $bw = $w; }
    }
    return $best;
}
function fpImageData(string $url): string
{
    if (!fpHostOk($url)) return '';
    [$b, $type] = fpCurl($url, 8 * 1024 * 1024);
    if ($b === '' || strlen($b) < 1000) return '';
    if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true) && function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $t = $f ? finfo_buffer($f, $b) : '';
        if ($f) finfo_close($f);
        if (in_array($t, ['image/jpeg', 'image/png', 'image/webp'], true)) $type = $t;
    }
    if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) return '';
    $s = @getimagesizefromstring($b);
    if (is_array($s) && (($s[0] ?? 0) < 320 || ($s[1] ?? 0) < 180)) return '';
    return 'data:' . $type . ';base64,' . base64_encode($b);
}
function fpArticleImages(string $url, int $max = 12): array
{
    [$html] = fpCurl($url);
    if ($html === '') return [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);
    $nodes = $xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," entry-content ")]//img | //*[contains(concat(" ",normalize-space(@class)," ")," post-content ")]//img | //article//img | //main//img');
    if (!$nodes) return [];
    $out = [];
    $seen = [];
    foreach ($nodes as $img) {
        $class = strtolower((string)$img->getAttribute('class'));
        $alt = strtolower((string)$img->getAttribute('alt'));
        if (preg_match('/avatar|emoji|logo|icon/', $class . ' ' . $alt)) continue;
        $src = '';
        foreach (['data-srcset', 'srcset'] as $a) {
            $v = trim((string)$img->getAttribute($a));
            if ($v !== '') { $src = fpBestSrcset($v, $url); if ($src !== '') break; }
        }
        if ($src === '') {
            foreach (['data-lazy-src', 'data-src', 'data-original', 'src'] as $a) {
                $v = trim((string)$img->getAttribute($a));
                if ($v !== '' && !str_starts_with($v, 'data:')) { $src = fpAbs($url, $v); if ($src !== '') break; }
            }
        }
        if (!fpHostOk($src)) continue;
        $key = (string)preg_replace('/[#?].*$/', '', $src);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $src;
        if (count($out) >= $max) break;
    }
    return $out;
}
function fpMusic(): array
{
    $dir = __DIR__ . '/music';
    if (!is_dir($dir) || !is_readable($dir)) return [];
    $ok = ['mp3', 'm4a', 'aac', 'wav', 'ogg'];
    $out = [];
    foreach (scandir($dir) ?: [] as $f) {
        $p = $dir . '/' . $f;
        $e = strtolower((string)pathinfo($f, PATHINFO_EXTENSION));
        if (is_file($p) && is_readable($p) && in_array($e, $ok, true)) {
            $out[] = ['name' => $f, 'url' => 'music/' . rawurlencode($f)];
        }
    }
    usort($out, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return $out;
}

if (!fpHostOk($url)) {
    $error = 'Per generare il Reel serve un articolo FormulaPaddock valido.';
} else {
    try {
        require_once __DIR__ . '/includes/url_extractor.php';
        $x = extractTextFromUrl($url);
        $article = [
            'title' => trim((string)($x['title'] ?? $article['title'])),
            'text' => trim((string)($x['text'] ?? '')),
            'image_url' => trim((string)($x['image_url'] ?? '')),
        ];
        $urls = fpArticleImages($url, 12);
        if ($article['image_url'] !== '') {
            $f = fpAbs($url, $article['image_url']);
            if ($f !== '' && !in_array($f, $urls, true)) array_unshift($urls, $f);
        }
        foreach (array_slice(array_values(array_unique($urls)), 0, 12) as $u) {
            $d = fpImageData($u);
            if ($d !== '') $images[] = $d;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$music = fpMusic();
$track = $music ? $music[array_rand($music)] : null;
$excerpt = trim((string)preg_replace('/\s+/u', ' ', $article['text']));
if (mb_strlen($excerpt) > 230) $excerpt = rtrim(mb_substr($excerpt, 0, 227)) . '…';
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Reel Builder FormulaPaddock</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:<?= $embed ? '14px' : '28px 18px 50px' ?>;font-family:Arial,sans-serif;background:#09090d;color:#f5f5f5}.wrap{max-width:1120px;margin:auto}.card{background:#131319;border:1px solid #2b2b34;border-radius:14px;padding:20px;margin-bottom:16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}.item{background:#0d0d12;border:1px solid #24242d;border-radius:10px;padding:14px}.label{color:#8f8f9c;font-size:12px;text-transform:uppercase}.value{margin-top:6px}.ok{color:#57df96}.bad{color:#ff7777}.warn{color:#ffd166}.pill{display:inline-block;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:800;background:#202028}.stage{display:grid;grid-template-columns:minmax(260px,360px) 1fr;gap:22px}@media(max-width:780px){.stage{grid-template-columns:1fr}}canvas,video{width:100%;aspect-ratio:9/16;background:#000;border:1px solid #333;border-radius:12px;display:block}audio{width:100%;margin-top:10px;display:block}button,.btn{border:0;border-radius:9px;background:#ffd100;color:#111;font-weight:900;padding:12px 18px;cursor:pointer;text-decoration:none;display:inline-block}button:disabled{opacity:.45}.status{padding:12px 14px;background:#0d0d12;border:1px solid #2b2b34;border-radius:9px;margin:12px 0}.thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;margin-top:12px}.thumb{width:100%;aspect-ratio:16/10;object-fit:cover;border-radius:8px}.small{color:#aaa;font-size:13px;line-height:1.5}code{color:#ffd166}a{color:#ffd100}.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
</style>
</head>
<body><div class="wrap">
<h1>🎬 Reel FormulaPaddock</h1>
<p class="small">Generazione locale nel browser, immagini dell'articolo + musica da <code>social/music</code>. L'MP4 viene poi salvato automaticamente sul server.</p>
<section class="card">
<div class="grid"><div class="item"><div class="label">Titolo</div><div class="value"><strong><?=htmlspecialchars($article['title'])?></strong></div></div><div class="item"><div class="label">Immagini</div><div class="value"><span class="pill"><?=count($images)?> CARICATE</span></div></div><div class="item"><div class="label">Musica</div><div class="value"><?php if($track):?><span class="pill"><?=count($music)?> BRANI</span><div class="small" style="margin-top:7px"><?=htmlspecialchars($track['name'])?></div><audio controls preload="metadata" src="<?=htmlspecialchars($track['url'],ENT_QUOTES)?>">Player audio non supportato.</audio><?php else:?><span class="bad">NESSUN BRANO</span><?php endif;?></div></div></div>
<?php if($error):?><p class="bad"><strong>⚠ <?=htmlspecialchars($error)?></strong></p><?php endif;?>
<?php if($images):?><div class="thumbs"><?php foreach($images as $i=>$d):?><img class="thumb" src="<?=htmlspecialchars($d,ENT_QUOTES)?>" alt="Foto <?=$i+1?>"><?php endforeach;?></div><?php endif;?>
</section>
<section class="card"><h2>Compatibilità browser</h2><div class="grid"><div class="item"><div class="label">Canvas</div><div class="value" id="capture">—</div></div><div class="item"><div class="label">MediaRecorder</div><div class="value" id="recorder">—</div></div><div class="item"><div class="label">MP4/H.264</div><div class="value" id="mp4">—</div></div></div><div class="status" id="compat">Controllo…</div></section>
<section class="card"><h2>Genera e salva Reel</h2><div class="stage"><canvas id="c" width="1080" height="1920"></canvas><div><p>1080×1920, 30 fps, 12 Mbps. Musica al 35%, loop automatico e fade-out finale.</p><button id="go" disabled>▶ Genera Reel MP4</button><div class="status" id="status">Caricamento immagini…</div><div id="result" hidden><video id="video" controls playsinline></video><p class="small" id="info"></p><div class="actions"><a class="btn" id="download" download="formulapaddock_reel.mp4">⬇ Scarica MP4</a><a class="btn" id="drive" target="_blank" hidden>☁️ Apri su Drive</a></div></div></div></div></section>
</div>
<script>
(()=>{
const TITLE=<?=json_encode($article['title'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const EX=<?=json_encode($excerpt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const CATEGORY=<?=json_encode(mb_strtoupper($category),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const IMAGES=<?=json_encode($images,JSON_UNESCAPED_SLASHES)?>;
const MUSIC=<?=json_encode($track['url']??'',JSON_UNESCAPED_SLASHES)?>;
const MUSICNAME=<?=json_encode($track['name']??'',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const CSRF=<?=json_encode($csrf)?>;
const c=document.getElementById('c'),x=c.getContext('2d'),go=document.getElementById('go'),st=document.getElementById('status'),compat=document.getElementById('compat'),result=document.getElementById('result'),video=document.getElementById('video'),info=document.getElementById('info'),download=document.getElementById('download'),drive=document.getElementById('drive');
const cap=typeof c.captureStream==='function',rec=typeof MediaRecorder!=='undefined';document.getElementById('capture').textContent=cap?'SÌ':'NO';document.getElementById('recorder').textContent=rec?'SÌ':'NO';
const types=['video/mp4;codecs=avc1.42E01E,mp4a.40.2','video/mp4;codecs=avc1.4D401F,mp4a.40.2','video/mp4;codecs=avc1.42E01E','video/mp4'];let mime='';if(rec&&MediaRecorder.isTypeSupported)mime=types.find(t=>MediaRecorder.isTypeSupported(t))||'';document.getElementById('mp4').textContent=mime||'NO';const browserOk=cap&&rec&&!!mime;compat.innerHTML=browserOk?'<span class="ok"><strong>✅ Browser pronto.</strong></span>':'<span class="bad"><strong>❌ MP4/H.264 non disponibile.</strong></span>';
const photos=[];
async function preload(){const r=await Promise.all(IMAGES.map(src=>new Promise(ok=>{const i=new Image();i.onload=()=>ok(i.naturalWidth&&i.naturalHeight?i:null);i.onerror=()=>ok(null);i.src=src})));photos.splice(0,photos.length,...r.filter(Boolean));return photos.length}
function rr(a,b,w,h,r,fill,stroke=null,lw=1){x.beginPath();x.roundRect(a,b,w,h,r);x.fillStyle=fill;x.fill();if(stroke){x.strokeStyle=stroke;x.lineWidth=lw;x.stroke()}}
function wrap(t,m,font,n=5){x.font=font;const ws=(t||'').split(/\s+/),ls=[];let cur='';for(const w of ws){const q=cur?cur+' '+w:w;if(x.measureText(q).width>m&&cur){ls.push(cur);cur=w;if(ls.length>=n-1)break}else cur=q}if(cur&&ls.length<n)ls.push(cur);return ls}
function cover(i,scale=1,alpha=1){if(!i||i.naturalWidth<=0||i.naturalHeight<=0)return false;const iw=i.naturalWidth,ih=i.naturalHeight,ir=iw/ih,br=1080/1920;let sw,sh,sx,sy;if(ir>br){sh=ih;sw=sh*br;sx=(iw-sw)/2;sy=0}else{sw=iw;sh=sw/br;sx=0;sy=(ih-sh)/2}const dw=1080*scale,dh=1920*scale;x.save();x.globalAlpha=alpha;x.drawImage(i,sx,sy,sw,sh,-(dw-1080)/2,-(dh-1920)/2,dw,dh);x.restore();return true}
function slide(p){if(!photos.length)return false;const n=photos.length,pos=Math.min(n-.0001,Math.max(0,p)*n),idx=Math.floor(pos),local=pos-idx,next=Math.min(n-1,idx+1),fade=next!==idx?Math.max(0,Math.min(1,(local-.7)/.3)):0;if(!cover(photos[idx],1+local*.055,1))return false;if(fade>0)cover(photos[next],1+Math.max(0,local-.7)*.03,fade);return true}
function frame(p){x.fillStyle='#08090d';x.fillRect(0,0,1080,1920);if(!slide(p)){const g=x.createLinearGradient(0,0,1080,1920);g.addColorStop(0,'#14090c');g.addColorStop(1,'#050507');x.fillStyle=g;x.fillRect(0,0,1080,1920)}const sh=x.createLinearGradient(0,0,0,1920);sh.addColorStop(0,'rgba(0,0,0,.30)');sh.addColorStop(.45,'rgba(0,0,0,.08)');sh.addColorStop(.68,'rgba(0,0,0,.60)');sh.addColorStop(1,'rgba(0,0,0,.96)');x.fillStyle=sh;x.fillRect(0,0,1080,1920);rr(48,52,500,82,17,'rgba(0,0,0,.78)','#e10600',4);x.fillStyle='#fff';x.font='900 36px Arial';x.fillText('FORMULAPADDOCK.IT',78,106);rr(48,1380,984,410,28,'rgba(0,0,0,.82)','rgba(255,255,255,.14)',2);x.fillStyle='#e10600';x.fillRect(48,1380,14,410);x.fillStyle='#ffd100';x.font='900 27px Arial';x.fillText((CATEGORY||'FORMULA 1 • NEWS').slice(0,48),92,1440);let y=1510;const tf='900 54px Arial';x.fillStyle='#fff';x.font=tf;for(const l of wrap(TITLE,860,tf,5)){x.fillText(l,92,y);y+=65}if(y<1740){const ef='500 28px Arial';x.fillStyle='#d5d5dc';x.font=ef;y+=12;for(const l of wrap(EX,860,ef,2)){x.fillText(l,92,y);y+=39}}const a=Math.max(0,(p-.78)/.12);x.save();x.globalAlpha=Math.min(1,a);rr(240,1815,600,62,18,'#e10600');x.fillStyle='#fff';x.textAlign='center';x.font='900 28px Arial';x.fillText('SEGUI FORMULAPADDOCK.IT',540,1856);x.restore();x.textAlign='left'}
async function musicTrack(ms){if(!MUSIC)return null;const AC=window.AudioContext||window.webkitAudioContext;if(!AC)return null;const el=new Audio(MUSIC);el.preload='auto';el.loop=true;const ac=new AC();await ac.resume();const src=ac.createMediaElementSource(el),gain=ac.createGain(),dest=ac.createMediaStreamDestination();src.connect(gain);gain.connect(dest);const now=ac.currentTime,d=ms/1000,f=Math.min(.9,d/4);gain.gain.setValueAtTime(.35,now);gain.gain.setValueAtTime(.35,now+Math.max(0,d-f));gain.gain.linearRampToValueAtTime(0,now+d);el.currentTime=0;await el.play();return{el,ac,dest}}
function stopMusic(m){if(!m)return;try{m.el.pause();m.el.currentTime=0}catch(e){}try{m.ac.close()}catch(e){}}
function randomUploadId(){const b=new Uint8Array(16);crypto.getRandomValues(b);return Array.from(b,v=>v.toString(16).padStart(2,'0')).join('')}
async function uploadBlob(blob){const chunkSize=1024*1024,total=Math.ceil(blob.size/chunkSize),uploadId=randomUploadId();let last=null;for(let i=0;i<total;i++){const fd=new FormData();fd.append('csrf',CSRF);fd.append('upload_id',uploadId);fd.append('index',String(i));fd.append('total',String(total));fd.append('chunk',blob.slice(i*chunkSize,Math.min(blob.size,(i+1)*chunkSize)),'chunk.bin');st.innerHTML=`<span class="warn"><strong>☁️ Salvataggio Reel ${Math.round(((i+1)/total)*100)}%</strong></span>`;const res=await fetch('upload_reel.php',{method:'POST',body:fd,credentials:'same-origin'});const data=await res.json().catch(()=>({}));if(!res.ok||!data.ok)throw new Error(data.error||'Errore salvataggio Reel');last=data;}return last}
frame(0);
preload().then(n=>{frame(0);if(browserOk&&n){go.disabled=false;st.innerHTML=`<span class="ok"><strong>✅ ${n} immagini pronte.</strong></span>${MUSIC?` 🎵 ${MUSICNAME}`:' <span class="warn">Nessuna musica trovata.</span>'}`}else st.innerHTML='<span class="bad">Nessuna immagine valida.</span>'});
let obj='';
go.addEventListener('click',async()=>{if(!browserOk||!photos.length)return;go.disabled=true;result.hidden=true;drive.hidden=true;if(obj){URL.revokeObjectURL(obj);obj=''}const dur=Math.min(18000,Math.max(8000,photos.length*1500+1500)),canvasStream=c.captureStream(30),chunks=[];st.innerHTML=`<span class="warn"><strong>🎬 Rendering…</strong></span> ${(dur/1000).toFixed(1)} s${MUSIC?' · 🎵':''}`;let mt=null,stream=canvasStream;try{mt=await musicTrack(dur);if(mt)stream=new MediaStream([...canvasStream.getVideoTracks(),...mt.dest.stream.getAudioTracks()])}catch(e){console.warn(e);mt=null;stream=canvasStream}let mr;try{mr=new MediaRecorder(stream,{mimeType:mime,videoBitsPerSecond:12000000,audioBitsPerSecond:192000})}catch(e){stopMusic(mt);st.innerHTML='<span class="bad">Errore MediaRecorder: '+e.message+'</span>';go.disabled=false;return}mr.ondataavailable=e=>{if(e.data&&e.data.size)chunks.push(e.data)};const done=new Promise(r=>mr.onstop=r);mr.start(1000);const start=performance.now();await new Promise(resolve=>{function tick(now){const p=Math.min(1,(now-start)/dur);frame(p);st.innerHTML=`<span class="warn"><strong>🎬 ${(p*100).toFixed(0)}%</strong></span> · foto ${Math.min(photos.length,Math.floor(p*photos.length)+1)}/${photos.length}${mt?' · 🎵':''}`;p<1?requestAnimationFrame(tick):resolve()}requestAnimationFrame(tick)});mr.stop();await done;stopMusic(mt);stream.getTracks().forEach(t=>t.stop());canvasStream.getTracks().forEach(t=>t.stop());const blob=new Blob(chunks,{type:mime});if(blob.size<10000){st.innerHTML='<span class="bad">File Reel troppo piccolo.</span>';go.disabled=false;return}obj=URL.createObjectURL(blob);video.src=obj;download.href=obj;result.hidden=false;info.innerHTML=`${photos.length} immagini · ${(dur/1000).toFixed(1)} s · ${(blob.size/1024/1024).toFixed(2)} MB${MUSIC?` · 🎵 ${MUSICNAME}`:''}`;try{const saved=await uploadBlob(blob);if(saved&&saved.complete){st.innerHTML='<span class="ok"><strong>✅ Reel creato e salvato sul server.</strong></span>';if(saved.drive&&saved.drive.view_link){drive.href=saved.drive.view_link;drive.hidden=false}if(saved.drive_warning)st.innerHTML+='<div class="small warn" style="margin-top:6px">Drive: '+saved.drive_warning+'</div>';if(window.parent&&window.parent!==window){window.parent.postMessage({type:'fp-reel-ready',url:saved.url,name:saved.name,drive:saved.drive||null},location.origin)}}}catch(e){st.innerHTML='<span class="warn"><strong>⚠ Reel creato ma non salvato sul server:</strong> '+e.message+'</span> Puoi comunque scaricarlo.'}go.disabled=false});
})();
</script>
</body></html>
