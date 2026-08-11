<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['message' => 'Metodo non consentito'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    jsonResponse(['message' => 'Payload non valido'], 400);
}

$entries = $payload['entries'] ?? [];
if (!is_array($entries)) {
    $entries = [];
}

$logFile = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'errori.txt';
if ($logFile === false) {
    jsonResponse(['message' => 'Percorso log non valido'], 500);
}

$normalized = [];
foreach ($entries as $entry) {
    if (!is_array($entry)) {
        continue;
    }

    $ts = trim((string)($entry['ts'] ?? date('c')));
    $level = trim((string)($entry['level'] ?? 'client'));
    $message = trim((string)($entry['message'] ?? ''));
    $stack = trim((string)($entry['stack'] ?? ''));
    $page = trim((string)($entry['page'] ?? ''));

    if ($message === '') {
        continue;
    }

    $message = preg_replace('/\s+/', ' ', $message) ?? $message;
    $stack = preg_replace('/\s+/', ' ', $stack) ?? $stack;

    if (mb_strlen($message) > 800) {
        $message = mb_substr($message, 0, 800) . '...';
    }
    if (mb_strlen($stack) > 1000) {
        $stack = mb_substr($stack, 0, 1000) . '...';
    }

    $line = sprintf('[%s] [%s] %s', $ts !== '' ? $ts : date('c'), $level !== '' ? $level : 'client', $message);
    if ($page !== '') {
        $line .= ' | page=' . $page;
    }
    if ($stack !== '') {
        $line .= ' | stack=' . $stack;
    }

    $normalized[] = $line;
}

if (!$normalized) {
    jsonResponse(['ok' => true, 'written' => 0]);
}

$payloadText = implode(PHP_EOL, $normalized) . PHP_EOL;
$ok = @file_put_contents($logFile, $payloadText, FILE_APPEND | LOCK_EX);
if ($ok === false) {
    jsonResponse(['message' => 'Impossibile scrivere il log'], 500);
}

jsonResponse(['ok' => true, 'written' => count($normalized)]);
