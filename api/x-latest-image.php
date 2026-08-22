<?php

require_once __DIR__ . '/bootstrap.php';

$bearerToken = trim((string) ($appConfig['x_bearer_token'] ?? ''));
$teams = [
    'mclaren' => ['label' => 'McLaren', 'username' => 'McLarenF1'],
    'ferrari' => ['label' => 'Ferrari', 'username' => 'ScuderiaFerrari'],
    'mercedes' => ['label' => 'Mercedes', 'username' => 'MercedesAMGF1'],
    'red-bull' => ['label' => 'Red Bull Racing', 'username' => 'redbullracing'],
    'aston-martin' => ['label' => 'Aston Martin', 'username' => 'AstonMartinF1'],
    'alpine' => ['label' => 'Alpine', 'username' => 'AlpineF1Team'],
    'williams' => ['label' => 'Williams', 'username' => 'WilliamsF1'],
    'haas' => ['label' => 'Haas', 'username' => 'HaasF1Team'],
    'racing-bulls' => ['label' => 'Racing Bulls', 'username' => 'visacashapprb'],
    'audi' => ['label' => 'Audi Revolut F1 Team', 'username' => 'audif1_'],
    'cadillac' => ['label' => 'Cadillac Formula 1 Team', 'username' => 'Cadillac_F1'],
];
$teamKey = strtolower(trim((string) ($_GET['team'] ?? '')));

if (!isset($teams[$teamKey])) {
    jsonResponse([
        'ok' => false,
        'found' => false,
        'error' => 'Team non valido o non selezionato.',
    ], 400);
}

$team = $teams[$teamKey];
$username = $team['username'];

function xTeamPayload(array $team, string $teamKey): array
{
    return [
        'team' => $teamKey,
        'team_label' => $team['label'],
        'username' => $team['username'],
    ];
}

function latestXImageFromFxTwitter(string $username): array
{
    $url = 'https://api.fxtwitter.com/2/profile/' . rawurlencode($username) . '/media?count=10';
    $response = httpRequest($url, 'GET', [
        'Accept' => 'application/json',
        'User-Agent' => 'FormulaPaddockContentHub/1.0',
    ], null, 25);

    if (($response['status'] ?? 0) !== 200) {
        return ['error' => 'Servizio immagini X non raggiungibile (HTTP ' . (int) ($response['status'] ?? 0) . ').'];
    }

    $payload = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($payload) || (int) ($payload['code'] ?? 0) !== 200) {
        return ['error' => 'Il servizio immagini X ha restituito dati non validi.'];
    }

    foreach (($payload['results'] ?? []) as $post) {
        if (!is_array($post)) {
            continue;
        }

        $photos = $post['media']['photos'] ?? [];
        if (!is_array($photos) || $photos === []) {
            $photos = array_values(array_filter(
                is_array($post['media']['all'] ?? null) ? $post['media']['all'] : [],
                static fn($media): bool => is_array($media) && ($media['type'] ?? '') === 'photo'
            ));
        }

        $imageUrl = (string) ($photos[0]['url'] ?? '');
        if ($imageUrl !== '') {
            return [
                'image_url' => $imageUrl,
                'post_url' => (string) ($post['url'] ?? ''),
            ];
        }
    }

    return ['error' => 'Il profilo non contiene foto recenti.'];
}

