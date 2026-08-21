<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$content = $content ?? ($_SESSION['last_social_content'] ?? []);
$title = $title ?? ($_SESSION['last_social_title'] ?? 'Formula 1 Live');
$sourceUrl = $sourceUrl ?? ($_SESSION['last_social_source_url'] ?? '');
$liveContext = $liveContext ?? ($_SESSION['last_live_social_context'] ?? []);
$liveImages = $liveImages ?? ($_SESSION['last_live_social_images'] ?? []);
$liveDrive = $liveDrive ?? ($_SESSION['last_live_social_drive'] ?? []);
$driveErrors = $driveErrors ?? ($_SESSION['last_social_drive_errors'] ?? []);

$sessionName = trim((string)($liveContext['session_name'] ?? 'Sessione Live'));
$meetingName = trim((string)($liveContext['meeting_name'] ?? ''));
$rows = is_array($liveContext['rows'] ?? null) ? $liveContext['rows'] : [];

function liveImgWebPath(array $liveImages, string $key): string
{
    $path = (string)($liveImages[$key] ?? '');
    return $path !== '' ? 'output/images/' . basename($path) : '';
}

function liveCaptionTop3(array $rows, string $sessionName, string $meetingName, string $sourceUrl): string
{
    $top = array_slice($rows, 0, 3);
    $parts = [];
    foreach ($top as $row) {
        $parts[] = trim((string)($row['position'] ?? '')) . '° ' . trim((string)($row['driver_name'] ?? ''));
    }
    return trim("🏁 {$sessionName}" . ($meetingName ? " — {$meetingName}" : '') . "\n\nTop 3: " . implode(' · ', $parts) . "\n\n{$sourceUrl}\n\n#F1 #Formula1 #FormulaPaddock");
}

function liveCaptionFerrari(array $rows, string $sessionName, string $meetingName, string $sourceUrl): string
{
    $ferrari = array_values(array_filter($rows, static function ($row): bool {
        $team = mb_strtolower((string)($row['team_name'] ?? ''));
        $driver = mb_strtolower((string)($row['driver_name'] ?? ''));
        return mb_strpos($team, 'ferrari') !== false || mb_strpos($driver, 'leclerc') !== false || mb_strpos($driver, 'hamilton') !== false;
    }));
    $parts = [];
    foreach ($ferrari as $row) {
        $parts[] = trim((string)($row['driver_name'] ?? '')) . ' P' . trim((string)($row['position'] ?? '-'));
    }
    $result = $parts ? implode(' · ', $parts) : 'risultati in aggiornamento';
    return trim("🔴 Ferrari — {$sessionName}" . ($meetingName ? " · {$meetingName}" : '') . "\n\n{$result}\n\n{$sourceUrl}\n\n#Ferrari #F1 #Formula1 #FormulaPaddock");
}

function liveCaptionTop10(string $sessionName, string $meetingName, string $sourceUrl): string
{
    return trim("📊 Classifica {$sessionName}" . ($meetingName ? " — {$meetingName}" : '') . "\n\nEcco la Top 10 della sessione.\n\n{$sourceUrl}\n\n#F1 #Formula1 #FormulaPaddock");
}

