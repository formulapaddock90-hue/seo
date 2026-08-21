<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['message' => 'Metodo non supportato'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    jsonResponse(['message' => 'Payload non valido'], 400);
}

$rawText = trim((string)($payload['raw_text'] ?? ''));

if ($rawText === '') {
    jsonResponse(['message' => 'raw_text obbligatorio'], 400);
}

$prompt = "Analizza il seguente articolo di Formula 1 / Motorsport ed estrai o simula una ricerca di Google Trends per suggerire:\n"
    . "1. Una Keyword Principale focalizzata sull'evento o sul tema principale (es. 'GP Monaco F1 2026', 'Meteo GP Belgio F1')\n"
    . "2. Da 3 a 5 Keyword Correlate (es. 'orari gp monaco 2026', 'strategia gomme monaco')\n\n"
    . "Rispondi in formato JSON pulito (senza markdown o tag di codice) con questa struttura:\n"
    . "{\n"
    . "  \"main_keyword\": \"stringa\",\n"
    . "  \"related_keywords\": [\"stringa1\", \"stringa2\", \"stringa3\"]\n"
    . "}\n\n"
    . "Articolo:\n"
    . $rawText;

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
        'temperature' => 0.2,
        'responseMimeType' => 'application/json',
    ],
];

$text = '';
$successfulModel = null;

foreach ($geminiModels as $modelName => $modelUrl) {
    $url = $modelUrl . '?key=' . urlencode($apiKey);
    $res = postJson($url, $request);
    
    $status = (int)($res['status'] ?? 0);
    if (!empty($res['ok']) && $status >= 200 && $status < 400) {
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
        
        if (trim($text) !== '') {
            $successfulModel = $modelName;
            break;
        }
    }
}

if (trim($text) === '') {
    jsonResponse(['success' => false, 'message' => 'Nessun modello di Gemini è riuscito ad analizzare il testo.'], 500);
}

$parsed = json_decode($text, true);
if (!is_array($parsed)) {
    // Fallback parsing manuale se non ha restituito JSON valido
    preg_match('/"main_keyword"\s*:\s*"([^"]+)"/', $text, $m1);
    preg_match_all('/"([^"]+)"\s*(?:\]|\,)/', $text, $m2);
    
    $parsed = [
        'main_keyword' => $m1[1] ?? 'GP F1',
        'related_keywords' => !empty($m2[1]) ? array_slice($m2[1], 1) : ['f1 notizie', 'f1 meteo']
    ];
}

jsonResponse([
    'success' => true,
    'model_used' => $successfulModel,
    'main_keyword' => $parsed['main_keyword'] ?? '',
    'related_keywords' => $parsed['related_keywords'] ?? [],
]);
