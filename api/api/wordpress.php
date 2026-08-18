<?php

require __DIR__ . '/bootstrap.php';

function getSiteConfig(array $sitesConfig, string $siteKey): ?array
{
    $site = $sitesConfig['sites'][$siteKey] ?? null;
    if (!is_array($site)) {
        return null;
    }
    $site['key'] = $siteKey;
    return $site;
}

function wpAuthHeader(array $site): string
{
    $user = (string)($site['username'] ?? '');
    $pass = (string)($site['application_password'] ?? '');
    return 'Basic ' . base64_encode($user . ':' . $pass);
}

function wpUploadMediaFile(array $site, string $filePath): ?array
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return null;
    }

    $base = rtrim((string)($site['url'] ?? ''), '/');
    if ($base === '') {
        return null;
    }

    $binary = @file_get_contents($filePath);
    if ($binary === false) {
        return null;
    }

    $filename = basename($filePath);
    $mime = @mime_content_type($filePath) ?: 'application/octet-stream';

    $res = httpRequest(
        $base . '/wp-json/wp/v2/media',
        'POST',
        [
            'Authorization' => wpAuthHeader($site),
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Content-Type' => $mime,
        ],
        $binary,
        45
    );

    if (($res['status'] ?? 500) >= 400) {
        return null;
    }

    $json = json_decode($res['body'] ?? '', true);
    if (!is_array($json)) {
        return null;
    }

    return [
        'id' => (int)($json['id'] ?? 0),
        'source_url' => (string)($json['source_url'] ?? ''),
    ];
}

function wpResolveLocalImagePath(string $src): ?string
{
    $src = trim($src);
    if ($src === '' || str_starts_with($src, 'data:')) {
        return null;
    }

    $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $rootNormalized = str_replace('\\', '/', $root);
    $projectBaseName = strtolower((string)basename($rootNormalized));

    $buildCandidateFromPath = static function (string $path) use ($root, $projectBaseName): ?string {
        $normalizedPath = '/' . ltrim(str_replace('\\', '/', $path), '/');

        $relativePath = ltrim($normalizedPath, '/');
        if ($projectBaseName !== '' && str_starts_with(strtolower($relativePath), $projectBaseName . '/')) {
            $relativePath = ltrim(substr($relativePath, strlen($projectBaseName) + 1), '/');
        }

        $candidates = [
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($normalizedPath, '/')),
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    };

    if (preg_match('#^https?://#i', $src)) {
        $path = parse_url($src, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        return $buildCandidateFromPath($path);
    }

    if (str_starts_with($src, '/')) {
        return $buildCandidateFromPath($src);
    }

    $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $src);
    return is_file($candidate) ? $candidate : null;
}

function wpExtractBodyInnerHtml(DOMDocument $dom): string
{
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }

    $html = '';
    foreach ($body->childNodes as $child) {
        $html .= $dom->saveHTML($child);
    }
    return $html;
}

function wpUploadEmbeddedImages(array $site, string $html): array
{
    $content = trim($html);
    if ($content === '') {
        return ['content' => $html, 'featured_media' => 0];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    if (!$loaded) {
        return ['content' => $html, 'featured_media' => 0];
    }

    $featuredMedia = 0;
    $cacheByPath = [];
    $images = $dom->getElementsByTagName('img');

    for ($i = 0; $i < $images->length; $i++) {
        $img = $images->item($i);
        if (!$img instanceof DOMElement) {
            continue;
        }

        $src = (string)$img->getAttribute('src');
        $localPath = wpResolveLocalImagePath($src);
        if ($localPath === null) {
            continue;
        }

        if (!isset($cacheByPath[$localPath])) {
            $cacheByPath[$localPath] = wpUploadMediaFile($site, $localPath);
        }

        $uploaded = $cacheByPath[$localPath];
        if (!is_array($uploaded) || ($uploaded['source_url'] ?? '') === '') {
            continue;
        }

        $uploadedUrl = (string)$uploaded['source_url'];
        $img->setAttribute('src', $uploadedUrl);

        $parent = $img->parentNode;
        if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'a') {
            $href = trim((string)$parent->getAttribute('href'));
            $hrefLocalPath = $href === '' ? null : wpResolveLocalImagePath($href);
            if ($href === '' || $href === trim($src) || ($hrefLocalPath !== null && $hrefLocalPath === $localPath)) {
                $parent->setAttribute('href', $uploadedUrl);
            }
        } else {
            $anchor = $dom->createElement('a');
            $anchor->setAttribute('href', $uploadedUrl);
            $img->parentNode?->replaceChild($anchor, $img);
            $anchor->appendChild($img);
        }

        if (($uploaded['id'] ?? 0) > 0) {
            $img->setAttribute('data-wp-media-id', (string)$uploaded['id']);
            if ($featuredMedia === 0) {
                $featuredMedia = (int)$uploaded['id'];
            }
        }
    }

    // Fallback 1: wp-image-N class su <img> già caricati in WP
    if ($featuredMedia === 0) {
        $allImgs = $dom->getElementsByTagName('img');
        for ($j = 0; $j < $allImgs->length; $j++) {
            $im = $allImgs->item($j);
            if (!$im instanceof DOMElement) continue;
            $cls = (string)$im->getAttribute('class');
            if (preg_match('/\bwp-image-(\d+)\b/', $cls, $cm)) {
                $featuredMedia = (int)$cm[1];
                break;
            }
        }
    }

    // Fallback 2: id nel commento blocco Gutenberg <!-- wp:image {"id":N} -->
    if ($featuredMedia === 0 && preg_match('/<!--\s*wp:image\s+({[^}]*})/s', $content, $bm)) {
        $blockAttrs = json_decode($bm[1], true);
        if (!empty($blockAttrs['id'])) {
            $featuredMedia = (int)$blockAttrs['id'];
        }
    }

    return [
        'content' => wpExtractBodyInnerHtml($dom),
        'featured_media' => $featuredMedia,
    ];
}

