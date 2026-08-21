<?php
/**
 * Servizio di pubblicazione automatica su Buffer via GraphQL API.
 */

function bufferGraphQL(string $query, array $variables = [], array $config = []): array
{
    $token = $config['buffer_access_token'] ?? '';
    if (empty($token)) {
        throw new Exception('Buffer Access Token non configurato.');
    }

    $ch = curl_init('https://api.buffer.com/graphql');
    $payload = json_encode([
        'query'     => $query,
        'variables' => $variables,
    ]);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $err) {
        throw new Exception("Errore di connessione verso Buffer: $err");
    }

    $data = json_decode($res, true);
    if ($code >= 400 || !empty($data['errors'])) {
        $msg = $data['errors'][0]['message'] ?? ($data['message'] ?? "HTTP $code");
        throw new Exception("Errore Buffer GraphQL ($code): $msg");
    }

    return $data['data'] ?? [];
}

function getBufferChannels(array $config): array
{
    $orgId = $config['buffer_organization_id'] ?? '689a66e96a24d3b69387c582';
    $query = 'query GetChannels($input: ChannelsInput!) {
        channels(input: $input) {
            id
            name
            service
        }
    }';

    $data = bufferGraphQL($query, ['input' => ['organizationId' => $orgId]], $config);
    return $data['channels'] ?? [];
}

function publishToBuffer(string $text, string $service = 'facebook', ?string $linkUrl = null, array $config = []): array
{
    $channels = getBufferChannels($config);
    $targetChannel = null;

    foreach ($channels as $ch) {
        if (strtolower($ch['service']) === strtolower($service)) {
            $targetChannel = $ch;
            break;
        }
    }

    if (!$targetChannel) {
        throw new Exception("Nessun canale Buffer trovato per il servizio: $service");
    }

    $query = 'mutation CreatePost($input: CreatePostInput!) {
        createPost(input: $input) {
            ... on PostActionSuccess {
                post {
                    id
                    status
                }
            }
            ... on InvalidInputError {
                message
            }
            ... on UnexpectedError {
                message
            }
            ... on RestProxyError {
                message
            }
            ... on LimitReachedError {
                message
            }
            ... on UnauthorizedError {
                message
            }
        }
    }';

    $assets = [];
    $fullText = $text;

    if (!empty($linkUrl)) {
        if (strpos($text, $linkUrl) === false) {
            $fullText = rtrim($text) . "\n\n" . $linkUrl;
        }
        if (strtolower($service) === 'facebook') {
            $assets[] = [
                'link' => [
                    'url' => $linkUrl
                ]
            ];
        }
    }

    $input = [
        'channelId'      => $targetChannel['id'],
        'text'           => $fullText,
        'mode'           => $config['buffer_share_mode'] ?? 'shareNow',
        'schedulingType' => 'automatic',
        'needsApproval'  => false,
        'assets'         => $assets
    ];

    if (strtolower($service) === 'facebook') {
        $input['metadata'] = [
            'facebook' => [
                'type' => 'post'
            ]
        ];
    }

    $data = bufferGraphQL($query, ['input' => $input], $config);
    $resPayload = $data['createPost'] ?? [];

    if (!empty($resPayload['message'])) {
        throw new Exception("Impossibile pubblicare su Buffer ({$service}): " . $resPayload['message']);
    }

    $post = $resPayload['post'] ?? null;
    if (!$post) {
        throw new Exception("Risposta non valida da Buffer per {$service}");
    }

    return [
        'id'      => $post['id'],
        'status'  => $post['status'],
        'channel' => $targetChannel['name'],
        'service' => $service,
    ];
}
