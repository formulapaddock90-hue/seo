<?php
/**
 * team-hub-sync.php v2
 * Scarica immagini dagli hub fotografici ufficiali dei team F1.
 * Approccio mirato per ogni piattaforma.
 */

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['message' => 'Metodo non supportato'], 405);
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$teams      = $input['teams'] ?? [];
$limit      = (int)($input['limit'] ?? 30);
$sbKey      = trim($input['scrapingbee_key'] ?? '');

// Fallback: leggi dal file settings server-side
if ($sbKey === '') {
    $settingsFile = __DIR__ . '/../storage/settings.json';
    if (file_exists($settingsFile)) {
        $s = json_decode(file_get_contents($settingsFile), true) ?? [];
        $sbKey = trim($s['scrapingbee_key'] ?? '');
    }
}

if (empty($teams)) {
    jsonResponse(['message' => 'Nessun team selezionato'], 400);
}

// ─── Credenziali hub team ─────────────────────────────────────────────────────
$HUB_CREDENTIALS = [
    'williams' => [
        'label'    => 'Williams',
        'platform' => 'basic_auth_gallery',
        'url'      => 'https://williamsracing.photos',
        'username' => 'williamsmedia',
        'password' => 'Williams25Media',
    ],
    'haas' => [
        'label'    => 'Haas',
        'platform' => 'basic_auth_gallery',
        'url'      => 'https://media.haasf1team.com/',
        'username' => 'HaasF1Media',
        'password' => 'Oxnard',
    ],
    'mercedes' => [
        'label'    => 'Mercedes',
        'platform' => 'basic_auth_gallery',
        'url'      => 'https://mercedes-benz-archive.com/marsF1/',
        'username' => 'info@formulapaddock.it',
        'password' => 'Gattipc231090',
    ],
    'red_bull' => [
        'label'    => 'Red Bull',
        'platform' => 'redbull_pool',
        'url'      => 'https://www.redbullcontentpool.com',
        'username' => 'Info@formulapaddock.it',
        'password' => 'Gattipc231090',
    ],
    'aston_martin' => [
        'label'    => 'Aston Martin',
        'platform' => 'canto',
        'tenant'   => 'astonmartinf1',
        'url'      => 'https://astonmartinf1.canto.global',
        'username' => 'info@formulapaddock.it',
        'password' => 'Gattipc231090!',
    ],
    'alpine' => [
        'label'    => 'Alpine',
        'platform' => 'alpine_media',
        'url'      => 'https://media.alpinecars.com',
        'username' => '',
        'password' => '',
    ],
    'visa_rb' => [
        'label'    => 'Visa Cash App RB',
        'platform' => 'redbull_pool',
        'url'      => 'https://www.redbullcontentpool.com',
        'username' => 'Info@formulapaddock.it',
        'password' => 'Gattipc231090',
        'filter'   => 'racing bulls',
    ],
    'sauber' => [
        'label'    => 'Sauber',
        'platform' => 'sauber_mediahub',
        'url'      => 'https://mediahub.sauber-group.com',
        'username' => 'info@formulapaddock.it',
        'password' => 'Gattipc231090',
    ],
    'pirelli' => [
        'label'    => 'Pirelli',
        'platform' => 'pirelli_pressarea',
        'url'      => 'https://f1pressarea.pirelli.com',
        'username' => 'info@formulapaddock.it',
        'password' => 'Gattipc231090',
    ],
    'mclaren' => [
        'label'    => 'McLaren',
        'platform' => 'brandfolder',
        'url'      => 'https://brandfolder.com',
        'username' => 'info@formulapaddock.it',
        'password' => 'Gattipc231090',
    ],
];

// ─── Cartella di destinazione ─────────────────────────────────────────────────
$baseDir = __DIR__ . '/../uploads/team-hubs';
if (!is_dir($baseDir) && !@mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
    jsonResponse(['message' => 'Impossibile creare cartella uploads/team-hubs'], 500);
}

