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

$context  = $articleTitle ? "TITOLO ARTICOLO: {$articleTitle}\n\n" : '';
$context .= $articleHtml  ? "CONTENUTO ARTICOLO (HTML):\n{$articleHtml}" : '';
$extraInstructions = $customInstructions ? "\n\nISTRUZIONI AGGIUNTIVE:\n{$customInstructions}" : '';

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

Estrai SOLO dati presenti nell'articolo. Non inventare numeri.

STEP 2 - DESIGN OBBLIGATORIO:
- Larghezza FISSA: 800px, altezza variabile (min 400px)
- Sfondo: #0f0f0f | Card/righe: #1a1a1a alternato #141414
- Accent rosso F1: #e10600 | Testo: #ffffff | Secondario: #999999 | Bordi: #2a2a2a
- Posizioni: P1 oro #ffd700, P2 argento #c0c0c0, P3 bronzo #cd7f32, P4+ bianco #ffffff (testo nero)
- Font: system-ui, -apple-system, sans-serif | Border-radius: 8px (card), 4px (badge)
- Header con banda rossa #e10600 e titolo | Footer con "formulapaddock.it" a destra

STEP 3 - OUTPUT (CRITICO):
- SOLO HTML puro self-contained con <style> interno
- NO JavaScript, NO CDN, NO font esterni
- NO tag html/head/body/meta/title
- Inizia direttamente con il <div> contenitore
- NO markdown o blocchi di codice

CONTENUTO DA ANALIZZARE:
{$context}{$extraInstructions}
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
                "✅ Infografica generata\nTimestamp: " . date('Y-m-d H:i:s') . "\nModel: {$modelName}\n\n", FILE_APPEND);
            break;
        }
    } else {
        $lastError = ['model' => $modelName, 'status' => $status, 'response' => $res['json'] ?? []];
        file_put_contents(__DIR__ . '/../errori.txt',
            "⚠️ Infografica fallita\nTimestamp: " . date('Y-m-d H:i:s') . "\nModel: {$modelName}\nStatus: {$status}\n" .
            json_encode($res['json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);
    }
}

if ($text === null || trim($text) === '') {
    jsonResponse(['error' => 'Generazione fallita', 'attempted_models' => $attemptedModels, 'last_error' => $lastError], 500);
}

$text = preg_replace('/^```(?:html)?\s*/i', '', trim($text)) ?? $text;
$text = preg_replace('/\s*```\s*$/i', '', $text) ?? $text;
$text = stripPageMetaTags($text);

jsonResponse(['success' => true, 'model_used' => $successfulModel, 'infografica_html' => trim($text)]);