function wpRequest(array $site, string $path, string $method = 'GET', ?array $payload = null): array
{
    $base = rtrim((string)($site['url'] ?? ''), '/');
    $url = $base . '/wp-json/wp/v2/' . ltrim($path, '/');

    $headers = [
        'Authorization' => wpAuthHeader($site),
    ];

    if ($payload !== null) {
        $res = postJson($url, $payload, $headers);
        return [
            'status' => $res['status'],
            'json' => $res['json'],
            'raw' => $res['raw'],
        ];
    }

    $res = httpRequest($url, $method, $headers);
    $decoded = json_decode($res['body'] ?? '', true);

    return [
        'status' => $res['status'],
        'json' => is_array($decoded) ? $decoded : [],
        'raw' => $res['body' ?? ''],
    ];
}

function wpNormalizeSeoValue(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($maxLength <= 0) {
        return $value;
    }

    if (mb_strlen($value) <= $maxLength) {
        return $value;
    }

    $slice = mb_substr($value, 0, $maxLength + 1);
    $lastSpace = mb_strrpos($slice, ' ');
    $trimmed = $lastSpace !== false && $lastSpace > 20
        ? mb_substr($slice, 0, $lastSpace)
        : mb_substr($value, 0, $maxLength);

    return wpTidySeoEnding((string)$trimmed, $maxLength);
}

function wpTidySeoEnding(string $value, int $maxLength): string
{
    $value = trim((string)preg_replace('/[,:;.!?\s]+$/u', '', $value));
    $weakWords = [
        'e', 'o', 'ma', 'che', 'di', 'del', 'della', 'dello', 'dei', 'degli', 'delle',
        'a', 'ad', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'il', 'lo', 'la', 'i',
        'gli', 'le', 'un', 'una', 'uno', 'piu', 'più', 'anche',
    ];

    $parts = preg_split('/\s+/u', $value) ?: [];
    while (count($parts) > 1 && in_array(mb_strtolower((string)end($parts)), $weakWords, true)) {
        array_pop($parts);
    }

    $value = trim((string)preg_replace('/[,:;.!?\s]+$/u', '', implode(' ', $parts)));
    if ($value !== '' && mb_strlen($value) + 1 <= $maxLength) {
        return $value . '.';
    }

    return $value;
}

function wpBuildSiteSeoMetaPayload(array $seo): array
{
    $metaPayload = [];

    if (($seo['seo_title'] ?? '') !== '') {
        $metaPayload['_siteseo_titles_title'] = wpNormalizeSeoValue((string)$seo['seo_title'], 60);
    }
    if (($seo['meta_description'] ?? '') !== '') {
        $metaPayload['_siteseo_titles_desc'] = wpNormalizeSeoValue((string)$seo['meta_description'], 160);
    }
    if (($seo['focus_keyword'] ?? '') !== '') {
        $metaPayload['_siteseo_analysis_target_kw'] = wpNormalizeSeoValue((string)$seo['focus_keyword'], 80);
    }

    return $metaPayload;
}

function wpUpdateSiteSeoMetaViaDb(array $site, int $postId, array $metaPayload): array
{
    $dbHost = trim((string)($site['db_host'] ?? ''));
    $dbName = trim((string)($site['db_name'] ?? ''));
    $dbUser = trim((string)($site['db_user'] ?? ''));
    $dbPass = (string)($site['db_password'] ?? '');
    $dbPort = (int)($site['db_port'] ?? 3306);
    $dbPrefixRaw = trim((string)($site['db_prefix'] ?? 'wp_'));

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        return [
            'ok' => false,
            'status' => 400,
            'error' => 'Credenziali DB mancanti nel sito configurato (db_host, db_name, db_user, db_password).',
            'method' => 'db',
        ];
    }

    $dbPrefix = preg_match('/^[A-Za-z0-9_]+$/', $dbPrefixRaw) ? $dbPrefixRaw : 'wp_';
    $table = $dbPrefix . 'postmeta';

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort > 0 ? $dbPort : 3306, $dbName);
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->beginTransaction();

        $selectStmt = $pdo->prepare("SELECT meta_id FROM {$table} WHERE post_id = :post_id AND meta_key = :meta_key LIMIT 1");
        $updateStmt = $pdo->prepare("UPDATE {$table} SET meta_value = :meta_value WHERE meta_id = :meta_id");
        $insertStmt = $pdo->prepare("INSERT INTO {$table} (post_id, meta_key, meta_value) VALUES (:post_id, :meta_key, :meta_value)");

        foreach ($metaPayload as $metaKey => $metaValue) {
            $selectStmt->execute([
                ':post_id' => $postId,
                ':meta_key' => (string)$metaKey,
            ]);

            $row = $selectStmt->fetch();
            if (is_array($row) && !empty($row['meta_id'])) {
                $updateStmt->execute([
                    ':meta_value' => (string)$metaValue,
                    ':meta_id' => (int)$row['meta_id'],
                ]);
            } else {
                $insertStmt->execute([
                    ':post_id' => $postId,
                    ':meta_key' => (string)$metaKey,
                    ':meta_value' => (string)$metaValue,
                ]);
            }
        }

        $pdo->commit();

        return [
            'ok' => true,
            'status' => 200,
            'error' => '',
            'method' => 'db',
        ];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'ok' => false,
            'status' => 500,
            'error' => 'Scrittura meta SiteSEO via DB fallita: ' . $e->getMessage(),
            'method' => 'db',
        ];
    }
}

