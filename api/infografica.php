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

$articleHtml        = trim((string)($payload['article_html'] ?? ''));
$articleTitle       = trim((string)($payload['article_title'] ?? ''));
$customInstructions = trim((string)($payload['custom_instructions'] ?? ''));

if ($articleHtml === '' && $articleTitle === '') {
    jsonResponse(['message' => 'article_html o article_title obbligatorio'], 400);
}

// Per le classifiche la fonte dati deve essere api-classifica.php,
// non il testo dell'articolo. L'API restituisce i dati reali già
// utilizzati dal modulo classifica del progetto.
function loadClassificaData(): array
{
    $url = rtrim((string)($_SERVER['REQUEST_SCHEME'] ?? 'https'), ':/') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/seo/api-classifica.php';
    if (($scheme = parse_url($url, PHP_URL_SCHEME)) === null || empty($_SERVER['HTTP_HOST'])) {
        $url = 'https://www.formulapaddock.it/seo/api-classifica.php';
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n"
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        // Fallback assoluto all'endpoint pubblico FormulaPaddock.
        $response = @file_get_contents('https://www.formulapaddock.it/seo/api-classifica.php', false, $context);
    }

    if ($response === false) {
        throw new RuntimeException('Impossibile recuperare la classifica da api-classifica.php');
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['success']) || !isset($data['data']) || !is_array($data['data'])) {
        throw new RuntimeException('api-classifica.php non ha restituito una classifica valida');
    }

    return $data;
}

$context  = $articleTitle ? "TITOLO ARTICOLO: {$articleTitle}\n\n" : '';
$context .= $articleHtml  ? "CONTENUTO ARTICOLO (HTML):\n{$articleHtml}" : '';
$extraInstructions = $customInstructions ? "\n\nISTRUZIONI AGGIUNTIVE:\n{$customInstructions}" : '';

// Determina se l'utente/articolo sta chiedendo una classifica.
$classificationRequested = (bool)preg_match('/\b(classifica|classifiche|campionato|mondiale piloti|mondiale costruttori|punti piloti|punti costruttori|standings)\b/i', $articleTitle . "\n" . $articleHtml . "\n" . $customInstructions);

$classificationContext = '';
if ($classificationRequested) {
    try {
        $standings = loadClassificaData();
        $classificationContext = "\n\nDATI CLASSIFICA UFFICIALI DA api-classifica.php (FONTE PRIORITARIA):\n" .
            json_encode($standings['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .
            "\n\nUsa questi dati per nomi, posizioni e valori numerici della classifica. Non ricavare questi dati dall'articolo e non inventare valori.\n";
    } catch (Throwable $e) {
        jsonResponse([
            'error' => 'Impossibile recuperare i dati reali della classifica',
            'details' => $e->getMessage()
        ], 502);
    }
}

$prompt = <<<PROMPT
Sei un designer specializzato in infografiche sportive per Formula 1.
Analizza il contenuto dell'articolo e genera un'infografica HTML/CSS completamente autonoma e visivamente professionale.

STEP 1 - ANALISI: scegli il tipo di infografica piu adatto:
- RISULTATO GARA: posizioni, piloti, team, tempo/distacco, punti, giro veloce
- GRIGLIA QUALIFICHE: Q1/Q2/Q3, piloti, team, tempi
- CLASSIFICA: top piloti o costruttori con punti
- STRATEGIA GOMME: mescole usate, pit stop, stint in giri
- SCHEDA CIRCUITO: lunghezza, curve, record, DRS zone
- CARD NOTIZIA: dati/fatti chiave strutturati in card visiva

Per una CLASSIFICA, quando sono presenti DATI CLASSIFICA UFFICIALI devi usare quelli come fonte numerica primaria. L'articolo serve soltanto per determinare il contesto/titolo della grafica.
Non inventare numeri e non sostituire i dati ufficiali con numeri trovati nell'articolo.

STEP 2 - DESIGN OBBLIGATORIO:
- Larghezza FISSA: 800px, altezza variabile (min 400px)
- Sfondo: #0f0f0f | Card/righe: #1a1a1a alternato #141414
- Accent rosso F1: #e10600 | Testo: #ffffff | Secondario: #999999 | Bordi: #2a2a2a
- Posizioni: P1 oro #ffd700, P2 argento #c0c0c0, P3 bronzo #cd7f32, P4+ bianco #ffffff
- Font: system-ui, -apple-system, sans-serif | Border-radius: 8px (card), 4px (badge)
- Header con banda rossa #e10600 e titolo | Footer con "formulapaddock.it" a destra
- Per una classifica mostra una tabella/serie di righe ordinata per posizione, con almeno posizione, pilota e punti quando disponibili.

STEP 3 - OUTPUT (CRITICO):
- SOLO HTML puro self-contained con <style> interno
- NO JavaScript, NO CDN, NO font esterni
- NO tag html/head/body/meta/title
- Inizia direttamente con il <div> contenitore
- NO markdown o blocchi di codice

CONTENUTO DA ANALIZZARE:
{$context}{$classificationContext}{$extraInstructions}
PROMPT;

$apiKey = trim((string)($appConfig['gemini_api_key'] ?? ''));
if ($apiKey === '') {
    jsonResponse(['error' => 'API key Gemini non configurata'], 400);
}

$geminiModels = $appConfig['gemini_models'] ?? [
    'gemini-2.0-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
    'gemini-1.5-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
];

$request = [
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.35, 'topP' => 0.9, 'maxOutputTokens' => 8192],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
    ],
];

$text = $successfulModel = $lastError = null;
$attemptedModels = [];

foreach ($geminiModels as $modelName => $modelUrl) {
    $attemptedModels[] = $modelName;
    $url = $modelUrl . '?key=' . urlencode($apiKey);
    $res = postJson($url, $request, [], 120);
    $status = (int)($res['status'] ?? 0);

    if (!empty($res['ok']) && $status >= 200 && $status < 400) {
        $generated = '';
        foreach (($res['json']['candidates'] ?? []) as $candidate) {
            foreach (($candidate['content']['parts'] ?? []) as $part) {
                if (isset($part['text'])) $generated .= (string)$part['text'];
            }
        }
        if (trim($generated) !== '') {
            $text = $generated;
            $successfulModel = $modelName;
            file_put_contents(__DIR__ . '/../errori.txt',
                "Infografica generata\nTimestamp: " . date('Y-m-d H:i:s') . "\nModel: {$modelName}\nClassifica fonte: " . ($classificationRequested ? 'api-classifica.php' : 'n/a') . "\n\n", FILE_APPEND);
            break;
        }
    } else {
        $lastError = ['model' => $modelName, 'status' => $status, 'response' => $res['json'] ?? []];
        file_put_contents(__DIR__ . '/../errori.txt',
            "Infografica fallita\nTimestamp: " . date('Y-m-d H:i:s') . "\nModel: {$modelName}\nStatus: {$status}\n" .
            json_encode($res['json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);
    }
}

if ($text === null || trim($text) === '') {
    jsonResponse(['error' => 'Generazione fallita', 'attempted_models' => $attemptedModels, 'last_error' => $lastError], 500);
}

$text = preg_replace('/^```(?:html)?\s*/i', '', trim($text)) ?? $text;
$text = preg_replace('/\s*```\s*$/i', '', $text) ?? $text;
$text = stripPageMetaTags($text);

jsonResponse([
    'success' => true,
    'model_used' => $successfulModel,
    'source' => $classificationRequested ? 'api-classifica.php' : 'article',
    'infografica_html' => trim($text)
]);