// ─── Helper cURL ──────────────────────────────────────────────────────────────
function curlGet(string $url, array $curlOpts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120',
    ]);
    foreach ($curlOpts as $k => $v) curl_setopt($ch, $k, $v);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body ?: '', 'code' => $info['http_code'], 'error' => $err, 'info' => $info];
}

function curlPost(string $url, $data, array $headers = [], array $curlOpts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120',
    ]);
    foreach ($curlOpts as $k => $v) curl_setopt($ch, $k, $v);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['body' => $body ?: '', 'code' => $info['http_code']];
}

// ─── ScrapingBee helper ─────────────────────────────────────────────────────
// Fetches a URL through ScrapingBee (renders JS) when API key is provided,
// otherwise falls back to plain cURL.
function scrapingBeeFetch(string $targetUrl, string $apiKey = '', string $basicUser = '', string $basicPass = ''): array
{
    if ($apiKey !== '') {
        $sbUrl = 'https://app.scrapingbee.com/api/v1/?' . http_build_query([
            'api_key'         => $apiKey,
            'url'             => $targetUrl,
            'render_js'       => 'true',
            'wait'            => '3000',
            'block_ads'       => 'true',
            'forward_headers' => 'true',   // ← corretto (non custom_headers)
        ]);

        $curlOpts = [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 45];
        if ($basicUser !== '') {
            // ScrapingBee forwarderà questo header al sito target
            $curlOpts[CURLOPT_HTTPHEADER] = [
                'Authorization: Basic ' . base64_encode($basicUser . ':' . $basicPass),
            ];
        }
        return curlGet($sbUrl, $curlOpts);
    }
    // Fallback diretto
    $opts = [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 30];
    if ($basicUser !== '') {
        $opts[CURLOPT_USERPWD]  = $basicUser . ':' . $basicPass;
        $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
    }
    return curlGet($targetUrl, $opts);
}

function extractImagesFromHtml(string $html, string $base): array
{
    $out = [];
    // <img src>
    preg_match_all('/<img[^>]+src=["\']([^"\']+\.(jpe?g|png|webp))["\'][^>]*>/i', $html, $m);
    foreach ($m[1] as $u) $out[] = resolveUrl($u, $base);
    // data-src
    preg_match_all('/data-src=["\']([^"\']+\.(jpe?g|png|webp))["\']/i', $html, $m2);
    foreach ($m2[1] as $u) $out[] = resolveUrl($u, $base);
    // background-image style url()
    preg_match_all('/url\(["\']?([^"\')\s]+\.(jpe?g|png|webp))["\']?\)/i', $html, $m3);
    foreach ($m3[1] as $u) $out[] = resolveUrl($u, $base);
    return array_values(array_unique(array_filter($out)));
}

function resolveUrl(string $url, string $base): string
{
    if (strpos($url, 'http') === 0) return $url;
    if (strpos($url, '//') === 0)   return 'https:' . $url;
    if (strpos($url, '/') === 0) {
        $p = parse_url($base);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
    }
    return '';
}

function saveImage(string $url, string $dir, string $prefix, string $cookieFile = ''): bool
{
    $urlPath = parse_url($url, PHP_URL_PATH);
    $urlPath = is_string($urlPath) ? $urlPath : '';
    $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION)) ?: 'jpg';
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
    $fname = $dir . '/' . $prefix . '_' . md5($url) . '.' . $ext;
    if (file_exists($fname)) return true;

    $opts = [
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
    ];
    if ($cookieFile) {
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
        $opts[CURLOPT_COOKIEJAR]  = $cookieFile;
    }
    $res = curlGet($url, $opts);
    if ($res['code'] === 200 && strlen($res['body']) > 2048) {
        file_put_contents($fname, $res['body']);
        return true;
    }
    return false;
}

