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

$trendsMain = trim((string)($payload['trends_main_keyword'] ?? ''));
$trendsKeywords = trim((string)($payload['trends_keywords'] ?? ''));

$mainKeyword = trim((string)($payload['main_keyword'] ?? ''));
$related = trim((string)($payload['related_keywords'] ?? ''));
$longTail = trim((string)($payload['long_tail'] ?? ''));
$rawText = trim((string)($payload['raw_text'] ?? ''));
$images = trim((string)($payload['images'] ?? ''));
$categoryHint = trim((string)($payload['category_name'] ?? ''));
$campionato = trim((string)($payload['campionato'] ?? ''));
$circuito = trim((string)($payload['circuito'] ?? ''));
$isLiveSession = !empty($payload['is_live_session']);
$liveSessionName = trim((string)($payload['live_session_name'] ?? ''));
$internalLinks = $payload['internal_links'] ?? [];

if (!is_array($internalLinks)) {
    $internalLinks = [];
}
$internalLinks = array_values(array_filter(array_map(static function ($v) {
    return is_string($v) ? trim($v) : '';
}, $internalLinks)));
$internalLinksText = implode("\n", $internalLinks);

if ($rawText === '') {
    jsonResponse(['message' => 'raw_text obbligatorio'], 400);
}

$liveSessionText = $isLiveSession ? "Sì, Sessione: {$liveSessionName}" : "No";

$prompt = "Sei un SEO copywriter italiano specializzato in Formula 1 e Motorsport. "
    . "Scegli tu la keyword principale più adatta a questo articolo e crea contenuto SEO in HTML pronto per WordPress. "
    . "Regole obbligatorie:\n"
    . "- Usa un solo H1 (titolo articolo) deciso da te in base al testo;\n"
    . "- Usa H2/H3 logici; paragrafi brevi; inserisci liste quando utile; niente keyword stuffing.\n"
    . "- I link interni devono essere scelti da te in base alla pertinenza e inseriti dentro ai paragrafi del testo, mai in una sezione finale separata.\n"
    . "- Inserisci tutti i link interni utili nel corpo dell'articolo, distribuiti in modo naturale nel contenuto. Usa anche link esterni autorevoli quando pertinenti.\n\n"
    . "REGOLA INFOGRAFICA OBBLIGATORIA:\n"
    . "Alla fine dell'articolo devi SEMPRE generare una sezione di infografica HTML/CSS interamente autonoma, inserita alla fine dell'HTML e racchiusa in `<section class=\"infografica-articolo\" style=\"background:#0f0f0f; border:1px solid #2a2a2a; border-radius:8px; padding:20px; color:#fff; font-family:system-ui, -apple-system, sans-serif; margin-top:25px;\">`.\n"
    . "L'infografica deve presentare i dati salienti dell'articolo (es. classifica, tempi di sessione, meteo o scheda circuito) in modo visivo, moderno e professionale:\n"
    . "- Titolo dell'infografica con una banda rossa (#e10600);\n"
    . "- Struttura a griglia o card pulite (es. con sfondo #1a1a1a o bordi #2a2a2a);\n"
    . "- Posizioni evidenziate (P1: #ffd700, P2: #c0c0c0, P3: #cd7f32);\n"
    . "- Testi corti e di forte impatto visivo.\n\n"
    . "Output solo HTML pulito (senza markdown o blocchi ```html) pronto per essere inserito nel body di WordPress:\n"
    . "NON includere tag <meta>, <title>, <html>, <head> o <body>; inizia direttamente dall'<h1>.\n"
    . "Non usare etichette come TITLE:, H1:, H2: nel testo finale.\n\n"
    . "CAMPIONATO: {$campionato}\n"
    . "CIRCUITO SELEZIONATO: {$circuito}\n"
    . "SESSIONE LIVE: {$liveSessionText}\n"
    . "TRENDS KEYWORD PRINCIPALE: {$trendsMain}\n"
    . "TRENDS KEYWORDS CORRELATE: {$trendsKeywords}\n"
    . "KEYWORD MANUALE (se presente): {$mainKeyword}\n"
    . "KEYWORD CORRELATE: {$related}\n"
    . "LONG TAIL: {$longTail}\n"
    . "CATEGORIA SUGGERITA: {$categoryHint}\n"
    . "IMMAGINI DISPONIBILI/SELEZIONATE (in cartelle): {$images}\n"
    . "LINK INTERNI DISPONIBILI:\n{$internalLinksText}\n\n"
    . "TESTO GREZZO:\n{$rawText}";

