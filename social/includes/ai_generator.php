<?php
/**
 * Generazione testi social + dati editoriali per i tre template grafici Formula Paddock.
 */

function callAI(string $systemPrompt, string $userPrompt, array $config): string
{
    $provider = $config['ai_provider'] ?? 'anthropic';
    if ($provider === 'gemini') {
        return callGemini($systemPrompt, $userPrompt, $config);
    }
    return callClaude($systemPrompt, $userPrompt, $config);
}

function callGemini(string $systemPrompt, string $userPrompt, array $config): string
{
    $models = array_merge([$config['gemini_model']], $config['gemini_fallback_models'] ?? []);
    $errors = [];
    foreach ($models as $model) {
        try {
            return callGeminiModel($model, $systemPrompt, $userPrompt, $config);
        } catch (Exception $e) {
            $errors[] = "[$model] " . $e->getMessage();
        }
    }
    throw new Exception("Tutti i modelli Gemini hanno fallito:\n" . implode("\n", $errors));
}

function callGeminiModel(string $model, string $systemPrompt, string $userPrompt, array $config): string
{
    $url = rtrim($config['gemini_api_url'], '/') . '/' . $model . ':generateContent?key=' . urlencode($config['gemini_api_key']);
    $payload = [
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
        'generationConfig' => ['maxOutputTokens' => 4000, 'temperature' => 0.6],
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $err) throw new Exception("Errore chiamata Gemini API: $err");
    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['error']['message'] ?? $response;
        throw new Exception("Errore Gemini API ($httpCode): $msg");
    }
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($text === null) throw new Exception('Risposta inattesa da Gemini API: ' . $response);
    return $text;
}

function callClaude(string $systemPrompt, string $userPrompt, array $config): string
{
    $payload = [
        'model' => $config['anthropic_model'],
        'max_tokens' => 2600,
        'system' => $systemPrompt,
        'messages' => [['role' => 'user', 'content' => $userPrompt]],
    ];
    $ch = curl_init($config['anthropic_api_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $config['anthropic_api_key'],
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $err) throw new Exception("Errore chiamata Claude API: $err");
    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['error']['message'] ?? $response;
        throw new Exception("Errore Claude API ($httpCode): $msg");
    }
    if (!isset($data['content'][0]['text'])) throw new Exception('Risposta inattesa da Claude API: ' . $response);
    return $data['content'][0]['text'];
}

function generateSocialContent(string $sourceText, string $title, array $config): array
{
    $systemPrompt = <<<SYS
Sei il social media editor di Formula Paddock, testata italiana dedicata alla Formula 1.
Trasforma il testo fornito in copy social e nei dati di una infografica editoriale premium.

Devi scegliere UNO dei tre template grafici:
- "breaking": notizie, mercato, annunci, regolamenti, comunicati, aggiornamenti.
- "race": risultati di gara/qualifica, vittorie, podi, classifiche e sessioni sportive.
- "analysis": analisi tecnica, strategia, approfondimenti, confronti e spiegazioni.

Regole per l'infografica:
- Titolo massimo 8 parole, forte ma fedele alla notizia.
- Sottotitolo massimo 16 parole.
- Tre dati chiave molto brevi e informativi.
- Non inventare numeri, risultati, piloti, team o fatti non presenti nel testo sorgente.
- Se un dato non e' disponibile, usa una sintesi qualitativa vera e utile, senza inventare cifre.
- Per template race, i tre dati possono essere podio/risultato, distacchi, giri, pole, giro veloce o altro dato realmente disponibile.
- Per template analysis, usa tre chiavi esplicative.
- Per template breaking, usa tre punti essenziali della notizia.

Rispondi SOLO con JSON valido, senza markdown, con esattamente questa struttura:
{
  "facebook": "Post Facebook coinvolgente, 2-4 frasi, 2-3 hashtag F1 alla fine",
  "twitter": "Post X massimo 280 caratteri, incisivo, 1-2 hashtag",
  "twitter_modificato": "Variante del post X, massimo 280 caratteri",
  "linkedin": "Post LinkedIn professionale/analitico, 3-5 frasi",
  "categoria": "Etichetta breve della notizia",
  "infografica_template": "breaking oppure race oppure analysis",
  "infografica_titolo": "Titolo grafico massimo 8 parole",
  "infografica_sottotitolo": "Sottotitolo grafico massimo 16 parole",
  "infografica_label_1": "Etichetta 1 massimo 3 parole",
  "infografica_dato_1": "Dato chiave 1 massimo 10 parole",
  "infografica_label_2": "Etichetta 2 massimo 3 parole",
  "infografica_dato_2": "Dato chiave 2 massimo 10 parole",
  "infografica_label_3": "Etichetta 3 massimo 3 parole",
  "infografica_dato_3": "Dato chiave 3 massimo 10 parole",
  "reel_script": "Testo rapido per Reel, massimo 3 frasi brevi"
}
SYS;

    $userPrompt = "TITOLO: " . $title . "\n\nTESTO ORIGINALE:\n" . $sourceText;
    $raw = trim(callAI($systemPrompt, $userPrompt, $config));
    $raw = preg_replace('/^```json\s*|\s*```$/m', '', $raw);
    $raw = trim($raw);
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new Exception("Impossibile interpretare la risposta JSON AI: $raw");

    $defaults = [
        'facebook' => '', 'twitter' => '', 'twitter_modificato' => '', 'linkedin' => '',
        'categoria' => 'F1 News', 'infografica_template' => 'breaking',
        'infografica_titolo' => $title, 'infografica_sottotitolo' => '',
        'infografica_label_1' => 'PUNTO 1', 'infografica_dato_1' => '',
        'infografica_label_2' => 'PUNTO 2', 'infografica_dato_2' => '',
        'infografica_label_3' => 'PUNTO 3', 'infografica_dato_3' => '',
        'reel_script' => '',
    ];
    $result = array_merge($defaults, $json);
    $template = strtolower(trim((string)$result['infografica_template']));
    $result['infografica_template'] = in_array($template, ['breaking', 'race', 'analysis'], true) ? $template : 'breaking';
    foreach (['twitter', 'twitter_modificato'] as $key) {
        if (mb_strlen((string)$result[$key]) > 280) $result[$key] = mb_substr((string)$result[$key], 0, 277) . '...';
    }
    return $result;
}
