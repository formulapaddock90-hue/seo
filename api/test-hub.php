<?php
/**
 * api/test-hub.php
 * Script di debug per testare ScrapingBee e gli hub team.
 * Accesso: /api/test-hub.php?team=williams&action=fetch
 */

require __DIR__ . '/bootstrap.php';

// Leggi la chiave ScrapingBee
$settingsFile = __DIR__ . '/../storage/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$sbKey = trim($settings['scrapingbee_key'] ?? '');

$team   = $_GET['team']   ?? 'williams';
$action = $_GET['action'] ?? 'credits';

// ── Helper cURL ───────────────────────────────────────────────────────────────
function doGet(string $url, array $opts = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    foreach ($opts as $k => $v) curl_setopt($ch, $k, $v);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'code' => $code, 'error' => $err];
}

// ── Credenziali team ──────────────────────────────────────────────────────────
$CREDS = [
    'williams'     => ['url' => 'https://williamsracing.photos',         'user' => 'williamsmedia',        'pass' => 'Williams25Media'],
    'haas'         => ['url' => 'https://media.haasf1team.com/',          'user' => 'HaasF1Media',           'pass' => 'Oxnard'],
    'mercedes'     => ['url' => 'https://mercedes-benz-archive.com/marsF1/', 'user' => 'info@formulapaddock.it', 'pass' => 'Gattipc231090'],
    'red_bull'     => ['url' => 'https://www.redbullcontentpool.com',    'user' => 'Info@formulapaddock.it','pass' => 'Gattipc231090'],
    'aston_martin' => ['url' => 'https://astonmartinf1.canto.global',   'user' => 'info@formulapaddock.it','pass' => 'Gattipc231090!'],
    'alpine'       => ['url' => 'https://media.alpinecars.com/section/mediatheque/?lang=eng&per_page=24&type=assets&page=1&other=373', 'user' => '', 'pass' => ''],
    'sauber'       => ['url' => 'https://mediahub.sauber-group.com',    'user' => 'info@formulapaddock.it','pass' => 'Gattipc231090'],
    'pirelli'      => ['url' => 'https://f1pressarea.pirelli.com',       'user' => 'info@formulapaddock.it','pass' => 'Gattipc231090'],
    'mclaren'      => ['url' => 'https://brandfolder.com',               'user' => 'info@formulapaddock.it','pass' => 'Gattipc231090'],
];

header('Content-Type: application/json; charset=utf-8');

// ── ACTION: credits ── Controlla crediti ScrapingBee ─────────────────────────
if ($action === 'credits') {
    if (!$sbKey) { echo json_encode(['error' => 'Nessuna API key trovata']); exit; }
    $res = doGet('https://app.scrapingbee.com/api/v1/usage?api_key=' . urlencode($sbKey));
    $data = json_decode($res['body'], true);
    echo json_encode([
        'key_present'    => true,
        'key_prefix'     => substr($sbKey, 0, 8) . '...',
        'http_code'      => $res['code'],
        'credits_used'   => ($data['max_api_credits'] ?? 0) - ($data['remaining_api_credits'] ?? 0),
        'credits_left'   => $data['remaining_api_credits'] ?? '?',
        'credits_total'  => $data['max_api_credits'] ?? '?',
        'raw'            => $data,
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── ACTION: direct ── Fetch diretto senza ScrapingBee ────────────────────────
if ($action === 'direct') {
    $cred = $CREDS[$team] ?? null;
    if (!$cred) { echo json_encode(['error' => 'Team non trovato']); exit; }

    $opts = [];
    if ($cred['user']) {
        $opts[CURLOPT_USERPWD]  = $cred['user'] . ':' . $cred['pass'];
        $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
    }
    $res = doGet($cred['url'], $opts);

    // Count images
    preg_match_all('/<img[^>]+src=["\']([^"\']+\.(jpe?g|png|webp))["\'][^>]*>/i', $res['body'], $m);
    preg_match_all('/data-src=["\']([^"\']+\.(jpe?g|png|webp))["\']/i', $res['body'], $m2);
    $imgs = array_unique(array_merge($m[1], $m2[1]));

    echo json_encode([
        'team'         => $team,
        'url'          => $cred['url'],
        'http_code'    => $res['code'],
        'body_length'  => strlen($res['body']),
        'body_preview' => substr(strip_tags($res['body']), 0, 500),
        'images_found' => count($imgs),
        'image_urls'   => array_slice($imgs, 0, 5),
        'curl_error'   => $res['error'],
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── ACTION: scrapingbee ── Fetch tramite ScrapingBee ─────────────────────────
if ($action === 'scrapingbee') {
    if (!$sbKey) { echo json_encode(['error' => 'Nessuna API key trovata']); exit; }
    $cred = $CREDS[$team] ?? null;
    if (!$cred) { echo json_encode(['error' => 'Team non trovato']); exit; }

    $targetUrl = $cred['url'];

    $params = [
        'api_key'         => $sbKey,
        'url'             => $targetUrl,
        'render_js'       => 'true',
        'wait'            => '3000',
        'block_ads'       => 'true',
        'forward_headers' => 'true',
    ];

    $sbUrl = 'https://app.scrapingbee.com/api/v1/?' . http_build_query($params);

    // Aggiungi Basic auth se necessario
    $curlOpts = [];
    if ($cred['user']) {
        $curlOpts[CURLOPT_HTTPHEADER] = [
            'Authorization: Basic ' . base64_encode($cred['user'] . ':' . $cred['pass']),
        ];
    }

    $res = doGet($sbUrl, $curlOpts);

    // Count images
    preg_match_all('/<img[^>]+src=["\']([^"\']+\.(jpe?g|png|webp))["\'][^>]*>/i', $res['body'], $m);
    preg_match_all('/data-src=["\']([^"\']+\.(jpe?g|png|webp))["\']/i', $res['body'], $m2);
    $imgs = array_unique(array_merge($m[1], $m2[1]));

    // ScrapingBee cost header
    $cost = '';

    echo json_encode([
        'team'         => $team,
        'url'          => $cred['url'],
        'http_code'    => $res['code'],
        'body_length'  => strlen($res['body']),
        'body_preview' => substr(strip_tags(html_entity_decode($res['body'])), 0, 800),
        'images_found' => count($imgs),
        'image_urls'   => array_slice($imgs, 0, 10),
        'curl_error'   => $res['error'],
        'sb_url_used'  => $sbUrl,
    ], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode(['error' => 'Action non riconosciuta. Usa: credits, direct, scrapingbee']);