function wpResolveLocalWpLoadPath(array $site): string
{
    $configuredRoot = trim((string)($site['wp_root'] ?? ''));
    if ($configuredRoot !== '') {
        $configuredWpLoad = rtrim($configuredRoot, "/\\") . DIRECTORY_SEPARATOR . 'wp-load.php';
        if (is_file($configuredWpLoad)) {
            return $configuredWpLoad;
        }
    }

    $siteHost = strtolower((string)(parse_url((string)($site['url'] ?? ''), PHP_URL_HOST) ?: ''));
    $baseRoot = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
    $candidates = [];

    if ($siteHost === 'wec.formulapaddock.it') {
        $candidates[] = $baseRoot . DIRECTORY_SEPARATOR . 'wec' . DIRECTORY_SEPARATOR . 'wp-load.php';
    } elseif ($siteHost === 'formula2.formulapaddock.it') {
        $candidates[] = $baseRoot . DIRECTORY_SEPARATOR . 'f2' . DIRECTORY_SEPARATOR . 'wp-load.php';
    }

    $candidates[] = $baseRoot . DIRECTORY_SEPARATOR . 'wp-load.php';

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function wpUpdateSiteSeoMetaViaBootstrap(array $site, int $postId, array $metaPayload): array
{
    $wpLoadPath = wpResolveLocalWpLoadPath($site);
    if ($wpLoadPath === '') {
        return [
            'ok' => false,
            'status' => 404,
            'error' => 'wp-load.php non trovato per il sito selezionato.',
            'method' => 'wp-bootstrap',
        ];
    }

    try {
        require_once $wpLoadPath;

        if (!function_exists('update_post_meta')) {
            return [
                'ok' => false,
                'status' => 500,
                'error' => 'WordPress caricato, ma update_post_meta non disponibile.',
                'method' => 'wp-bootstrap',
            ];
        }

        foreach ($metaPayload as $metaKey => $metaValue) {
            update_post_meta($postId, (string)$metaKey, (string)$metaValue);
        }

        if (function_exists('clean_post_cache')) {
            clean_post_cache($postId);
        }

        return [
            'ok' => true,
            'status' => 200,
            'error' => '',
            'method' => 'wp-bootstrap',
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'status' => 500,
            'error' => 'Scrittura meta SiteSEO via WordPress fallita: ' . $e->getMessage(),
            'method' => 'wp-bootstrap',
        ];
    }
}

function wpUpdateSiteSeoMeta(array $site, int $postId, string $postType, array $seo): array
{
    if ($postId <= 0) {
        return ['ok' => false, 'status' => 0, 'error' => 'ID post non valido', 'method' => 'none'];
    }

    $metaPayload = wpBuildSiteSeoMetaPayload($seo);

    if ($metaPayload === []) {
        return ['ok' => true, 'status' => 0, 'error' => '', 'method' => 'none'];
    }

    $bootstrapRes = wpUpdateSiteSeoMetaViaBootstrap($site, $postId, $metaPayload);
    if (!empty($bootstrapRes['ok'])) {
        return $bootstrapRes;
    }

    $endpoint = (in_array($postType, ['post', 'page'], true) ? $postType . 's' : $postType) . '/' . $postId;

    $res = wpRequest($site, $endpoint, 'POST', ['meta' => $metaPayload]);
    if (($res['status'] ?? 500) < 400) {
        return [
            'ok' => true,
            'status' => (int)($res['status'] ?? 200),
            'error' => '',
            'method' => 'post-meta',
        ];
    }

    $fallbackPayload = [
        'meta_input' => $metaPayload,
    ] + $metaPayload;
    $fallbackRes = wpRequest($site, $endpoint, 'POST', $fallbackPayload);

    if (($fallbackRes['status'] ?? 500) < 400) {
        return [
            'ok' => true,
            'status' => (int)($fallbackRes['status'] ?? 200),
            'error' => '',
            'method' => 'meta_input',
        ];
    }

    $dbRes = wpUpdateSiteSeoMetaViaDb($site, $postId, $metaPayload);
    if (!empty($dbRes['ok'])) {
        return $dbRes;
    }

    $status = (int)($fallbackRes['status'] ?? $res['status'] ?? 500);
    $message = (string)($fallbackRes['json']['message'] ?? $res['json']['message'] ?? '');
    $code = (string)($fallbackRes['json']['code'] ?? $res['json']['code'] ?? '');

    $bootstrapMessage = (string)($bootstrapRes['error'] ?? '');
    $dbMessage = (string)($dbRes['error'] ?? '');
    $extraMessages = array_values(array_filter([$bootstrapMessage, $dbMessage]));

    if ($message === '' && !empty($extraMessages)) {
        $message = implode(' | ', $extraMessages);
    }

    if ($message === '') {
        $message = 'WordPress ha rifiutato il salvataggio dei meta SiteSEO via REST';
    }

    if ($code !== '') {
        $message = "{$message} ({$code})";
    }

    $dbError = trim((string)($dbRes['error'] ?? ''));
    if ($dbError !== '') {
        $message .= ' | ' . $dbError;
    }

    return [
        'ok' => false,
        'status' => $status,
        'error' => $message,
        'method' => 'failed',
    ];
}

function normalizeWpCategoryToken(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
}

function resolveCategoryIdByName(array $site, string $categoryName): int
{
    $name = trim($categoryName);
    if ($name === '') {
        return 0;
    }

    $res = wpRequest($site, 'categories?per_page=100');
    if (($res['status'] ?? 500) >= 400 || !is_array($res['json'])) {
        return 0;
    }

    $normalizedInput = normalizeWpCategoryToken($name);

    foreach ($res['json'] as $cat) {
        $catName = trim((string)($cat['name'] ?? ''));
        $catSlug = trim((string)($cat['slug'] ?? ''));
        if ($catName === '' && $catSlug === '') {
            continue;
        }

        if (normalizeWpCategoryToken($catName) === $normalizedInput || normalizeWpCategoryToken($catSlug) === $normalizedInput) {
            return (int)($cat['id'] ?? 0);
        }
    }

    return 0;
}

function ensureCategoryId(array $site, string $categoryName): int
{
    $id = resolveCategoryIdByName($site, $categoryName);
    if ($id > 0) {
        return $id;
    }

    $name = trim($categoryName);
    if ($name === '') {
        return 0;
    }

    $createRes = wpRequest($site, 'categories', 'POST', ['name' => $name]);
    if (($createRes['status'] ?? 500) < 400) {
        return (int)($createRes['json']['id'] ?? 0);
    }

    if ((int)($createRes['status'] ?? 500) === 400) {
        return resolveCategoryIdByName($site, $categoryName);
    }

    return 0;
}

$action = $_GET['action'] ?? '';

if ($action === 'sites') {
    $sites = [];
    foreach (($sitesConfig['sites'] ?? []) as $key => $s) {
        $sites[] = [
            'key' => $key,
            'label' => $s['label'] ?? $key,
            'url' => $s['url'] ?? '',
        ];
    }
    jsonResponse(['sites' => $sites]);
}

if ($action === 'create_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(['message' => 'Payload non valido'], 400);
    }

    $siteKey = trim((string)($payload['site'] ?? ''));
    $site = getSiteConfig($sitesConfig, $siteKey);
    if (!$site) {
        jsonResponse(['message' => 'Sito non valido'], 400);
    }

    $name = trim((string)($payload['name'] ?? ''));
    if ($name === '') {
        jsonResponse(['message' => 'Nome categoria obbligatorio'], 400);
    }

    $id = ensureCategoryId($site, $name);
    if ($id <= 0) {
        jsonResponse(['message' => 'Impossibile creare la categoria'], 500);
    }

    jsonResponse(['ok' => true, 'id' => $id, 'name' => $name]);
}