function latestXImageFromPublicTimeline(string $username): array
{
    $timelineUrl = 'https://syndication.twitter.com/srv/timeline-profile/screen-name/' . rawurlencode($username);
    $requestHeaders = [
        'Accept' => 'text/html,application/xhtml+xml',
        'Accept-Language' => 'it-IT,it;q=0.9,en;q=0.8',
        'Referer' => 'https://platform.twitter.com/',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/151 Safari/537.36',
    ];
    $response = [];

    // Il servizio syndication può rispondere in modo intermittente dagli IP
    // degli hosting condivisi: ritenta una volta con un query param innocuo.
    foreach ([$timelineUrl . '?dnt=true', $timelineUrl] as $url) {
        $response = httpRequest($url, 'GET', $requestHeaders, null, 45);
        if (($response['status'] ?? 0) === 200 && (string) ($response['body'] ?? '') !== '') {
            break;
        }
    }

    if (($response['status'] ?? 0) !== 200) {
        return ['error' => 'Feed X non raggiungibile dal server (HTTP ' . (int) ($response['status'] ?? 0) . ').'];
    }

    if (!preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', (string) ($response['body'] ?? ''), $match)) {
        return ['error' => 'X ha restituito un feed senza dati utilizzabili.'];
    }

    // __NEXT_DATA__ contiene già JSON valido. Decodificare prima le entità HTML
    // può trasformare &quot; presenti nei tweet in virgolette non sottoposte a
    // escaping e rendere il JSON invalido per alcuni profili.
    $payload = json_decode(trim($match[1]), true);
    if (!is_array($payload)) {
        $payload = json_decode(html_entity_decode($match[1], ENT_NOQUOTES | ENT_HTML5, 'UTF-8'), true);
    }
    if (!is_array($payload)) {
        return ['error' => 'Il feed X ricevuto non è leggibile.'];
    }
    $entries = $payload['props']['pageProps']['timeline']['entries'] ?? [];
    if (!is_array($entries)) {
        return ['error' => 'Il feed X non contiene post.'];
    }

    $images = [];
    foreach ($entries as $entry) {
        $tweet = $entry['content']['tweet'] ?? null;
        if (!is_array($tweet)) {
            continue;
        }

        $createdAt = strtotime((string) ($tweet['created_at'] ?? '')) ?: 0;
        $postId = (string) ($tweet['id_str'] ?? $tweet['conversation_id_str'] ?? '');
        $mediaItems = $tweet['extended_entities']['media'] ?? $tweet['entities']['media'] ?? [];

        foreach ($mediaItems as $media) {
            if (!is_array($media)) {
                continue;
            }
            $imageUrl = (string) ($media['media_url_https'] ?? '');
            if ($imageUrl === '') {
                continue;
            }
            $images[] = [
                'created_at' => $createdAt,
                'image_url' => $imageUrl . '?name=large',
                'post_url' => $postId === '' ? null : 'https://x.com/' . rawurlencode($username) . '/status/' . rawurlencode($postId),
            ];
            break;
        }
    }

    usort($images, static fn(array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
    if ($images === []) {
        return ['error' => 'Il profilo X non contiene foto pubbliche nel feed disponibile.'];
    }

    return $images[0];
}

if ($bearerToken === '') {
    $publicImage = latestXImageFromFxTwitter($username);
    $source = 'fxtwitter_media';
    if (!isset($publicImage['image_url'])) {
        $publicImage = latestXImageFromPublicTimeline($username);
        $source = 'public_timeline';
    }
    if (isset($publicImage['image_url'])) {
        jsonResponse(array_merge(xTeamPayload($team, $teamKey), [
            'ok' => true,
            'found' => true,
            'configured' => true,
            'source' => $source,
            'image_url' => $publicImage['image_url'],
            'post_url' => $publicImage['post_url'],
        ]));
    }

    jsonResponse(array_merge(xTeamPayload($team, $teamKey), [
        'ok' => true,
        'found' => false,
        'configured' => false,
        'image_url' => null,
        'post_url' => null,
        'message' => (string) ($publicImage['error'] ?? 'Nessuna immagine pubblica trovata per questo team.'),
    ]));
}

$headers = ['Authorization' => 'Bearer ' . $bearerToken];
$userResponse = httpRequest(
    'https://api.x.com/2/users/by/username/' . rawurlencode($username),
    'GET',
    $headers
);
$userPayload = json_decode((string) ($userResponse['body'] ?? ''), true);
$userId = (string) ($userPayload['data']['id'] ?? '');

if (($userResponse['status'] ?? 0) !== 200 || $userId === '') {
    jsonResponse([
        'ok' => false,
        'found' => false,
        'error' => 'Impossibile leggere il profilo X configurato.',
    ], 502);
}

$query = http_build_query([
    'max_results' => 10,
    'exclude' => 'retweets,replies',
    'expansions' => 'attachments.media_keys',
    'media.fields' => 'type,url,preview_image_url',
]);
$postsResponse = httpRequest(
    'https://api.x.com/2/users/' . rawurlencode($userId) . '/tweets?' . $query,
    'GET',
    $headers
);
$postsPayload = json_decode((string) ($postsResponse['body'] ?? ''), true);

if (($postsResponse['status'] ?? 0) !== 200 || !is_array($postsPayload)) {
    jsonResponse([
        'ok' => false,
        'found' => false,
        'error' => 'Impossibile leggere i post X.',
    ], 502);
}

$mediaByKey = [];
foreach (($postsPayload['includes']['media'] ?? []) as $media) {
    if (is_array($media) && isset($media['media_key'])) {
        $mediaByKey[(string) $media['media_key']] = $media;
    }
}

foreach (($postsPayload['data'] ?? []) as $post) {
    foreach (($post['attachments']['media_keys'] ?? []) as $mediaKey) {
        $media = $mediaByKey[(string) $mediaKey] ?? [];
        $imageUrl = (string) ($media['url'] ?? $media['preview_image_url'] ?? '');
        if ($imageUrl !== '') {
            $postId = (string) ($post['id'] ?? '');
            jsonResponse(array_merge(xTeamPayload($team, $teamKey), [
                'ok' => true,
                'found' => true,
                'configured' => true,
                'image_url' => $imageUrl,
                'post_url' => $postId === '' ? null : 'https://x.com/' . rawurlencode($username) . '/status/' . rawurlencode($postId),
            ]));
        }
    }
}

jsonResponse(array_merge(xTeamPayload($team, $teamKey), [
    'ok' => true,
    'found' => false,
    'configured' => true,
    'image_url' => null,
    'post_url' => null,
]));
