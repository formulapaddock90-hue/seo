<?php

require __DIR__ . '/bootstrap.php';

const SOCIAL_SPREADSHEET_ID = '1YSrwn0wcmxIQzucRoe2SmSWFWHIUAr0u624aMCY-wzE';
const SOCIAL_SPREADSHEET_SHEET_ID = 0;

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function normalizeGooglePrivateKey($privateKey): string
{
    $privateKey = trim((string)$privateKey);
    if ($privateKey === '') {
        return '';
    }

    $privateKey = str_replace(["\r\n", "\r"], "\n", $privateKey);
    return str_replace('\\n', "\n", $privateKey);
}

function isValidGooglePrivateKey($privateKey): bool
{
    $normalizedKey = normalizeGooglePrivateKey($privateKey);
    if ($normalizedKey === '') {
        return false;
    }

    $keyResource = @openssl_pkey_get_private($normalizedKey);
    if ($keyResource === false) {
        return false;
    }

    if (PHP_VERSION_ID < 80000 && is_resource($keyResource)) {
        openssl_free_key($keyResource);
    }

    return true;
}

function loadGoogleServiceAccount(array $appConfig): array
{
    $email = trim((string)($appConfig['google_service_account_email'] ?? ''));
    $privateKey = normalizeGooglePrivateKey($appConfig['google_service_account_private_key'] ?? '');    $keyFile = trim((string)($appConfig['google_service_account_key_file'] ?? ($appConfig['google_service_account_json'] ?? '')));
    $keyFileError = '';

    if (($email === '' || !isValidGooglePrivateKey($privateKey)) && $keyFile !== '') {
        $resolvedFile = $keyFile;
        if (preg_match('#^(?:[A-Za-z]:[\\/]|/)#', $resolvedFile) !== 1) {
            $resolvedFile = dirname(__DIR__, 2) . '/' . ltrim($resolvedFile, '/\\');
        }

        if (is_file($resolvedFile)) {
            $json = json_decode((string)file_get_contents($resolvedFile), true);
            if (is_array($json)) {
                $jsonEmail = trim((string)($json['client_email'] ?? ''));
                $jsonPrivateKey = normalizeGooglePrivateKey($json['private_key'] ?? '');

                if ($email === '' && $jsonEmail !== '') {
                    $email = $jsonEmail;
                }

                if (isValidGooglePrivateKey($jsonPrivateKey)) {
                    $privateKey = $jsonPrivateKey;
                    $keyFileError = '';
                }
            }
        } else {
            $keyFileError = 'File credenziali Google non trovato: ' . $resolvedFile;
        }
    }

    $envKeyFile = trim((string)(getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: ''));
    if (($email === '' || !isValidGooglePrivateKey($privateKey)) && $envKeyFile !== '' && is_file($envKeyFile)) {
        $json = json_decode((string)file_get_contents($envKeyFile), true);
        if (is_array($json)) {
            $jsonEmail = trim((string)($json['client_email'] ?? ''));
            $jsonPrivateKey = normalizeGooglePrivateKey($json['private_key'] ?? '');

            if ($email === '' && $jsonEmail !== '') {
                $email = $jsonEmail;
            }

            if (isValidGooglePrivateKey($jsonPrivateKey)) {
                $privateKey = $jsonPrivateKey;
                $keyFileError = '';
            }
        }
    }

    return [
        'email' => $email,
        'private_key' => $privateKey,
        'error' => $keyFileError,
    ];
}

function getGoogleAccessToken(array $serviceAccount): string
{
    $email = trim((string)($serviceAccount['email'] ?? ''));
    $privateKey = trim((string)($serviceAccount['private_key'] ?? ''));
    $serviceAccountError = trim((string)($serviceAccount['error'] ?? ''));

    if ($email === '' || $privateKey === '') {
        jsonResponse([
            'ok' => false,
            'message' => $serviceAccountError !== '' ? $serviceAccountError : 'Credenziali Google Sheets mancanti. Configura `google_service_account_email` e `google_service_account_private_key` in `config.php` oppure un file JSON di service account.',
        ], 500);
    }

    $keyResource = @openssl_pkey_get_private($privateKey);
    if ($keyResource === false) {
        jsonResponse([
            'ok' => false,
            'message' => 'Chiave privata Google non valida. Usa il file JSON del service account o una chiave PEM RSA completa.',
        ], 500);
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss' => $email,
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];

    $jwtHeader = base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $jwtClaims = base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));
    $unsignedJwt = $jwtHeader . '.' . $jwtClaims;

    $signature = '';
    $signOk = openssl_sign($unsignedJwt, $signature, $keyResource, OPENSSL_ALGO_SHA256);
    if (PHP_VERSION_ID < 80000 && is_resource($keyResource)) {
        openssl_free_key($keyResource);
    }

    if (!$signOk) {
        jsonResponse(['ok' => false, 'message' => 'Firma JWT non riuscita con la chiave privata configurata.'], 500);
    }

    $tokenResponse = httpRequest(
        'https://oauth2.googleapis.com/token',
        'POST',
        ['Content-Type' => 'application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsignedJwt . '.' . base64UrlEncode($signature),
        ]),
        30
    );

    $decoded = json_decode($tokenResponse['body'] ?? '', true);
    $accessToken = trim((string)($decoded['access_token'] ?? ''));

    if (($tokenResponse['status'] ?? 0) !== 200 || $accessToken === '') {
        jsonResponse([
            'ok' => false,
            'message' => 'Autenticazione Google Sheets fallita.',
            'details' => is_array($decoded) ? $decoded : ($tokenResponse['body'] ?? ''),
        ], 502);
    }

    return $accessToken;
}