$apiKey = trim((string)($appConfig['gemini_api_key'] ?? ''));
if ($apiKey === '') {
    jsonResponse(['error' => 'API key non configurata'], 400);
}

// Ottieni la lista dei modelli disponibili da config.php
$geminiModels = $appConfig['gemini_models'] ?? [];
if (!is_array($geminiModels) || empty($geminiModels)) {
    $geminiModels = [
        'gemini-3.6-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
        'gemini-2.0-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
        'gemini-1.5-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
        'gemini-1.5-pro' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent',
        'gemini-2.0-flash-lite' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent',
    ];
}

// Prepara la richiesta base
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
    'safetySettings' => [
        [
            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
            'threshold' => 'BLOCK_NONE',
        ],
        [
            'category' => 'HARM_CATEGORY_HATE_SPEECH',
            'threshold' => 'BLOCK_NONE',
        ],
        [
            'category' => 'HARM_CATEGORY_HARASSMENT',
            'threshold' => 'BLOCK_NONE',
        ],
        [
            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
            'threshold' => 'BLOCK_NONE',
        ],
    ],
];

// Try-catch per fallback tra modelli
$text = '';
$lastError = null;
$successfulModel = null;
$attemptedModels = [];

foreach ($geminiModels as $modelName => $modelUrl) {
    $attemptedModels[] = $modelName;
    
    $url = $modelUrl . '?key=' . urlencode($apiKey);
    $res = postJson($url, $request);
    
    $status = (int)($res['status'] ?? 0);
    if (!empty($res['ok']) && $status >= 200 && $status < 400) {
        // Successo!
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
            // Log del successo
            file_put_contents(__DIR__ . '/../errori.txt',
                "✅ Gemini API SUCCESS\n" .
                "Timestamp: " . date('Y-m-d H:i:s') . "\n" .
                "Model: " . $modelName . "\n" .
                "Status: " . ($res['status'] ?? '?') . "\n\n",
                FILE_APPEND
            );
            break;
        }
    } else {
        // Errore - salva i dettagli
        $lastError = [
            'model' => $modelName,
            'status' => $status > 0 ? $status : '?',
            'url' => $url,
            'response' => $res['json'] ?? [],
            'transport_error' => $res['error'] ?? '',
        ];
        
        // Log del tentativo fallito
        file_put_contents(__DIR__ . '/../errori.txt',
            "⚠️ Gemini Model Attempt Failed\n" .
            "Timestamp: " . date('Y-m-d H:i:s') . "\n" .
            "Model: " . $modelName . "\n" .
            "Status: " . ($res['status'] ?? '?') . "\n" .
            "Response: " . json_encode($res['json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n",
            FILE_APPEND
        );
    }
}

// Se nessun modello ha funzionato
if (trim($text) === '') {
    $errorMsg = 'Tutti i modelli hanno fallito';
    $errorDetails = '';
    $responseStatus = 500;

    // Analizza l'ultimo errore per dare info più utili
    if ($lastError) {
        $status = (int)($lastError['status'] ?? 500);
        $errorCode = $lastError['response']['error']['code'] ?? null;
        $errorMessage = $lastError['response']['error']['message'] ?? '';
        $responseStatus = $status > 0 ? $status : 500;

        if ($errorCode === 429) {
            $errorMsg = 'Quota API esaurita - Riprovare più tardi';
            $errorDetails = $errorMessage;
            $responseStatus = 429;
        } elseif ($status >= 500) {
            $errorMsg = 'Servizi Gemini temporaneamente non disponibili';
            $errorDetails = $errorMessage;
            $responseStatus = 503;
        } elseif ($status === 404) {
            $errorMsg = 'Modello non disponibile';
            $errorDetails = $errorMessage;
            $responseStatus = 404;
        }
    }

    jsonResponse([
        'message' => $errorMsg,
        'error' => $errorMsg,
        'details' => $errorDetails,
        'attempted_models' => $attemptedModels,
    ], $responseStatus);
}

$text = stripPageMetaTags($text);

jsonResponse([
    'source' => 'gemini',
    'model_used' => $successfulModel,
    'draft_text' => $text,
    'draft_html' => $text,
]);
