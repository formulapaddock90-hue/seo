<?php
require_once __DIR__ . '/bootstrap.php';

$configPath = __DIR__ . '/../config.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function exportPhpArrayFile(string $path, array $payload): void
{
    $content = "<?php\n\nreturn " . var_export($payload, true) . ";\n";
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Impossibile scrivere config.php');
    }
}

if ($method === 'GET' && (($_GET['action'] ?? '') === 'test_gemini')) {
    $cfg = require $configPath;
    $apiKey = trim((string)($cfg['gemini_api_key'] ?? ''));
    $modelUrl = trim((string)($cfg['gemini_model_url'] ?? ''));

    if ($apiKey === '') {
        jsonResponse([
            'ok' => false,
            'message' => 'API key Gemini non configurata',
        ], 400);
    }

    if ($modelUrl === '') {
        jsonResponse([
            'ok' => false,
            'message' => 'URL modello Gemini non configurato',
        ], 400);
    }

    $request = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Rispondi con: OK'],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0,
            'maxOutputTokens' => 8,
        ],
    ];

    $res = postJson($modelUrl . '?key=' . urlencode($apiKey), $request);
    $status = (int)($res['status'] ?? 500);

    if (empty($res['ok']) || $status < 200 || $status >= 400) {
        $err = $res['json']['error'] ?? [];
        $message = (string)($err['message'] ?? $res['error'] ?? 'Errore Gemini');
        $reason = (string)($err['status'] ?? '');
        $code = (int)($err['code'] ?? $status);

        jsonResponse([
            'ok' => false,
            'message' => 'Test Gemini fallito',
            'details' => $message,
            'status' => $code,
            'reason' => $reason,
            'model_url' => $modelUrl,
        ], $status > 0 ? $status : 502);
    }

    jsonResponse([
        'ok' => true,
        'message' => 'Connessione Gemini valida',
        'status' => $status,
        'model_url' => $modelUrl,
    ]);
}

if ($method === 'GET') {
    $cfg = require $configPath;
    $key = trim((string)($cfg['gemini_api_key'] ?? ''));
    $modelUrl = trim((string)($cfg['gemini_model_url'] ?? ''));
    $sites = [];

    foreach (($cfg['sites'] ?? []) as $siteKey => $site) {
        if (!is_array($site)) {
            continue;
        }

        $sites[] = [
            'key' => $siteKey,
            'label' => (string)($site['label'] ?? $siteKey),
            'url' => (string)($site['url'] ?? ''),
            'username' => (string)($site['username'] ?? ''),
            'application_password' => (string)($site['application_password'] ?? ''),
            'default_category' => (string)($site['default_category'] ?? ''),
            'default_parent_page' => (string)($site['default_parent_page'] ?? ''),
        ];
    }

    jsonResponse([
        'gemini_api_key' => $key !== '' ? $key : '',
        'gemini_model_url' => $modelUrl,
        'gemini_configured' => $key !== '',
        'sites' => $sites,
    ]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $newKey = trim((string)($body['gemini_api_key'] ?? ''));
    $newModelUrl = trim((string)($body['gemini_model_url'] ?? ''));
    $incomingSites = is_array($body['sites'] ?? null) ? $body['sites'] : [];

    $config = require $configPath;
    $config['gemini_api_key'] = $newKey;
    if ($newModelUrl !== '') {
        $config['gemini_model_url'] = $newModelUrl;
    }

    $sites = [];
    foreach ($incomingSites as $site) {
        if (!is_array($site)) {
            continue;
        }

        $siteKey = trim((string)($site['key'] ?? ''));
        $label = trim((string)($site['label'] ?? ''));
        $url = trim((string)($site['url'] ?? ''));
        if ($siteKey === '' || $label === '' || $url === '') {
            continue;
        }

        $sites[$siteKey] = [
            'label' => $label,
            'url' => rtrim($url, '/'),
            'username' => trim((string)($site['username'] ?? '')),
            'application_password' => trim((string)($site['application_password'] ?? '')),
            'default_category' => trim((string)($site['default_category'] ?? '')),
            'default_parent_page' => trim((string)($site['default_parent_page'] ?? '')),
        ];
    }

    $config['sites'] = $sites;

    try {
        exportPhpArrayFile($configPath, $config);

        $responseSites = [];
        foreach ($sites as $siteKey => $site) {
            $responseSites[] = [
                'key' => $siteKey,
                'label' => $site['label'],
                'url' => $site['url'],
                'username' => $site['username'],
                'application_password' => $site['application_password'],
                'default_category' => $site['default_category'],
                'default_parent_page' => $site['default_parent_page'],
            ];
        }

        jsonResponse([
            'ok' => true,
            'gemini_configured' => $newKey !== '',
            'sites' => $responseSites,
        ]);
    } catch (Throwable $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Metodo non supportato'], 405);