// ═══════════════════════════════════════════════════════════════════════════
// Platform handlers
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Basic auth + HTML gallery scraping (+ ScrapingBee for JS-rendered sites)
 * Works for Williams, Haas, Mercedes
 */
function fetchBasicAuthGallery(array $cred, string $dir, int $limit, string $sbKey = ''): array
{
    $cookie = tempnam(sys_get_temp_dir(), 'fp_hub_');
    $paths = ['', '/gallery', '/images', '/media', '/photos', '/press', '/press-kit', '/latest'];
    $allImages = [];

    foreach ($paths as $path) {
        $targetUrl = $cred['url'] . $path;

        if ($sbKey !== '') {
            $res = scrapingBeeFetch($targetUrl, $sbKey, $cred['username'], $cred['password']);
        } else {
            $res = curlGet($targetUrl, [
                CURLOPT_USERPWD    => $cred['username'] . ':' . $cred['password'],
                CURLOPT_HTTPAUTH   => CURLAUTH_BASIC,
                CURLOPT_COOKIEFILE => $cookie,
                CURLOPT_COOKIEJAR  => $cookie,
                CURLOPT_HTTPHEADER => ['Accept: text/html,*/*'],
            ]);
        }

        if ($res['code'] === 200) {
            $imgs = extractImagesFromHtml($res['body'], $targetUrl);
            $imgs = array_filter($imgs, fn($u) => !preg_match('/logo|icon|sprite|placeholder|thumb\.svg/i', $u));
            $allImages = array_merge($allImages, array_values($imgs));
        }
    }

    $allImages = array_slice(array_unique($allImages), 0, $limit);
    $saved = 0;
    foreach ($allImages as $url) {
        if (saveImage($url, $dir, basename($dir), $cookie)) $saved++;
    }

    @unlink($cookie);
    return ['found' => count($allImages), 'saved' => $saved];
}

/**
 * Red Bull Content Pool – session-based login then JSON API
 */
