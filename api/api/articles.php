<?php

require __DIR__ . '/bootstrap.php';

$pathsToTry = [
    __DIR__ . '/../../storage/articles/articles.json',
    __DIR__ . '/../storage/articles/articles.json',
    __DIR__ . '/storage/articles/articles.json',
];

$storageFile = '';
foreach ($pathsToTry as $p) {
    if (file_exists($p) && filesize($p) > 0) {
        $storageFile = $p;
        break;
    }
}
if ($storageFile === '') {
    $storageFile = __DIR__ . '/../../storage/articles/articles.json';
}
$storageDir = dirname($storageFile);

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0777, true);
}

if (!is_file($storageFile)) {
    @file_put_contents($storageFile, json_encode(['articles' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function readArticles(string $storageFile): array
{
    if (!is_file($storageFile)) {
        return [];
    }

    $raw = @file_get_contents($storageFile);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['articles']) || !is_array($decoded['articles'])) {
        return [];
    }

    return $decoded['articles'];
}

function writeArticles(string $storageFile, array $articles): void
{
    $fp = @fopen($storageFile, 'c+');
    if ($fp === false) {
        throw new RuntimeException('Impossibile aprire archivio articoli');
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Impossibile bloccare archivio in scrittura');
        }

        $payload = json_encode(['articles' => array_values($articles)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($payload === false) {
            throw new RuntimeException('Impossibile serializzare articoli');
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $payload);
        fflush($fp);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

if ($action === 'list') {
    try {
        $articles = readArticles($storageFile);
        usort($articles, static fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
        jsonResponse(['articles' => $articles]);
    } catch (Throwable $e) {
        jsonResponse(['message' => $e->getMessage()], 500);
    }
}

if ($action === 'get') {
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') {
        jsonResponse(['message' => 'id mancante'], 400);
    }

    try {
        $articles = readArticles($storageFile);
        foreach ($articles as $article) {
            if (($article['id'] ?? '') === $id) {
                jsonResponse(['article' => $article]);
            }
        }
        jsonResponse(['message' => 'Articolo non trovato'], 404);
    } catch (Throwable $e) {
        jsonResponse(['message' => $e->getMessage()], 500);
    }
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(['message' => 'Payload non valido'], 400);
    }

    $id = trim((string)($payload['id'] ?? ''));
    $title = trim((string)($payload['title'] ?? ''));
    $content = trim((string)($payload['content'] ?? ''));
    $category = trim((string)($payload['category'] ?? ''));
    $h2ImageMap = is_array($payload['h2_image_map'] ?? null) ? $payload['h2_image_map'] : [];
    $wpUrlMap = is_array($payload['wp_url_map'] ?? null) ? $payload['wp_url_map'] : [];
    $customCharts = is_array($payload['custom_charts'] ?? null) ? $payload['custom_charts'] : [];
    $paragraphChartMap = is_array($payload['paragraph_chart_map'] ?? null) ? $payload['paragraph_chart_map'] : [];
    $autoPlaceMedia = !empty($payload['auto_place_media']);

    if ($title === '' || $content === '') {
        jsonResponse(['message' => 'title e content sono obbligatori'], 400);
    }

    try {
        $articles = readArticles($storageFile);

        $now = date('c');
        $saved = null;
        $updated = false;

        foreach ($articles as &$article) {
            if ($id !== '' && ($article['id'] ?? '') === $id) {
                $article['title'] = $title;
                $article['content'] = $content;
                $article['category'] = $category;
                $article['h2_image_map'] = $h2ImageMap;
                $article['wp_url_map'] = $wpUrlMap;
                $article['custom_charts'] = $customCharts;
                $article['paragraph_chart_map'] = $paragraphChartMap;
                $article['auto_place_media'] = $autoPlaceMedia;
                $article['updated_at'] = $now;
                $saved = $article;
                $updated = true;
                break;
            }
        }
        unset($article);

        if (!$updated) {
            $new = [
                'id' => $id !== '' ? $id : bin2hex(random_bytes(8)),
                'title' => $title,
                'content' => $content,
                'category' => $category,
                'h2_image_map' => $h2ImageMap,
                'wp_url_map' => $wpUrlMap,
                'custom_charts' => $customCharts,
                'paragraph_chart_map' => $paragraphChartMap,
                'auto_place_media' => $autoPlaceMedia,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $articles[] = $new;
            $saved = $new;
        }

        $unique = [];
        foreach ($articles as $a) {
            $aid = (string)($a['id'] ?? '');
            if ($aid === '') continue;
            $unique[$aid] = $a;
        }

        writeArticles($storageFile, array_values($unique));
        jsonResponse(['ok' => true, 'article' => $saved]);
    } catch (Throwable $e) {
        jsonResponse(['message' => $e->getMessage()], 500);
    }
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(['message' => 'Payload non valido'], 400);
    }

    $id = trim((string)($payload['id'] ?? ''));
    if ($id === '') {
        jsonResponse(['message' => 'id mancante'], 400);
    }

    try {
        $articles = readArticles($storageFile);
        $before = count($articles);
        $articles = array_values(array_filter($articles, static fn($a) => (string)($a['id'] ?? '') !== $id));

        if (count($articles) === $before) {
            jsonResponse(['message' => 'Articolo non trovato'], 404);
        }

        writeArticles($storageFile, $articles);
        jsonResponse(['ok' => true]);
    } catch (Throwable $e) {
        jsonResponse(['message' => $e->getMessage()], 500);
    }
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        writeArticles($storageFile, []);
        jsonResponse(['ok' => true]);
    } catch (Throwable $e) {
        jsonResponse(['message' => $e->getMessage()], 500);
    }
}

jsonResponse(['message' => 'Azione non valida'], 400);
