<?php

require_once __DIR__ . '/bootstrap.php';

$bearerToken = trim((string) ($appConfig['x_bearer_token'] ?? ''));
$username = trim((string) ($appConfig['x_username'] ?? 'paddock_formula'));

if ($bearerToken === '') {
    jsonResponse([
        'ok' => true,
        'found' => false,
        'configured' => false,
        'image_url' => null,
        'post_url' => null,
        'message' => 'Integrazione X non configurata.',
    ]);
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
            jsonResponse([
                'ok' => true,
                'found' => true,
                'configured' => true,
                'image_url' => $imageUrl,
                'post_url' => $postId === '' ? null : 'https://x.com/' . rawurlencode($username) . '/status/' . rawurlencode($postId),
            ]);
        }
    }
}

jsonResponse([
    'ok' => true,
    'found' => false,
    'configured' => true,
    'image_url' => null,
    'post_url' => null,
]);
