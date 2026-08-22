<?php
require __DIR__ . '/auth.php';
checkAuth();

$siteConfig = require __DIR__ . '/config.php';
$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$appVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();
?>
<!doctype html>
<html lang="it">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>F1 Content Hub</title>
	<link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.css?v=<?= urlencode((string)$styleVersion) ?>">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<header>
	<div class="header-inner">
		<h1>⚡ F1 Content Hub</h1>
		<div class="header-actions">
			<a href="<?= BASE_PATH ?>commento.php" class="btn-settings" title="Commento" style="text-decoration:none">💬</a>
			<a href="<?= BASE_PATH ?>social/" class="btn-settings" title="Social" style="text-decoration:none">📣</a>
			<button type="button" id="open-settings" class="btn-settings" title="Impostazioni">⚙️</button>
			<a href="<?= BASE_PATH ?>logout.php" class="btn-settings" title="Esci" style="text-decoration:none">🚪</a>
		</div>
	</div>
</header>

<nav class="tabs">
	<button data-tab="mod-a" class="active">✍️ Generazione Testo</button>
	<button data-tab="mod-b">🖼️ Immagini</button>
	<button data-tab="mod-f">🏎️ Dati F1</button>
	<button data-tab="mod-wiki">📖 Wikipedia</button>
	<button data-tab="mod-postgara">🏁 Post Gara</button>
	<button data-tab="mod-chart">📊 Grafici</button>
	<button data-tab="mod-g">📤 Pubblica</button>
</nav>

<main class="workspace-grid">
	<div class="modules-column">
		<?php include __DIR__ . '/modules/contenuto/view.php'; ?>
		<?php include __DIR__ . '/modules/pirelli/view.php'; ?>
		<?php include __DIR__ . '/modules/wikipedia/view.php'; ?>
		<?php include __DIR__ . '/modules/grafici/view.php'; ?>
		<?php include __DIR__ . '/modules/revisione/view.php'; ?>
		<?php include __DIR__ . '/modules/immagini/view.php'; ?>
		<?php include __DIR__ . '/modules/postgara/view.php'; ?>
	</div>

	<aside class="preview-column">
		<div class="preview-card">
			<h3>Anteprima in tempo reale</h3>
			<label>Titolo</label>
			<input type="text" id="preview-title" readonly>
			<label>Anteprima HTML formattata</label>
			<div id="preview-html-render" class="preview-html-render"></div>
		</div>
	</aside>
</main>

<?php include __DIR__ . '/modules/settings/view.php'; ?>

<script src="<?= BASE_PATH ?>modules/contenuto/module.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>modules/immagini/module.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>modules/pirelli/module.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>modules/wikipedia/module.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>modules/postgara/module.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>modules/postgara/final-standings.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>modules/revisione/module.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>assets/js/app.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>modules/infografica/module.js?v=<?= urlencode((string)$appVersion) ?>"></script>
<script src="<?= BASE_PATH ?>modules/grafici/module.js?v=<?= urlencode((string)$appVersion) ?>"></script>
</body>
</html>
