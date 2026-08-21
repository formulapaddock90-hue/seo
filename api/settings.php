<?php
/**
 * api/settings.php
 * Salva e recupera impostazioni (JSON) su file server-side.
 * Non viene incluso nel controllo versioni.
 */

require __DIR__ . '/bootstrap.php';

$settingsFile = __DIR__ . '/../storage/settings.json';
$settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?? []) : [];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isBridge = defined('CONTENT_HUB_BRIDGE') && CONTENT_HUB_BRIDGE === true;

function bridgeSafeSites(array $sites): array
{
    return array_map(static function (array $site): array {
        return [
            'key' => $site['key'] ?? '',
            'label' => $site['label'] ?? '',
            'url' => $site['url'] ?? '',
            'username' => '',
            'application_password' => '',
            'default_category' => $site['default_category'] ?? '',
            'default_parent_page' => $site['default_parent_page'] ?? '',
        ];
    }, $sites);
}

if ($method === 'GET') {
    $sites = [];
    if (isset($settings['sites']) && is_array($settings['sites']) && count($settings['sites']) > 0) {
        $sites = $settings['sites'];
    } else {
        foreach (($sitesConfig['sites'] ?? []) as $key => $s) {
            $sites[] = [
                'key' => $key,
                'label' => $s['label'] ?? $key,
                'url' => $s['url'] ?? '',
                'username' => $s['username'] ?? '',
                'application_password' => $s['application_password'] ?? '',
                'default_category' => $s['default_category'] ?? '',
                'default_parent_page' => $s['default_parent_page'] ?? '',
            ];
        }
    }

    if ($isBridge) {
        jsonResponse([
            'ok' => true,
            'bridge' => true,
            'gemini_api_key' => '',
            'gemini_model_url' => $settings['gemini_model_url'] ?? ($appConfig['gemini_model_url'] ?? ''),
            'sites' => bridgeSafeSites($sites)
        ]);
    }

    jsonResponse([
        'ok' => true,
        'gemini_api_key' => $settings['gemini_api_key'] ?? ($appConfig['gemini_api_key'] ?? ''),
        'gemini_model_url' => $settings['gemini_model_url'] ?? ($appConfig['gemini_model_url'] ?? ''),
        'sites' => $sites
    ]);
}

if ($method === 'POST') {
    // Da GitHub Pages le credenziali restano solo lato Aruba: il bridge non
    // può sovrascrivere settings.json o password applicative WordPress.
    if ($isBridge) {
        jsonResponse([
            'ok' => false,
            'message' => 'Le impostazioni private si modificano solo dal pannello Aruba.'
        ], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach ($input as $k => $v) {
        $settings[$k] = $v;
    }
    if (!is_dir(dirname($settingsFile))) {
        @mkdir(dirname($settingsFile), 0777, true);
    }
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

    $sites = [];
    if (isset($settings['sites']) && is_array($settings['sites'])) {
        $sites = $settings['sites'];
    }

    jsonResponse(['ok' => true, 'sites' => $sites]);
}

jsonResponse(['message' => 'Metodo non supportato'], 405);
