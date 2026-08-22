<?php
/**
 * output.php — Formula Paddock Social Visual Studio HD & Post Control Center
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/config.php';
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
$reelPath = (string)($_SESSION['last_social_reel'] ?? '');
$reelUrl = (string)($_SESSION['last_social_reel_url'] ?? '');
$reelDrive = $_SESSION['last_social_reel_drive'] ?? null;
$reelReady = $reelPath !== '' && is_file($reelPath);

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

$reelBuilderUrl = 'reel_builder.php?embed=1';
if ($sourceUrl !== '') {
    $reelBuilderUrl .= '&url=' . rawurlencode($sourceUrl);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Formula Paddock Visual Studio HD — Output & Social Hub</title>
<style>
*{box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:linear-gradient(135deg,#09090e 0%,#1c080b 50%,#0d0d12 100%);color:#fff;margin:0;padding:30px 20px;min-height:100vh}.container{max-width:1100px;margin:0 auto}.header-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}h1{font-size:24px;margin:0}.accent{color:#ffd100}.btn-top-back{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:#ffd100;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600}.section{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:22px;margin-bottom:22px;backdrop-filter:blur(10px)}.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px}.section h2{margin:0;font-size:17px;color:#ffd100}.status-box{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px}.ok{background:rgba(40,160,80,.18);border:1px solid #2a8;color:#4ade80}.warn{background:rgba(180,40,40,.2);border:1px solid #b33;color:#f87171}.post-textarea{width:100%;min-height:110px;background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.15);border-radius:8px;color:#fff;padding:12px 14px;font-size:14px;line-height:1.5;font-family:inherit;resize:vertical}.action-bar{display:flex;align-items:center;justify-content:space-between;margin-top:10px;flex-wrap:wrap;gap:10px}.action-buttons{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.btn-copy{background:#2a2a38;color:#ddd;border:1px solid #444;padding:8px 14px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}.btn-pub{padding:9px 18px;border-radius:6px;border:none;color:#fff;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(0,0,0,.3)}.btn-pub:disabled{opacity:.4;cursor:not-allowed}.btn-facebook{background:linear-gradient(135deg,#1877f2,#0d5cb6)}.btn-twitter{background:linear-gradient(135deg,#000,#222);border:1px solid #444}.btn-threads{background:linear-gradient(135deg,#242526,#000);border:1px solid #666}.btn-linkedin{background:linear-gradient(135deg,#0a66c2,#004182)}.btn-tiktok{background:linear-gradient(135deg,#fe2c55,#25f4ee);color:#000}.publish-status-msg{font-size:13px;font-weight:600}.hd-preview-container{display:grid;grid-template-columns:360px 1fr;gap:24px;align-items:start}@media(max-width:850px){.hd-preview-container{grid-template-columns:1fr}}.hd-img-wrapper{background:#000;border:2px solid #ffd100;border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.5)}.hd-img-wrapper img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block}.badge-hd{display:inline-block;background:#ffd100;color:#111;font-weight:800;font-size:12px;padding:4px 10px;border-radius:4px;margin-bottom:12px}.reel-banner{background:linear-gradient(90deg,#16161e,#28090d);border:2px solid #e10600;border-radius:14px;padding:20px;margin-bottom:18px}.reel-banner strong{color:#ffd100}.reel-frame{width:100%;height:1080px;border:2px solid rgba(225,6,0,.6);border-radius:12px;overflow:hidden;background:#09090d;margin-top:12px}.reel-frame iframe{width:100%;height:100%;border:0}@media(max-width:780px){.reel-frame{height:1320px}}.reel-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.reel-ready{padding:12px;border-radius:8px;background:rgba(40,160,80,.14);border:1px solid #2a8;color:#4ade80}.reel-wait{padding:12px;border-radius:8px;background:rgba(255,209,0,.08);border:1px solid rgba(255,209,0,.5);color:#ffd100}
</style>
</head>
<body><div class="container">
<div class="header-bar"><h1>🏎️ <span class="accent">Formula Paddock</span> Visual Studio HD</h1><a class="btn-top-back" href="index.php">&larr; Inserisci nuova notizia</a></div>

<?php if ($sheetError): ?><div class="status-box warn">⚠️ Google Sheet: <?=htmlspecialchars($sheetError)?></div><?php else: ?><div class="status-box ok">✅ Riga e dati sincronizzati su Google Sheet.</div><?php endif; ?>
<?php foreach($bufferResults as $br):?><div class="status-box ok">🚀 Auto-Buffer: <?=htmlspecialchars($br)?></div><?php endforeach;?>
<?php foreach($bufferErrors as $be):?><div class="status-box warn">⚠️ Auto-Buffer: <?=htmlspecialchars($be)?></div><?php endforeach;?>
<?php foreach($driveErrors as $de):?><div class="status-box warn">⚠️ Google Drive: <?=htmlspecialchars($de)?></div><?php endforeach;?>

<div class="section"><div class="section-header"><h2>🎨 Formula Paddock Visual Studio HD (1080x1080)</h2><span class="badge-hd">FACEBOOK & INSTAGRAM</span></div><div class="hd-preview-container"><div class="hd-img-wrapper"><img src="<?=htmlspecialchars($hdImageFile)?>" alt="Visual Studio HD"></div><div><h3><?=htmlspecialchars($title)?></h3><p style="color:#aaa;font-size:13px;line-height:1.6">Formato grafico unificato 1080x1080.</p><div class="reel-actions"><a class="btn-top-back" href="<?=htmlspecialchars($hdImageFile)?>" download>⬇️ Scarica HD</a><?php if($hdDriveLink):?><a class="btn-top-back" href="<?=htmlspecialchars($hdDriveLink)?>" target="_blank">☁️ Drive</a><?php endif;?></div></div></div></div>

<div class="section"><div class="section-header"><h2>📘 Formato Facebook</h2><div class="publish-status-msg" id="status-facebook"></div></div><textarea id="text-facebook" class="post-textarea"><?=htmlspecialchars($content['facebook']??'')?></textarea><div class="action-bar"><button class="btn-copy" onclick="copyToClipboard('text-facebook',this)">📋 Copia testo</button><button class="btn-pub btn-facebook" onclick="publishSocial('facebook','text-facebook')">🚀 Pubblica Facebook + immagine</button></div></div>

<div class="section"><div class="section-header"><h2>🐦 Formato Twitter / X</h2><div class="publish-status-msg" id="status-twitter"></div></div><textarea id="text-twitter" class="post-textarea"><?=htmlspecialchars($content['twitter']??'')?></textarea><div class="action-bar"><button class="btn-copy" onclick="copyToClipboard('text-twitter',this)">📋 Copia testo</button><button class="btn-pub btn-twitter" onclick="publishSocial('twitter','text-twitter')">🚀 Pubblica X (Buffer)</button></div></div>

<div class="section"><div class="section-header"><h2>💬 Formato Threads</h2><div class="publish-status-msg" id="status-threads"></div></div><textarea id="text-threads" class="post-textarea"><?=htmlspecialchars($content['twitter_modificato']??($content['twitter']??''))?></textarea><div class="action-bar"><button class="btn-copy" onclick="copyToClipboard('text-threads',this)">📋 Copia testo</button><button class="btn-pub btn-threads" onclick="publishSocial('threads','text-threads')">🚀 Pubblica Threads</button></div></div>

<div class="section"><div class="section-header"><h2>💼 Formato LinkedIn</h2><div class="publish-status-msg" id="status-linkedin"></div></div><textarea id="text-linkedin" class="post-textarea"><?=htmlspecialchars($content['linkedin']??'')?></textarea><div class="action-bar"><button class="btn-copy" onclick="copyToClipboard('text-linkedin',this)">📋 Copia testo</button><button class="btn-pub btn-linkedin" onclick="publishSocial('linkedin','text-linkedin')">🚀 Pubblica LinkedIn</button></div></div>

<div class="section">
<div class="section-header"><h2>🎵 TikTok & Facebook Reels (9:16)</h2><div><span class="publish-status-msg" id="status-tiktok"></span> <span class="publish-status-msg" id="status-facebook_reel"></span></div></div>
<textarea id="text-tiktok" class="post-textarea"><?=htmlspecialchars(($content['infografica_titolo']??$title).' #f1 #formula1 #formulapaddock')?></textarea>
<div class="action-bar"><button class="btn-copy" onclick="copyToClipboard('text-tiktok',this)">📋 Copia didascalia</button><div class="action-buttons"><button id="btn-facebook-reel" class="btn-pub btn-facebook" onclick="publishSocial('facebook_reel','text-tiktok')" <?=$reelReady?'':'disabled'?>>📘 Pubblica Facebook Reel</button><button id="btn-tiktok" class="btn-pub btn-tiktok" onclick="publishSocial('tiktok','text-tiktok')" <?=$reelReady?'':'disabled'?>>🎵 Invia a TikTok</button></div></div>
</div>

<div class="reel-banner"><h2 style="margin:0 0 8px">🎬 Reel Builder FormulaPaddock</h2><p style="margin:0;color:#ccc">Il Reel viene creato <strong>nel tuo browser</strong> con tutte le immagini dell'articolo e la musica di <code>social/music</code>, poi viene salvato automaticamente in <code>output/reels</code>. Nessun OnRender.</p></div>

<div class="section"><div class="section-header"><h2>🎬 Generatore Reel integrato</h2><a class="btn-top-back" href="<?=htmlspecialchars(str_replace('embed=1','embed=0',$reelBuilderUrl))?>" target="_blank">Apri a schermo intero ↗</a></div>
<div id="reel-state" class="<?=$reelReady?'reel-ready':'reel-wait'?>"><?=$reelReady?'✅ Reel della notizia corrente già disponibile.':'⏳ Genera il Reel qui sotto. I pulsanti di pubblicazione si abiliteranno quando il file sarà salvato.'?></div>
<div class="reel-actions" id="saved-reel-links" <?=$reelReady?'':'hidden'?>><?php if($reelReady&&$reelUrl):?><a class="btn-top-back" href="<?=htmlspecialchars($reelUrl)?>" target="_blank">▶️ Apri MP4 salvato</a><?php endif;?><?php if($reelReady&&!empty($reelDrive['view_link'])):?><a class="btn-top-back" href="<?=htmlspecialchars($reelDrive['view_link'])?>" target="_blank">☁️ Reel su Drive</a><?php endif;?></div>
<div class="reel-frame"><iframe src="<?=htmlspecialchars($reelBuilderUrl)?>" title="Reel Builder FormulaPaddock" allow="autoplay"></iframe></div></div>

<?php if($sourceUrl):?><div class="section"><h2>🔗 Articolo di origine</h2><pre style="white-space:pre-wrap;background:rgba(0,0,0,.3);padding:10px;border-radius:6px;font-size:13px"><?=htmlspecialchars($sourceUrl)?></pre></div><?php endif;?>
<p style="text-align:center;margin-top:30px"><a class="btn-top-back" href="index.php">&larr; Torna alla schermata di inserimento</a></p>
</div>
<script>
const articleSourceUrl=<?=json_encode($sourceUrl,JSON_UNESCAPED_SLASHES)?>;
let reelReady=<?=json_encode($reelReady)?>;
function copyToClipboard(id,btn){const el=document.getElementById(id);if(!el)return;navigator.clipboard.writeText(el.value).then(()=>{const old=btn.innerText;btn.innerText='✅ Copiato!';setTimeout(()=>btn.innerText=old,1600)})}
function setReelReady(data){reelReady=true;document.getElementById('btn-tiktok').disabled=false;document.getElementById('btn-facebook-reel').disabled=false;const state=document.getElementById('reel-state');state.className='reel-ready';state.textContent='✅ Reel creato e salvato. Pronto per la pubblicazione.';const links=document.getElementById('saved-reel-links');links.hidden=false;links.innerHTML='';if(data&&data.url){const a=document.createElement('a');a.className='btn-top-back';a.target='_blank';a.href=data.url;a.textContent='▶️ Apri MP4 salvato';links.appendChild(a)}if(data&&data.drive&&data.drive.view_link){const a=document.createElement('a');a.className='btn-top-back';a.target='_blank';a.href=data.drive.view_link;a.textContent='☁️ Reel su Drive';links.appendChild(a)}}
window.addEventListener('message',e=>{if(e.origin!==location.origin||!e.data||e.data.type!=='fp-reel-ready')return;setReelReady(e.data)});
function publishSocial(channel,textElementId){const textarea=document.getElementById(textElementId),statusEl=document.getElementById('status-'+channel),textVal=textarea?textarea.value.trim():'';if((channel==='tiktok'||channel==='facebook_reel')&&!reelReady){alert('Genera e salva prima il Reel della notizia corrente.');return}if(!textVal&&channel!=='tiktok'){alert('Il testo non può essere vuoto.');return}if(statusEl)statusEl.innerHTML='<span style="color:#ffd100">⏳ Pubblicazione...</span>';const fd=new FormData();fd.append('channel',channel);fd.append('text',textVal);fd.append('link',articleSourceUrl||'');fetch('publish_ajax.php',{method:'POST',body:fd}).then(async r=>{const d=await r.json().catch(()=>({}));if(!r.ok||!d.ok)throw new Error(d.error||'Errore pubblicazione ('+r.status+')');return d}).then(d=>{if(statusEl)statusEl.innerHTML='<span style="color:#4ade80">✅ '+(d.message||'Pubblicato')+'</span>'}).catch(err=>{if(statusEl)statusEl.innerHTML='<span style="color:#f87171">⚠️ '+err.message+'</span>'})}
</script>
</body></html>
