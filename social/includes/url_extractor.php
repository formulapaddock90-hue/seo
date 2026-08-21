<?php
/**
 * Estrazione del testo principale da una pagina web.
 */

/**
 * Scarica una pagina e ne estrae il testo "leggibile" (titolo + paragrafi).
 *
 * @param string $url
 * @return array{title:string, text:string, source_url:string}
 * @throws Exception
 */
function extractTextFromUrl(string $url): array
{
    $url = resolveInputUrl(trim($url));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SocialContentBot/1.0)',
    ]);
    $html = curl_exec($ch);
    $err  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false || $err) {
        throw new Exception("Errore durante il download dell'URL: $err");
    }
    if ($httpCode >= 400) {
        throw new Exception("La pagina ha risposto con codice HTTP $httpCode");
    }

    // Forza la codifica corretta (molte pagine italiane sono in UTF-8, ma per sicurezza)
    $encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // ---- Titolo ----
    $title = '';
    $titleNodes = $xpath->query('//meta[@property="og:title"]/@content');
    if ($titleNodes->length > 0) {
        $title = trim($titleNodes->item(0)->nodeValue);
    }
    if ($title === '') {
        $h1 = $dom->getElementsByTagName('h1');
        if ($h1->length > 0) {
            $title = trim($h1->item(0)->textContent);
        }
    }
    if ($title === '') {
        $titleTag = $dom->getElementsByTagName('title');
        if ($titleTag->length > 0) {
            $title = trim($titleTag->item(0)->textContent);
        }
    }

    // ---- Immagine in evidenza / OG Image ----
    $imageUrl = '';
    $imgNodes = $xpath->query('//meta[@property="og:image"]/@content | //meta[@name="twitter:image"]/@content | //meta[@property="twitter:image"]/@content | //link[@rel="image_src"]/@href');
    if ($imgNodes->length > 0) {
        $imageUrl = trim($imgNodes->item(0)->nodeValue);
    }
    if ($imageUrl === '') {
        $articleImgs = $xpath->query('//article//img/@src | //main//img/@src | //img[contains(@class, "wp-post-image")]/@src | //img[contains(@class, "featured")]/@src');
        if ($articleImgs->length > 0) {
            $imageUrl = trim($articleImgs->item(0)->nodeValue);
        }
    }
    if ($imageUrl !== '' && str_starts_with($imageUrl, '//')) {
        $imageUrl = 'https:' . $imageUrl;
    }

    // ---- Rimuovi elementi inutili (script, style, nav, footer, header, form, aside) ----
    $tagsToRemove = ['script', 'style', 'nav', 'footer', 'header', 'form', 'aside', 'noscript', 'iframe'];
    foreach ($tagsToRemove as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            $node->parentNode->removeChild($node);
        }
    }

    // ---- Cerca un contenitore principale (article, main, oppure il div con piu' testo) ----
    $candidates = [];
    foreach (['article', 'main'] as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        foreach ($nodes as $node) {
            $candidates[] = $node;
        }
    }

    $bestNode = null;
    $bestLength = 0;

    if (!empty($candidates)) {
        foreach ($candidates as $node) {
            $len = mb_strlen(trim($node->textContent));
            if ($len > $bestLength) {
                $bestLength = $len;
                $bestNode = $node;
            }
        }
    }

    // Se non troviamo article/main, prendiamo tutti i <p> della pagina
    if ($bestNode === null) {
        $paragraphs = $dom->getElementsByTagName('p');
        $text = '';
        foreach ($paragraphs as $p) {
            $t = trim($p->textContent);
            if (mb_strlen($t) > 30) {
                $text .= $t . "\n\n";
            }
        }
    } else {
        $paragraphs = $bestNode->getElementsByTagName('p');
        $text = '';
        foreach ($paragraphs as $p) {
            $t = trim($p->textContent);
            if (mb_strlen($t) > 20) {
                $text .= $t . "\n\n";
            }
        }
        // fallback: se non c'erano <p>, usa tutto il testo del nodo
        if (trim($text) === '') {
            $text = trim($bestNode->textContent);
        }
    }

    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    if ($text === '') {
        throw new Exception('Non e\' stato possibile estrarre testo significativo dalla pagina.');
    }

    // Limita la lunghezza per non sovraccaricare il prompt AI
    if (mb_strlen($text) > 12000) {
        $text = mb_substr($text, 0, 12000);
    }

    return [
        'title'      => $title !== '' ? $title : 'Articolo',
        'text'       => $text,
        'source_url' => $url,
        'image_url'  => $imageUrl,
    ];
}

/**
 * Se l'URL contiene un parametro ?url=... (wrapper/redirect), restituisce
 * l'URL originario contenuto nel parametro; altrimenti restituisce l'URL cosi' com'e'.
 */
function resolveInputUrl(string $url): string
{
    $query = parse_url($url, PHP_URL_QUERY);
    if ($query) {
        parse_str($query, $params);
        if (!empty($params['url']) && isValidUrl($params['url'])) {
            return trim($params['url']);
        }
    }
    return $url;
}

/**
 * Determina se la stringa fornita dall'utente e' un URL valido.
 */
function isValidUrl(string $input): bool
{
    $input = trim($input);
    return (bool) filter_var($input, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $input);
}
