<?php

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Metodo non supportato'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    jsonResponse(['ok' => false, 'message' => 'Payload non valido'], 400);
}

$teams = $payload['teams'] ?? [];
if (!is_array($teams) || $teams === []) {
    jsonResponse(['ok' => false, 'message' => 'Dati team mancanti'], 400);
}

$rows = [];
foreach ($teams as $team) {
    if (!is_array($team)) continue;
    $name = trim((string)($team['name'] ?? ''));
    if ($name === '') continue;

    $drivers = $team['drivers'] ?? [];
    $driverNames = [];
    if (is_array($drivers)) {
        foreach ($drivers as $driver) {
            if (!is_array($driver)) continue;
            $fullName = trim((string)(($driver['first_name'] ?? '') . ' ' . ($driver['last_name'] ?? '')));
            if ($fullName !== '') $driverNames[] = $fullName;
        }
    }

    $comment = trim((string)($team['comment'] ?? ''));
    $image = trim((string)($team['image'] ?? ''));

    $rows[] = [
        'name' => $name,
        'drivers' => $driverNames,
        'comment' => $comment,
        'image' => $image,
    ];
}

if ($rows === []) {
    jsonResponse(['ok' => false, 'message' => 'Nessun team valido da elaborare'], 400);
}

$teamText = [];
foreach ($rows as $row) {
    $teamText[] = implode("\n", [
        'Team: ' . $row['name'],
        'Piloti: ' . (!empty($row['drivers']) ? implode(', ', $row['drivers']) : 'n.d.'),
        'Commento utente: ' . ($row['comment'] !== '' ? $row['comment'] : 'nessun commento'),
        'Immagine team: ' . ($row['image'] !== '' ? $row['image'] : 'nessuna immagine'),
    ]);
}

$sourceText = implode("\n\n", $teamText);

$prompt = "Sei un SEO copywriter italiano specializzato in Formula 1. "
    . "Genera un articolo SEO completo in HTML (solo HTML, niente markdown) basato sui dati team post-gara. "
    . "Regole: un solo <h1>, usa <h2> per sezioni per ogni team, integra i commenti utente se presenti, "
    . "scrivi in stile giornalistico sportivo, paragrafi brevi, tono professionale. "
    . "Aggiungi una meta-description come primo paragrafo in corsivo. "
    . "Se ci sono URL immagine, inserisci tag <img> con alt descrittivo nel team corrispondente. "
    . "Output HTML pronto per WordPress: NON includere tag <meta>, <title>, <html>, <head> o <body>; inizia direttamente dall'<h1>. "
    . "Chiudi con conclusioni SEO orientate alla keyword 'analisi post gara Formula 1'.\n\n"
    . "DATI TEAM:\n" . $sourceText;

$apiKey = trim((string)($appConfig['gemini_api_key'] ?? ''));
if ($apiKey === '') {
    jsonResponse(['error' => 'API key non configurata'], 400);
}

$geminiModels = $appConfig['gemini_models'] ?? [
    'gemini-2.0-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
    'gemini-1.5-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
];

$request = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
            ],
        ],
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'topP' => 0.9,
        'maxOutputTokens' => 8192,
    ],
];

$res = null;
$successfulModel = null;
$lastError = null;

foreach ($geminiModels as $mName => $mUrl) {
    $r = postJson($mUrl . '?key=' . urlencode($apiKey), $request);
    if (!empty($r['ok']) && (int)($r['status'] ?? 0) >= 200 && (int)($r['status'] ?? 0) < 400) {
        $res = $r;
        $successfulModel = $mName;
        break;
    }
    $lastError = $r;
}

if (!$res) {
    $status = (int)($lastError['status'] ?? 502);
    $errorCode = $lastError['json']['error']['code'] ?? null;
    $errorMessage = $lastError['json']['error']['message'] ?? $lastError['error'] ?? 'Errore sconosciuto';
    
    $errorMsg = 'Errore nella risposta dal modello';
    if ($errorCode === 429) {
        $errorMsg = 'Quota API esaurita - Riprovare più tardi';
    } elseif ($status >= 500) {
        $errorMsg = 'Servizi Gemini temporaneamente non disponibili';
    } elseif ($status === 404) {
        $errorMsg = 'Modello non configurato correttamente';
    }
    
    jsonResponse(['error' => $errorMsg, 'details' => $errorMessage], $status > 0 ? $status : 502);
}

$text = '';
$candidates = $res['json']['candidates'] ?? [];
if (is_array($candidates)) {
    foreach ($candidates as $candidate) {
        $parts = $candidate['content']['parts'] ?? [];
        if (!is_array($parts)) continue;
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= (string)$part['text'];
            }
        }
    }
}

$text = trim($text);
if ($text === '') {
    jsonResponse(['error' => 'Nessun testo generato'], 500);
}

$text = stripPageMetaTags($text);

jsonResponse([
    'ok' => true,
    'source' => 'gemini',
    'draft_html' => $text,
]);