if ($action === 'meta') {
    $siteKey = trim((string)($_GET['site'] ?? ''));
    $site = getSiteConfig($sitesConfig, $siteKey);
    if (!$site) {
        jsonResponse(['message' => 'Sito non valido'], 400);
    }

    if (trim((string)($site['username'] ?? '')) === '' || trim((string)($site['application_password'] ?? '')) === '') {
        jsonResponse(['categories' => [], 'pages' => [], 'defaults' => []]);
    }

    $categoriesRes = wpRequest($site, 'categories?per_page=100');

    if (($categoriesRes['status'] ?? 500) >= 400) {
        jsonResponse(['message' => 'Errore caricamento categorie', 'status' => $categoriesRes['status']], 502);
    }


    $categories = [];
    foreach (($categoriesRes['json'] ?? []) as $c) {
        $categories[] = [
            'id' => $c['id'] ?? null,
            'name' => $c['name'] ?? '',
        ];
    }

    // Scopre tutti i post type esposti via REST e li recupera ordinati per data
    $typesRes = wpRequest($site, 'types?context=view');
    $postTypes = [];
    if (($typesRes['status'] ?? 500) < 400 && is_array($typesRes['json'])) {
        $skip = ['attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation'];
        foreach ($typesRes['json'] as $slug => $typeInfo) {
            if (!in_array($slug, $skip, true) && !empty($typeInfo['rest_base'])) {
                $postTypes[$slug] = (string)$typeInfo['rest_base'];
            }
        }
    }
    if (empty($postTypes)) {
        $postTypes = ['page' => 'pages', 'post' => 'posts'];
    }

    $pages = [];
    foreach ($postTypes as $slug => $restBase) {
        $ep = $restBase . '?per_page=100&orderby=date&order=desc&_fields=id,title,parent,date,link,featured_media&status=publish,draft,pending,future';
        $res = wpRequest($site, $ep);
        if (($res['status'] ?? 500) >= 400) continue;
        foreach (($res['json'] ?? []) as $p) {
            $pages[] = [
                'id'             => $p['id'] ?? null,
                'title'          => $p['title']['rendered'] ?? '',
                'parent'         => $p['parent'] ?? 0,
                'post_type'      => $slug,
                'date'           => $p['date'] ?? '',
                'link'           => $p['link'] ?? '',
                'featured_media' => (int)($p['featured_media'] ?? 0),
            ];
        }
    }
    jsonResponse([
        'categories' => $categories,
        'pages' => $pages,
        'defaults' => [
            'category' => $site['default_category'] ?? '',
            'parent_page' => $site['default_parent_page'] ?? '',
        ],
    ]);
}

if ($action === 'upload_media_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(['message' => 'Payload non valido'], 400);
    }

    $siteKey = trim((string)($payload['site'] ?? ''));
    $site = getSiteConfig($sitesConfig, $siteKey);
    if (!$site) {
        jsonResponse(['message' => 'Sito non valido'], 400);
    }

    if (trim((string)($site['username'] ?? '')) === '' || trim((string)($site['application_password'] ?? '')) === '') {
        jsonResponse(['message' => 'Credenziali mancanti in config.php'], 400);
    }

    $paths = $payload['paths'] ?? [];
    if (!is_array($paths)) {
        jsonResponse(['message' => 'paths deve essere un array'], 400);
    }

    $uploads = [];
    $cacheByPath = [];

    foreach ($paths as $src) {
        $src = trim((string)$src);
        if ($src === '') continue;

        $localPath = wpResolveLocalImagePath($src);
        if ($localPath === null) {
            $uploads[$src] = null;
            continue;
        }

        if (!isset($cacheByPath[$localPath])) {
            $cacheByPath[$localPath] = wpUploadMediaFile($site, $localPath);
        }

        $uploaded = $cacheByPath[$localPath];
        if (is_array($uploaded) && ($uploaded['source_url'] ?? '') !== '') {
            $uploads[$src] = [
                'url' => $uploaded['source_url'],
                'id'  => (int)($uploaded['id'] ?? 0),
            ];
        } else {
            $uploads[$src] = null;
        }
    }

    jsonResponse(['ok' => true, 'uploads' => $uploads]);
}