function fetchRedBullPool(array $cred, string $dir, int $limit): array
{
    $cookie = tempnam(sys_get_temp_dir(), 'fp_rbcp_');

    // Step 1: GET login page
    $page = curlGet($cred['url'] . '/login', [
        CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie,
    ]);

    // Step 2: POST login
    $login = curlPost($cred['url'] . '/api/v1/auth/login', json_encode([
        'email'    => $cred['username'],
        'password' => $cred['password'],
    ]), [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
        'Referer: ' . $cred['url'] . '/login',
    ], [CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);

    $authData = json_decode($login['body'], true);
    $token = $authData['token'] ?? $authData['access_token'] ?? $authData['data']['token'] ?? '';

    // Step 3: Search images
    $filter = isset($cred['filter']) ? $cred['filter'] : 'formula 1';
    $searchUrl = $cred['url'] . '/api/v1/search?q=' . urlencode($filter) . '&contentType=photo&limit=' . $limit;

    $headers = ['Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    $search = curlGet($searchUrl, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_COOKIEJAR  => $cookie,
    ]);

    $searchData = json_decode($search['body'], true);
    $items = $searchData['items'] ?? $searchData['results'] ?? $searchData['data'] ?? [];

    $saved = 0;
    foreach (array_slice($items, 0, $limit) as $item) {
        $imgUrl = $item['downloadUrl'] ?? $item['url'] ?? $item['previewUrl'] ?? '';
        if ($imgUrl && saveImage($imgUrl, $dir, 'rb', $cookie)) $saved++;
    }

    // Fallback: scrape HTML
    if ($saved === 0) {
        $html = curlGet($cred['url'] . '/search?q=formula+1&contentType=photo', [
            CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $imgs = extractImagesFromHtml($html['body'], $cred['url']);
        $imgs = array_filter($imgs, fn($u) => preg_match('/\.(jpe?g|png)$/i', (string)parse_url($u, PHP_URL_PATH)));
        $imgs = array_slice(array_values($imgs), 0, $limit);
        foreach ($imgs as $url) if (saveImage($url, $dir, 'rb', $cookie)) $saved++;
        $items = $imgs;
    }

    @unlink($cookie);
    return ['found' => count($items), 'saved' => $saved];
}

/**
 * Canto DAM API (Aston Martin: astonmartinf1.canto.global)
 * Uses OAuth2 client credentials or password grant
 */
function fetchCanto(array $cred, string $dir, int $limit): array
{
    $tenant  = $cred['tenant'] ?? 'astonmartinf1';
    $apiBase = "https://{$tenant}.canto.global/api/v1";

    // Try OAuth2 password grant
    $tokenRes = curlPost("https://{$tenant}.canto.global/oauth/api/oauth2/token",
        http_build_query([
            'grant_type' => 'password',
            'username'   => $cred['username'],
            'password'   => $cred['password'],
            'app_id'     => 'formulapaddock',
        ]),
        ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']
    );

    $tokenData = json_decode($tokenRes['body'], true);
    $token = $tokenData['access_token'] ?? '';

    // List all assets
    $headers = ['Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    $assetsUrl = $apiBase . '/search?sortBy=time&sortDirection=descending&limit=' . $limit . '&start=0';
    $assets = curlGet($assetsUrl, [CURLOPT_HTTPHEADER => $headers]);
    $data = json_decode($assets['body'], true);

    $results = $data['results'] ?? $data['found'] ?? [];
    $saved = 0;

    foreach (array_slice($results, 0, $limit) as $item) {
        // Canto download URL format
        $itemId  = $item['id'] ?? '';
        $scheme  = $item['scheme'] ?? 'image';
        if (!$itemId) continue;

        $dlUrl = "https://{$tenant}.canto.global/api_binary/v1/{$scheme}/{$itemId}/d";
        $headers2 = ['Accept: image/*,*/*'];
        if ($token) $headers2[] = 'Authorization: Bearer ' . $token;

        if (saveImage($dlUrl, $dir, 'am', '')) $saved++;
    }

    return ['found' => count($results), 'saved' => $saved];
}

/**
 * Alpine Media – public gallery with ScrapingBee fallback
 */
function fetchAlpineMedia(array $cred, string $dir, int $limit, string $sbKey = ''): array
{
    $apiUrl = 'https://media.alpinecars.com/api/assets?type=image&page=1&per_page=' . $limit . '&other=373';
    $jsonRes = curlGet($apiUrl, [CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $data = json_decode($jsonRes['body'], true);
    $assets = $data['assets'] ?? $data['items'] ?? $data['data'] ?? [];
    $saved = 0;

    if (!empty($assets)) {
        foreach (array_slice($assets, 0, $limit) as $a) {
            $url = $a['download_url'] ?? $a['url'] ?? $a['preview_url'] ?? '';
            if ($url && saveImage($url, $dir, 'alpine', '')) $saved++;
        }
    } else {
        // Use ScrapingBee or direct HTML scrape
        $pageUrl = $cred['url'] . '/section/mediatheque/?lang=eng&per_page=24&type=assets&page=1&other=373';
        $html = $sbKey !== ''
            ? scrapingBeeFetch($pageUrl, $sbKey)
            : curlGet($pageUrl);

        $imgs = extractImagesFromHtml($html['body'], $cred['url']);
        $imgs = array_filter($imgs, fn($u) => !preg_match('/icon|logo|sprite/i', $u));
        $imgs = array_slice(array_values($imgs), 0, $limit);
        foreach ($imgs as $url) if (saveImage($url, $dir, 'alpine', '')) $saved++;
        $assets = $imgs;
    }

    return ['found' => count($assets), 'saved' => $saved];
}

/**
 * Sauber MediaHub
 */
function fetchSauberMediahub(array $cred, string $dir, int $limit): array
{
    $cookie = tempnam(sys_get_temp_dir(), 'fp_sau_');

    // GET login
    $page = curlGet($cred['url'] . '/login/', [CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);

    // Extract CSRF
    preg_match('/name=["\']csrfmiddlewaretoken["\'][^>]*value=["\']([^"\']+)["\']/i', $page['body'], $cm);
    $csrf = $cm[1] ?? '';

    // POST login
    curlPost($cred['url'] . '/login/', http_build_query([
        'username'          => $cred['username'],
        'password'          => $cred['password'],
        'csrfmiddlewaretoken' => $csrf,
        'next'              => '/',
    ]), [
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: ' . $cred['url'] . '/login/',
        'X-CSRFToken: ' . $csrf,
    ], [CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);

    // Try JSON API
    $apiPaths = ['/api/assets/?limit=' . $limit, '/api/v1/assets/', '/media/assets/?format=json'];
    $saved = 0; $found = 0;

    foreach ($apiPaths as $path) {
        $res = curlGet($cred['url'] . $path, [
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie,
        ]);
        $data = json_decode($res['body'], true);
        $assets = $data['results'] ?? $data['assets'] ?? $data['items'] ?? [];
        if (!empty($assets)) {
            $found = count($assets);
            foreach (array_slice($assets, 0, $limit) as $a) {
                $url = $a['url'] ?? $a['download_url'] ?? $a['file'] ?? '';
                if ($url) {
                    $abs = resolveUrl($url, $cred['url']);
                    if ($abs && saveImage($abs, $dir, 'sau', $cookie)) $saved++;
                }
            }
            break;
        }
    }

    if ($saved === 0) {
        // HTML fallback
        $html = curlGet($cred['url'] . '/', [CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);
        $imgs = extractImagesFromHtml($html['body'], $cred['url']);
        $imgs = array_filter($imgs, fn($u) => !preg_match('/logo|icon|nav/i', $u));
        $found = count($imgs);
        foreach (array_slice(array_values($imgs), 0, $limit) as $url) {
            if (saveImage($url, $dir, 'sau', $cookie)) $saved++;
        }
    }

    @unlink($cookie);
    return ['found' => $found, 'saved' => $saved];
}

/**
 * Pirelli F1 Press Area
 */
function fetchPirelliPressArea(array $cred, string $dir, int $limit): array
{
    $cookie = tempnam(sys_get_temp_dir(), 'fp_pir_');

    // GET login page
    $page = curlGet($cred['url'] . '/index.php', [CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);

    // POST login
    curlPost($cred['url'] . '/index.php', http_build_query([
        'email'    => $cred['username'],
        'password' => $cred['password'],
        'login'    => 'Login',
        'redirect' => '/',
    ]), [
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: ' . $cred['url'] . '/index.php',
    ], [CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);

    // Gallery pages
    $paths = ['/', '/gallery', '/photos', '/images', '/press-kit/photos'];
    $saved = 0; $found = 0;

    foreach ($paths as $p) {
        $res = curlGet($cred['url'] . $p, [
            CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie,
        ]);
        if ($res['code'] === 200) {
            $imgs = extractImagesFromHtml($res['body'], $cred['url']);
            $imgs = array_filter($imgs, fn($u) => preg_match('/\.(jpe?g|png)(\?|$)/i', $u));
            $imgs = array_filter($imgs, fn($u) => !preg_match('/logo|icon|nav|header/i', $u));
            if (count($imgs) > 2) {
                $found += count($imgs);
                foreach (array_slice(array_values($imgs), 0, $limit) as $url) {
                    if (saveImage($url, $dir, 'pir', $cookie)) $saved++;
                }
                if ($saved > 0) break;
            }
        }
    }

    @unlink($cookie);
    return ['found' => $found, 'saved' => $saved];
}

/**
 * Brandfolder (McLaren) – API v4
 */
function fetchBrandfolder(array $cred, string $dir, int $limit): array
{
    // Brandfolder API v4 - requires API key (not password auth)
    // Try session-based approach
    $cookie = tempnam(sys_get_temp_dir(), 'fp_bf_');

    $loginRes = curlPost('https://brandfolder.com/api/v4/sessions', json_encode([
        'email'    => $cred['username'],
        'password' => $cred['password'],
    ]), [
        'Content-Type: application/json',
        'Accept: application/json',
    ], [CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);

    $loginData = json_decode($loginRes['body'], true);
    $apiKey = $loginData['data']['attributes']['default_api_key'] ?? '';

    $headers = ['Accept: application/json'];
    if ($apiKey) $headers[] = 'Authorization: Bearer ' . $apiKey;

    // List collections
    $orgRes = curlGet('https://brandfolder.com/api/v4/brandfolders?fields[assets]=preview_url,cdn_url&include=assets', [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie,
    ]);

    $orgData = json_decode($orgRes['body'], true);
    $assets = [];

    // Extract from included assets
    $included = $orgData['included'] ?? [];
    foreach ($included as $item) {
        if (($item['type'] ?? '') === 'assets') {
            $attrs = $item['attributes'] ?? [];
            $url = $attrs['cdn_url'] ?? $attrs['preview_url'] ?? '';
            if ($url) $assets[] = $url;
        }
    }

    $saved = 0;
    foreach (array_slice($assets, 0, $limit) as $url) {
        if (saveImage($url, $dir, 'mcl', $cookie)) $saved++;
    }

    @unlink($cookie);
    return ['found' => count($assets), 'saved' => $saved];
}


// ═══════════════════════════════════════════════════════════════════════════
// Main loop
// ═══════════════════════════════════════════════════════════════════════════

$results = [];

foreach ($teams as $teamId) {
    if (!isset($HUB_CREDENTIALS[$teamId])) {
        $results[$teamId] = ['ok' => false, 'error' => 'Team non riconosciuto', 'found' => 0, 'saved' => 0];
        continue;
    }

    $cred    = $HUB_CREDENTIALS[$teamId];
    $label   = $cred['label'];
    $teamDir = $baseDir . '/' . $teamId;

    if (!is_dir($teamDir) && !@mkdir($teamDir, 0777, true) && !is_dir($teamDir)) {
        $results[$teamId] = ['ok' => false, 'error' => 'Impossibile creare cartella', 'found' => 0, 'saved' => 0];
        continue;
    }

    try {
        $r = match($cred['platform']) {
            'basic_auth_gallery'  => fetchBasicAuthGallery($cred, $teamDir, $limit, $sbKey),
            'redbull_pool'        => fetchRedBullPool($cred, $teamDir, $limit),
            'canto'               => fetchCanto($cred, $teamDir, $limit),
            'alpine_media'        => fetchAlpineMedia($cred, $teamDir, $limit, $sbKey),
            'sauber_mediahub'     => fetchSauberMediahub($cred, $teamDir, $limit),
            'pirelli_pressarea'   => fetchPirelliPressArea($cred, $teamDir, $limit),
            'brandfolder'         => fetchBrandfolder($cred, $teamDir, $limit),
            default               => ['found' => 0, 'saved' => 0],
        };

        $results[$teamId] = [
            'ok'     => true,
            'label'  => $label,
            'found'  => $r['found'],
            'saved'  => $r['saved'],
            'folder' => 'uploads/team-hubs/' . $teamId,
        ];

    } catch (Throwable $e) {
        $results[$teamId] = [
            'ok'    => false,
            'label' => $label,
            'error' => $e->getMessage(),
            'found' => 0,
            'saved' => 0,
        ];
    }
}

$totalSaved = array_sum(array_column($results, 'saved'));

jsonResponse([
    'ok'          => true,
    'total_saved' => $totalSaved,
    'results'     => $results,
]);
