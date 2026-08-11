<?php

require_once __DIR__ . '/function.php';

function fmtMs(float $seconds): string
{
    return number_format($seconds * 1000, 1, ',', '.') . ' ms';
}

function fmtPercent(float $value): string
{
    return number_format($value * 100, 1, ',', '.') . '%';
}

$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'cache';
$initError = null;
if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
    $initError = 'Impossibile creare la cartella cache locale.';
}

$sources = getSources();
$metrics = newPhotoMetrics();
$items = $initError === null ? buildPhotoItems($sources, $cacheDir, $metrics) : [];

$total = max(0.000001, (float)($metrics['total_seconds'] ?? 0.0));
$network = (float)($metrics['network_seconds'] ?? 0.0);
$parse = (float)($metrics['parse_seconds'] ?? 0.0);
$io = (float)($metrics['io_seconds'] ?? 0.0);

?><!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>F1 Photo Wall</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
    <h1>F1 Photo Wall</h1>
    <p class="sub">Sorgenti definite nel codice (cache locale senza riscaricare file gi� presenti)</p>

    <div class="source-box">
        <div class="label">URL sorgente:</div>
        <pre><?= htmlspecialchars(implode("\n", $sources), ENT_QUOTES, 'UTF-8') ?></pre>
    </div>

    <div class="perf-box">
        <div class="label">Diagnostica prestazioni</div>
        <div class="perf-grid">
            <div><strong>Tempo totale:</strong> <?= fmtMs($total) ?></div>
            <div><strong>Rete:</strong> <?= fmtMs($network) ?> (<?= fmtPercent($network / $total) ?>)</div>
            <div><strong>Parsing DOM:</strong> <?= fmtMs($parse) ?> (<?= fmtPercent($parse / $total) ?>)</div>
            <div><strong>I/O locale:</strong> <?= fmtMs($io) ?> (<?= fmtPercent($io / $total) ?>)</div>
            <div><strong>Sorgenti OK:</strong> <?= (int)$metrics['sources_ok'] ?>/<?= (int)$metrics['sources_total'] ?></div>
            <div><strong>HTML fail:</strong> <?= (int)$metrics['html_failures'] ?></div>
            <div><strong>URL immagine uniche:</strong> <?= (int)$metrics['image_urls_unique'] ?></div>
            <div><strong>Download tentati:</strong> <?= (int)$metrics['image_download_attempts'] ?></div>
            <div><strong>Download riusciti:</strong> <?= (int)$metrics['image_download_success'] ?></div>
            <div><strong>Cache hit:</strong> <?= (int)$metrics['cache_hits'] ?></div>
            <div><strong>Byte scaricati:</strong> <?= number_format((int)$metrics['downloaded_bytes'], 0, ',', '.') ?></div>
            <div><strong>Foto finali:</strong> <?= (int)$metrics['items_built'] ?></div>
        </div>
    </div>

    <?php if ($initError !== null): ?>
        <div class="empty"><?= htmlspecialchars($initError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($items === []): ?>
        <div class="empty">Nessuna foto trovata nelle sorgenti configurate.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($items as $item): ?>
                <article class="card">
                    <a href="<?= htmlspecialchars($item['source'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <img loading="lazy" src="<?= htmlspecialchars($item['local'], ENT_QUOTES, 'UTF-8') ?>" alt="F1 photo">
                    </a>
                    <div class="actions">
                        <a href="<?= htmlspecialchars($item['source'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Sorgente</a>
                        <a href="<?= htmlspecialchars($item['local'], ENT_QUOTES, 'UTF-8') ?>" download>Scarica</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