// ── upload_charts_batch: riceve immagini chart in base64 → carica su WP media ─
if ($action === 'upload_charts_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(['message' => 'Payload non valido'], 400);
    }

    $siteKey = trim((string)($payload['site'] ?? ''));
    $site = getSiteConfig($sitesConfig, $siteKey);
    if (!$site) {
        jsonResponse(['message' => 'Sito non valido'], 400);
    }

    if (trim((string)($site['username'] ?? '')) === '' || trim((string)($site['application_password'] ?? '')) === '') {
        jsonResponse(['message' => 'Credenziali mancanti'], 400);
    }

    $images = $payload['images'] ?? [];
    if (!is_array($images)) {
        jsonResponse(['message' => 'images deve essere un array'], 400);
    }

    $uploads = [];
    $tmpFiles = [];

    foreach ($images as $img) {
        $name    = preg_replace('/[^a-z0-9_\-]/i', '_', trim((string)($img['name'] ?? 'chart'))) . '.png';
        $dataUrl = trim((string)($img['data_url'] ?? ''));
        if ($dataUrl === '' || !preg_match('#^data:image/([a-z]+);base64,#', $dataUrl, $m)) {
            $uploads[$name] = null;
            continue;
        }

        $b64  = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $data = base64_decode($b64);
        if ($data === false || $data === '') {
            $uploads[$name] = null;
            continue;
        }

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'f1chart_' . uniqid() . '.png';
        if (file_put_contents($tmpPath, $data) === false) {
            $uploads[$name] = null;
            continue;
        }
        $tmpFiles[] = $tmpPath;

        $res = wpUploadMediaFile($site, $tmpPath);
        if (is_array($res) && ($res['source_url'] ?? '') !== '') {
            $uploads[$name] = ['url' => $res['source_url'], 'id' => (int)($res['id'] ?? 0)];
        } else {
            $uploads[$name] = null;
        }
    }

    foreach ($tmpFiles as $f) {
        @unlink($f);
    }

    jsonResponse(['ok' => true, 'uploads' => $uploads]);
}

if ($action === 'publish' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(['message' => 'Payload non valido'], 400);
    }

    $siteKey = trim((string)($payload['site'] ?? ''));
    $site = getSiteConfig($sitesConfig, $siteKey);
    if (!$site) {
        jsonResponse(['message' => 'Sito non valido'], 400);
    }

    if (trim((string)($site['username'] ?? '')) === '' || trim((string)($site['application_password'] ?? '')) === '') {
        jsonResponse(['message' => 'Credenziali mancanti in config.php'], 400);
    }

    $title = trim((string)($payload['title'] ?? ''));
    $content = trim((string)($payload['content'] ?? ''));
    $postType = trim((string)($payload['post_type'] ?? 'post'));
    $status = trim((string)($payload['status'] ?? 'draft'));
    $categoryId = (int)($payload['category_id'] ?? 0);
    $categoryName = trim((string)($payload['category_name'] ?? ''));
    if ($categoryName === '') {
        $categoryName = trim((string)($site['default_category'] ?? ''));
    }
    $parentPageId = (int)($payload['parent_page_id'] ?? 0);
    $parentPageUrl = trim((string)($payload['parent_page_url'] ?? ''));

    $seoTitle = trim((string)($payload['seo_title'] ?? ''));
    $metaDescription = trim((string)($payload['meta_description'] ?? ''));
    $focusKeyword = trim((string)($payload['focus_keyword'] ?? ''));

    if ($parentPageId <= 0 && $parentPageUrl !== '') {
        $slug = basename(rtrim((string)(parse_url($parentPageUrl, PHP_URL_PATH) ?? ''), '/'));
        if ($slug !== '') {
            $searchRes = wpRequest($site, $postType . '?slug=' . urlencode($slug) . '&_fields=id');
            if (($searchRes['status'] ?? 500) < 400 && !empty($searchRes['json'][0]['id'])) {
                $parentPageId = (int)$searchRes['json'][0]['id'];
            }
        }
    }

    if ($title === '' || $content === '') {
        jsonResponse(['message' => 'title e content sono obbligatori'], 400);
    }

    if ($postType === '' || str_contains($postType, '/') || str_contains($postType, '\\') || str_contains($postType, '..')) {
        jsonResponse(['message' => 'post_type non valido'], 400);
    }

    if ($categoryId <= 0 && $categoryName !== '') {
        $resolved = ensureCategoryId($site, $categoryName);
        if ($resolved > 0) {
            $categoryId = $resolved;
        }
    }

    $featuredMediaIdFromClient = (int)($payload['featured_media_id'] ?? 0);
    $processedMedia = wpUploadEmbeddedImages($site, $content);
    $contentWithWpMedia = trim((string)($processedMedia['content'] ?? ''));
    if ($contentWithWpMedia === '') {
        $contentWithWpMedia = $content;
    }
    $featuredMedia = $featuredMediaIdFromClient > 0
        ? $featuredMediaIdFromClient
        : (int)($processedMedia['featured_media'] ?? 0);

    $siteSeoMetaPayload = wpBuildSiteSeoMetaPayload([
        'seo_title' => $seoTitle,
        'meta_description' => $metaDescription,
        'focus_keyword' => $focusKeyword,
    ]);

    $wpPayload = [
        'title'   => $title,
        'content' => $contentWithWpMedia,
        'status'  => in_array($status, ['draft', 'publish', 'pending'], true) ? $status : 'draft',
    ];

    if ($siteSeoMetaPayload !== []) {
        $wpPayload['meta'] = $siteSeoMetaPayload;
        $wpPayload['meta_input'] = $siteSeoMetaPayload;
    }

    if ($featuredMedia > 0) {
        $wpPayload['featured_media'] = $featuredMedia;
    }

    if ($categoryId > 0) {
        $wpPayload['categories'] = [$categoryId];
    }

    if ($parentPageId > 0) {
        $wpPayload['parent'] = $parentPageId;
    }

    $wpEndpoint = in_array($postType, ['post', 'page'], true) ? $postType . 's' : $postType;
    $res = wpRequest($site, $wpEndpoint, 'POST', $wpPayload);
    if (($res['status'] ?? 500) >= 400) {
        jsonResponse([
            'message' => 'Errore pubblicazione WordPress',
            'status' => $res['status'],
            'details' => $res['json'],
        ], 502);
    }

    $postId = (int)($res['json']['id'] ?? 0);

    $siteSeoResult = [
        'ok' => ($siteSeoMetaPayload === []),
        'status' => 0,
        'error' => '',
        'method' => $siteSeoMetaPayload === [] ? 'none' : 'post-meta',
    ];

    if ($siteSeoMetaPayload !== []) {
        $responseMeta = $res['json']['meta'] ?? null;
        $responseMetaArr = is_array($responseMeta) ? $responseMeta : [];

        $hasAppliedMeta =
            array_key_exists('_siteseo_titles_title', $responseMetaArr)
            || array_key_exists('_siteseo_titles_desc', $responseMetaArr)
            || array_key_exists('_siteseo_analysis_target_kw', $responseMetaArr);

        if ($hasAppliedMeta) {
            $siteSeoResult = [
                'ok' => true,
                'status' => (int)($res['status'] ?? 200),
                'error' => '',
                'method' => 'post-meta',
            ];
        } else {
            $siteSeoResult = wpUpdateSiteSeoMeta($site, $postId, $postType, [
                'seo_title' => $seoTitle,
                'meta_description' => $metaDescription,
                'focus_keyword' => $focusKeyword,
            ]);
        }
    }

    jsonResponse([
        'ok' => true,
        'id' => $res['json']['id'] ?? null,
        'link' => $res['json']['link'] ?? '',
        'status' => $res['json']['status'] ?? '',
        'type' => $postType,
        'title' => $res['json']['title']['rendered'] ?? $title,
        'content' => $contentWithWpMedia,
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'featured_media' => $featuredMedia,
        'siteseo' => [
            'applied' => $siteSeoResult['ok'],
            'status' => $siteSeoResult['status'],
            'error' => $siteSeoResult['error'],
            'method' => $siteSeoResult['method'] ?? 'unknown',
            'seo_title' => wpNormalizeSeoValue($seoTitle, 60),
            'meta_description' => wpNormalizeSeoValue($metaDescription, 160),
            'focus_keyword' => wpNormalizeSeoValue($focusKeyword, 80),
        ],
    ]);
}