$cards = [
    'top3' => [
        'title' => 'Top 3',
        'icon' => '🏆',
        'image' => liveImgWebPath($liveImages, 'top3'),
        'caption' => liveCaptionTop3($rows, $sessionName, $meetingName, $sourceUrl),
    ],
    'ferrari' => [
        'title' => 'Risultato Ferrari',
        'icon' => '🔴',
        'image' => liveImgWebPath($liveImages, 'ferrari'),
        'caption' => liveCaptionFerrari($rows, $sessionName, $meetingName, $sourceUrl),
    ],
    'top10' => [
        'title' => 'Classifica Top 10',
        'icon' => '📊',
        'image' => liveImgWebPath($liveImages, 'top10'),
        'caption' => liveCaptionTop10($sessionName, $meetingName, $sourceUrl),
    ],
];
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Formula Paddock — Social Live</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#09090d;color:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;padding:26px}.wrap{max-width:1380px;margin:auto}.head{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:24px}.head h1{margin:0;font-size:27px}.accent{color:#ffd100}.pill{display:inline-block;background:#e10600;color:#fff;font-weight:800;font-size:12px;padding:7px 11px;border-radius:99px;margin-top:8px}.muted{color:#aaa}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{border:1px solid #444;background:#1b1b22;color:#fff;border-radius:8px;padding:9px 14px;text-decoration:none;cursor:pointer;font-weight:700}.btn:hover{border-color:#ffd100}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.card{background:#141419;border:1px solid #2d2d34;border-radius:16px;padding:15px;box-shadow:0 12px 32px rgba(0,0,0,.28)}.card h2{margin:0 0 12px;font-size:18px;color:#ffd100}.img{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:12px;border:1px solid #333;background:#000}.caption{width:100%;min-height:150px;margin-top:12px;background:#09090d;color:#fff;border:1px solid #383840;border-radius:9px;padding:11px;font:inherit;line-height:1.45;resize:vertical}.row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.row .btn{flex:1;text-align:center}.fb{background:#1877f2;border-color:#1877f2}.copy{background:#262630}.status{font-size:13px;min-height:22px;margin-top:8px}.ok{color:#4ade80}.err{color:#f87171}.warn{padding:10px 13px;background:#301616;border:1px solid #7f3030;border-radius:8px;margin-bottom:12px;color:#fca5a5}@media(max-width:1000px){.grid{grid-template-columns:1fr}.card{max-width:720px;margin:auto;width:100%}}
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <div>
            <h1>⚡ Social <span class="accent">Sessione Live</span></h1>
            <div class="pill"><?= htmlspecialchars($sessionName) ?><?= $meetingName ? ' · ' . htmlspecialchars($meetingName) : '' ?></div>
            <div class="muted" style="margin-top:7px;">Tre infografiche generate automaticamente dai risultati della sessione.</div>
        </div>
        <div class="actions">
            <a class="btn" href="output.php">Pannello social completo</a>
            <a class="btn" href="index.php">Nuova notizia</a>
        </div>
    </div>

    <?php foreach ($driveErrors as $err): ?>
        <div class="warn">⚠️ <?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <div class="grid">
        <?php foreach ($cards as $key => $card): ?>
        <div class="card">
            <h2><?= $card['icon'] ?> <?= htmlspecialchars($card['title']) ?></h2>
            <?php if ($card['image']): ?>
                <img class="img" src="<?= htmlspecialchars($card['image']) ?>" alt="<?= htmlspecialchars($card['title']) ?>">
            <?php else: ?>
                <div class="img" style="display:grid;place-items:center;color:#888;">Immagine non disponibile</div>
            <?php endif; ?>
            <textarea class="caption" id="caption-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($card['caption']) ?></textarea>
            <div class="row">
                <?php if ($card['image']): ?>
                <a class="btn" href="<?= htmlspecialchars($card['image']) ?>" download>⬇️ Scarica JPG</a>
                <?php endif; ?>
                <?php if (!empty($liveDrive[$key]['view_link'])): ?>
                <a class="btn" href="<?= htmlspecialchars($liveDrive[$key]['view_link']) ?>" target="_blank" rel="noopener">☁️ Drive</a>
                <?php endif; ?>
            </div>
            <div class="row">
                <button class="btn copy" type="button" onclick="copyCaption('<?= htmlspecialchars($key) ?>',this)">📋 Copia testo</button>
                <button class="btn fb" type="button" onclick="publishFacebook('<?= htmlspecialchars($key) ?>',this)">🚀 Pubblica Facebook</button>
            </div>
            <div class="status" id="status-<?= htmlspecialchars($key) ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
const articleSourceUrl = <?= json_encode($sourceUrl) ?>;
function copyCaption(key, btn){const t=document.getElementById('caption-'+key);if(!t)return;navigator.clipboard.writeText(t.value).then(()=>{const x=btn.textContent;btn.textContent='✅ Copiato';setTimeout(()=>btn.textContent=x,1500);});}
async function publishFacebook(key,btn){const t=document.getElementById('caption-'+key);const s=document.getElementById('status-'+key);const text=t?t.value.trim():'';if(!text)return;btn.disabled=true;s.className='status';s.textContent='⏳ Pubblicazione in corso...';try{const fd=new FormData();fd.append('channel','facebook');fd.append('text',text);fd.append('link',articleSourceUrl||'');fd.append('image_key',key);const r=await fetch('publish_ajax.php',{method:'POST',body:fd});const d=await r.json().catch(()=>({}));if(!r.ok||!d.ok)throw new Error(d.error||'Pubblicazione non riuscita');s.className='status ok';s.textContent='✅ '+(d.message||'Pubblicato con successo');}catch(e){s.className='status err';s.textContent='⚠️ '+e.message;}finally{btn.disabled=false;}}
</script>
</body>
</html>