function normalizeSocialRow(array $row): ?array
{
    $content = is_array($row['content'] ?? null) ? $row['content'] : [];
    $platform = strtolower(trim((string)($row['platform'] ?? '')));
    $legacyContent = isset($row['content']) && is_string($row['content']) ? trim($row['content']) : '';

    $normalized = [
        'data' => trim((string)($row['data'] ?? $row['date'] ?? date('Y-m-d'))),
        'facebook' => trim((string)($row['facebook'] ?? $content['facebook'] ?? '')),
        'twitter' => trim((string)($row['twitter'] ?? $content['twitter'] ?? '')),
        'linkedin' => trim((string)($row['linkedin'] ?? $content['linkedin'] ?? '')),
        'instagram' => trim((string)($row['instagram'] ?? $content['instagram'] ?? '')),
        'category' => trim((string)($row['category'] ?? $content['category'] ?? '')),
        'featured_image_url' => trim((string)($row['featured_image_url'] ?? $row['url_immagine_evidenza'] ?? $content['featured_image_url'] ?? '')),
    ];

    if ($legacyContent !== '' && in_array($platform, ['facebook', 'twitter', 'linkedin', 'instagram'], true) && $normalized[$platform] === '') {
        $normalized[$platform] = $legacyContent;
    }

    $hasAnyText = false;
    foreach (['facebook', 'twitter', 'linkedin', 'instagram'] as $field) {
        if ($normalized[$field] !== '') {
            $hasAnyText = true;
            break;
        }
    }

    return $hasAnyText ? $normalized : null;
}

function extractRowsFromPayload($payload): array
{
    $rows = [];

    if (!is_array($payload)) {
        return $rows;
    }

    $sourceRows = [];
    if (isListArray($payload)) {
        $sourceRows = $payload;
    } elseif (isset($payload['posts']) && is_array($payload['posts'])) {
        $sourceRows = $payload['posts'];
    } else {
        $sourceRows = [$payload];
    }

    foreach ($sourceRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $normalized = normalizeSocialRow($row);
        if ($normalized !== null) {
            $rows[] = $normalized;
        }
    }

    return $rows;
}

function appendRowsToSpreadsheet(string $accessToken, array $rows): array
{
    $requests = [];

    foreach ($rows as $row) {
        $requests[] = [
            'appendCells' => [
                'sheetId' => SOCIAL_SPREADSHEET_SHEET_ID,
                'rows' => [[
                    'values' => [
                        ['userEnteredValue' => ['stringValue' => (string)$row['data']]],
                        ['userEnteredValue' => ['stringValue' => (string)$row['facebook']]],
                        ['userEnteredValue' => ['stringValue' => (string)$row['twitter']]],
                        ['userEnteredValue' => ['stringValue' => (string)$row['linkedin']]],
                        ['userEnteredValue' => ['stringValue' => (string)$row['instagram']]],
                        ['userEnteredValue' => ['stringValue' => (string)($row['category'] ?? '')]],
                        ['userEnteredValue' => ['stringValue' => (string)($row['featured_image_url'] ?? '')]],
                    ],
                ]],
                'fields' => 'userEnteredValue',
            ],
        ];
    }

    $response = postJson(
        'https://sheets.googleapis.com/v4/spreadsheets/' . SOCIAL_SPREADSHEET_ID . ':batchUpdate',
        ['requests' => $requests],
        ['Authorization' => 'Bearer ' . $accessToken]
    );

    if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
        jsonResponse([
            'ok' => false,
            'message' => 'Scrittura su Google Sheet fallita.',
            'details' => $response['json']['error'] ?? ($response['raw'] ?? ''),
        ], 502);
    }

    return $response;
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
$rows = extractRowsFromPayload($payload);

if (empty($rows)) {
    jsonResponse([
        'ok' => false,
        'message' => 'Payload non valido. Invia almeno un post con `date`/`data` e uno o più campi tra `facebook`, `twitter`, `linkedin`, `instagram`.',
    ], 400);
}

$serviceAccount = loadGoogleServiceAccount($appConfig);
$accessToken = getGoogleAccessToken($serviceAccount);
appendRowsToSpreadsheet($accessToken, $rows);

jsonResponse([
    'ok' => true,
    'rows' => count($rows),
    'spreadsheetId' => SOCIAL_SPREADSHEET_ID,
]);