// ── generate_postgara_article ───────────────────────────────────────────────
if ($action === 'generate_postgara_article' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(['ok' => false, 'message' => 'Payload non valido'], 400);
    }

    $teams = $payload['teams'] ?? [];

    // Fallback: carica i dati salvati lato server se il client non ha mandato teams
    if (!is_array($teams) || $teams === []) {
        $storedFile = __DIR__ . '/../storage/postgara/team-data.json';
        if (is_file($storedFile)) {
            $storedRaw = @file_get_contents($storedFile);
            $storedData = $storedRaw ? json_decode($storedRaw, true) : [];
            if (is_array($storedData)) {
                $teams = [];
                foreach ($storedData as $teamName => $teamData) {
                    $teams[] = [
                        'name'    => $teamName,
                        'drivers' => [],
                        'comment' => (string)($teamData['comment'] ?? ''),
                        'image'   => (string)($teamData['image'] ?? ''),
                    ];
                }
            }
        }
    }

    if (!is_array($teams) || $teams === []) {
        jsonResponse(['ok' => false, 'message' => 'Nessun dato team disponibile. Aggiungi commenti o immagini prima di generare.'], 400);
    }

    // Costruisce il testo descrittivo per ogni team
    $teamLines = [];
    foreach ($teams as $team) {
        if (!is_array($team)) continue;
        $name = trim((string)($team['name'] ?? ''));
        if ($name === '') continue;

        $drivers = $team['drivers'] ?? [];
        $driverNames = [];
        if (is_array($drivers)) {
            foreach ($drivers as $d) {
                if (!is_array($d)) continue;
                $fn = trim((string)(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')));
                if ($fn !== '') $driverNames[] = $fn;
                elseif (isset($d['broadcast_name']) && $d['broadcast_name'] !== '') $driverNames[] = $d['broadcast_name'];
            }
        }

        $comment = trim((string)($team['comment'] ?? ''));
        $image   = trim((string)($team['image'] ?? ''));

        $teamLines[] = implode("\n", [
            'Team: ' . $name,
            'Piloti: ' . (!empty($driverNames) ? implode(', ', $driverNames) : 'n.d.'),
            'Commento: ' . ($comment !== '' ? $comment : 'nessun commento'),
            'Immagine: ' . ($image !== '' ? $image : 'nessuna'),
        ]);
    }

    if ($teamLines === []) {
        jsonResponse(['ok' => false, 'message' => 'Nessun team valido'], 400);
    }

    $sourceText = implode("\n\n", $teamLines);

    $prompt = "Sei un SEO copywriter italiano specializzato in Formula 1. "
        . "Genera un articolo SEO completo in HTML (solo HTML, niente markdown) basato sui dati team post-gara. "
        . "Regole: un solo <h1>, usa <h2> per sezione di ogni team, integra i commenti utente se presenti, "
        . "scrivi in stile giornalistico sportivo, paragrafi brevi, tono professionale. "
        . "Aggiungi una meta-description come primo paragrafo in corsivo. "
        . "Se ci sono URL immagine, inserisci tag <img src=\"URL\" alt=\"Team nome\"> nel team corrispondente. "
        . "Output HTML pronto per WordPress: NON includere <meta>, <title>, <html>, <head>, <body>; inizia dall'<h1>. "
        . "Chiudi con conclusioni SEO orientate alla keyword 'analisi post gara Formula 1'.\n\n"
        . "DATI TEAM:\n" . $sourceText;

    $apiKey = trim((string)($appConfig['gemini_api_key'] ?? ''));
    if ($apiKey === '') {
        jsonResponse(['ok' => false, 'message' => 'API key Gemini non configurata'], 500);
    }

    // Modelli con fallback: 2.0-flash prima, 1.5-flash se indisponibile
    $geminiModels = [
        'gemini-2.0-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
        'gemini-1.5-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
    ];

    $request = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature'     => 0.7,
            'topP'            => 0.9,
            'maxOutputTokens' => 8192,
            'thinkingConfig'  => ['thinkingBudget' => 0], // disabilita thinking → risposta compatta
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ],
    ];

    $generatedText = '';
    $usedModel     = null;

    foreach ($geminiModels as $modelName => $modelUrl) {
        $url = $modelUrl . '?key=' . urlencode($apiKey);
        $res = postJson($url, $request, [], 120);
        $status = (int)($res['status'] ?? 0);

        file_put_contents(__DIR__ . '/../errori.txt',
            "[postgara-seo] " . date('Y-m-d H:i:s') . " model={$modelName}"
            . " status={$status}"
            . " candidates=" . count($res['json']['candidates'] ?? [])
            . " finishReason=" . json_encode($res['json']['candidates'][0]['finishReason'] ?? null)
            . " error=" . json_encode($res['json']['error']['message'] ?? null) . "\n",
            FILE_APPEND
        );

        if (!empty($res['ok']) && $status >= 200 && $status < 400) {
            foreach (($res['json']['candidates'] ?? []) as $candidate) {
                foreach (($candidate['content']['parts'] ?? []) as $part) {
                    // Salta le parti di "thinking" (thought=true), prendi solo il testo finale
                    if (isset($part['text']) && empty($part['thought'])) {
                        $generatedText .= (string)$part['text'];
                    }
                }
            }
            if (trim($generatedText) !== '') { $usedModel = $modelName; break; }
        }

        // 503 = sovraccarico → prova il modello successivo, non abbandonare
    }

    if (trim($generatedText) === '') {
        $lastStatus = (int)($res['status'] ?? 0);
        $lastMsg    = $res['json']['error']['message'] ?? 'Nessun testo generato';
        if ($lastStatus === 503) {
            jsonResponse(['ok' => false, 'message' => 'Gemini è temporaneamente sovraccarico. Riprova tra qualche secondo.'], 503);
        }
        jsonResponse(['ok' => false, 'message' => $lastMsg], 500);
    }


    $generatedText = stripPageMetaTags(trim($generatedText));

    jsonResponse([
        'ok'         => true,
        'model_used' => $usedModel,
        'draft_html' => $generatedText,
    ]);
}

// ── update ──────────────────────────────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(['message' => 'Payload non valido'], 400);
    }

    $siteKey = trim((string)($payload['site'] ?? ''));
    $site = getSiteConfig($sitesConfig, $siteKey);
    if (!$site) {
        jsonResponse(['message' => 'Sito non valido'], 400);
    }

    if (trim((string)($site['username'] ?? '')) === '' || trim((string)($site['application_password'] ?? '')) === '') {
        jsonResponse(['message' => 'Credenziali mancanti in config.php'], 400);
    }

    $existingUrl     = trim((string)($payload['existing_url'] ?? ''));
    $title           = trim((string)($payload['title'] ?? ''));
    $content         = trim((string)($payload['content'] ?? ''));
    $postType        = trim((string)($payload['post_type'] ?? 'post'));
    $status          = trim((string)($payload['status'] ?? 'draft'));
    $date            = trim((string)($payload['date'] ?? ''));
    $categoryName    = trim((string)($payload['category_name'] ?? ''));
    $seoTitle        = trim((string)($payload['seo_title'] ?? ''));
    $metaDescription = trim((string)($payload['meta_description'] ?? ''));
    $focusKeyword    = trim((string)($payload['focus_keyword'] ?? ''));

    if ($existingUrl === '') {
        jsonResponse(['message' => 'existing_url obbligatorio'], 400);
    }
    if ($title === '' || $content === '') {
        jsonResponse(['message' => 'title e content sono obbligatori'], 400);
    }

    // ── Mappa slug tipo → rest_base (necessaria per costruire endpoint corretto) ─
    $skipTypes = ['attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation'];
    $typeMap   = ['post' => 'posts', 'page' => 'pages'];   // fallback sempre disponibile
    $typesRes  = wpRequest($site, 'types?context=view');
    if (($typesRes['status'] ?? 500) < 400 && is_array($typesRes['json'])) {
        foreach ($typesRes['json'] as $ts => $ti) {
            if (!in_array($ts, $skipTypes, true) && !empty($ti['rest_base'])) {
                $typeMap[$ts] = (string)$ti['rest_base'];
            }
        }
    }

    // ── Risolve URL → post ID ────────────────────────────────────────────────
    $postId           = (int)($payload['post_id'] ?? 0);
    $resolvedPostType = $postType;
    $resolvedRestBase = $typeMap[$postType] ?? 'posts';   // default sicuro

    if ($postId <= 0) {
        $parsedUrl = parse_url($existingUrl);

        // Strategia 1: shortlink ?p=ID
        parse_str($parsedUrl['query'] ?? '', $qp);
        if (!empty($qp['p'])) {
            $postId = (int)$qp['p'];
        }

        // Strategia 2: slug su tutti i tipi (tipo selezionato prima)
        if ($postId <= 0) {
            $slug         = basename(rtrim($parsedUrl['path'] ?? '', '/'));
            $orderedTypes = $typeMap;
            if (isset($orderedTypes[$postType])) {
                $orderedTypes = array_merge([$postType => $orderedTypes[$postType]], $orderedTypes);
            }

            foreach ($orderedTypes as $tryType => $tryRestBase) {
                if ($tryType === '' || str_contains($tryType, '/')) continue;
                $ep = $tryRestBase . '?slug=' . urlencode($slug) . '&_fields=id&per_page=1';
                $sr = wpRequest($site, $ep);
                if (($sr['status'] ?? 500) < 400 && !empty($sr['json'][0]['id'])) {
                    $postId           = (int)$sr['json'][0]['id'];
                    $resolvedPostType = $tryType;
                    $resolvedRestBase = $tryRestBase;   // ← salva rest_base corretto
                    break;
                }
            }
        }

        // Strategia 3: REST search
        if ($postId <= 0) {
            $slug = basename(rtrim($parsedUrl['path'] ?? '', '/'));
            $sr   = wpRequest($site, 'search?search=' . urlencode($slug) . '&type=post&_fields=id,subtype&per_page=10');
            if (($sr['status'] ?? 500) < 400 && !empty($sr['json'])) {
                foreach ($sr['json'] as $hit) {
                    if (!empty($hit['id'])) {
                        $postId           = (int)$hit['id'];
                        $resolvedPostType = $hit['subtype'] ?? $postType;
                        $resolvedRestBase = $typeMap[$resolvedPostType] ?? 'posts';
                        break;
                    }
                }
            }
        }
    }

    if ($postId <= 0) {
        jsonResponse(['message' => 'Articolo non trovato per URL: ' . $existingUrl . '. Verifica che l\'URL sia corretto e che il sito sia quello giusto.'], 422);
    }

    // ── Processa contenuto e media ───────────────────────────────────────────
    $processedMedia     = wpUploadEmbeddedImages($site, $content);
    $contentWithWpMedia = trim((string)($processedMedia['content'] ?? ''));
    if ($contentWithWpMedia === '') $contentWithWpMedia = $content;

    $featuredMediaIdFromClient = (int)($payload['featured_media_id'] ?? 0);
    $featuredMedia = $featuredMediaIdFromClient > 0
        ? $featuredMediaIdFromClient
        : (int)($processedMedia['featured_media'] ?? 0);

    // Categoria
    $categoryId = 0;
    if ($categoryName !== '') {
        $resolved = ensureCategoryId($site, $categoryName);
        if ($resolved > 0) $categoryId = $resolved;
    }

    // ── Build payload WP ─────────────────────────────────────────────────────
    $siteSeoMetaPayload = wpBuildSiteSeoMetaPayload([
        'seo_title'        => $seoTitle,
        'meta_description' => $metaDescription,
        'focus_keyword'    => $focusKeyword,
    ]);

    $wpPayload = [
        'title'   => $title,
        'content' => $contentWithWpMedia,
        'status'  => in_array($status, ['draft', 'publish', 'pending'], true) ? $status : 'draft',
    ];

    // Data pubblicazione: passa direttamente a WP (formato ISO 8601: "2025-05-22T10:30:00")
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $date)) {
        $wpPayload['date'] = $date;
    }

    if ($siteSeoMetaPayload !== []) {
        $wpPayload['meta']       = $siteSeoMetaPayload;
        $wpPayload['meta_input'] = $siteSeoMetaPayload;
    }
    if ($featuredMedia > 0)  $wpPayload['featured_media'] = $featuredMedia;
    if ($categoryId > 0)     $wpPayload['categories']     = [$categoryId];

    // ── Aggiorna il post esistente ────────────────────────────────────────────
    $res = wpRequest($site, $resolvedRestBase . '/' . $postId, 'POST', $wpPayload);

    if (($res['status'] ?? 500) >= 400) {
        jsonResponse([
            'message' => 'Errore aggiornamento WordPress',
            'status'  => $res['status'],
            'details' => $res['json'],
        ], 502);
    }

    // ── SEO ──────────────────────────────────────────────────────────────────
    $siteSeoResult = ['ok' => ($siteSeoMetaPayload === []), 'error' => '', 'method' => 'none'];
    if ($siteSeoMetaPayload !== []) {
        $responseMeta    = $res['json']['meta'] ?? null;
        $responseMetaArr = is_array($responseMeta) ? $responseMeta : [];
        $hasAppliedMeta  =
            array_key_exists('_siteseo_titles_title',       $responseMetaArr)
            || array_key_exists('_siteseo_titles_desc',     $responseMetaArr)
            || array_key_exists('_siteseo_analysis_target_kw', $responseMetaArr);

        $siteSeoResult = $hasAppliedMeta
            ? ['ok' => true,  'error' => '', 'method' => 'post-meta']
            : wpUpdateSiteSeoMeta($site, $postId, $resolvedPostType, [
                'seo_title'        => $seoTitle,
                'meta_description' => $metaDescription,
                'focus_keyword'    => $focusKeyword,
            ]);
    }

    jsonResponse([
        'ok'      => true,
        'id'      => $res['json']['id'] ?? $postId,
        'link'    => $res['json']['link'] ?? $existingUrl,
        'status'  => $res['json']['status'] ?? '',
        'type'    => $resolvedPostType,
        'title'   => $res['json']['title']['rendered'] ?? $title,
        'content' => $contentWithWpMedia,
        'siteseo' => [
            'applied' => $siteSeoResult['ok'],
            'error'   => $siteSeoResult['error'] ?? '',
            'method'  => $siteSeoResult['method'] ?? '',
        ],
    ]);
}
jsonResponse(['message' => 'Azione non valida'], 400);
