<?php

require __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

function f1ApiGet(string $path): array
{
    return getJson('https://f1api.dev/api/' . ltrim($path, '/'));
}

function f1ApiResultRows(array $race): array
{
    foreach ([
        'results',
        'qualyResults',
        'sprintRaceResults',
        'sprintQualyResults',
        'fp1Results',
        'fp2Results',
        'fp3Results',
    ] as $key) {
        if (!empty($race[$key]) && is_array($race[$key])) {
            return $race[$key];
        }
    }

    return [];
}

function f1ApiSessionTimestamp(array $race, array $candidate): int
{
    $date = (string)($race[$candidate['date_key']] ?? $race['date'] ?? '');
    $time = (string)($race[$candidate['time_key']] ?? $race['time'] ?? '00:00:00Z');
    $timestamp = strtotime(trim($date . ' ' . $time));
    return $timestamp === false ? 0 : $timestamp;
}

function f1ApiDriverName(array $row): string
{
    $driver = is_array($row['driver'] ?? null) ? $row['driver'] : [];
    $name = trim((string)($driver['name'] ?? ''));
    $surname = trim((string)($driver['surname'] ?? ''));
    $full = trim($name . ' ' . $surname);

    return $full !== ''
        ? $full
        : (string)($driver['shortName'] ?? ($row['driverId'] ?? 'N/A'));
}

function f1ApiBestTime(array $row): string
{
    foreach (['time', 'q3', 'q2', 'q1', 'fastLap', 'bestLap', 'lapTime'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '' && strtolower($value) !== 'null') {
            return $value;
        }
    }

    return '';
}

function f1ApiNormalizeRows(array $rows): array
{
    $out = [];
    foreach ($rows as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }

        $team = is_array($row['team'] ?? null) ? $row['team'] : [];
        $position = $row['position'] ?? ($row['gridPosition'] ?? ($idx + 1));

        $out[] = [
            'position' => (string)$position,
            'driver_name' => f1ApiDriverName($row),
            'team_name' => (string)($team['teamName'] ?? ($row['teamId'] ?? '')),
            'time' => f1ApiBestTime($row),
            'points' => array_key_exists('points', $row) ? $row['points'] : null,
        ];
    }

    usort($out, static function ($a, $b) {
        $ap = is_numeric($a['position']) ? (int)$a['position'] : 9999;
        $bp = is_numeric($b['position']) ? (int)$b['position'] : 9999;
        return $ap <=> $bp;
    });

    return $out;
}

if ($action === 'latest') {
    $candidates = [
        ['path' => 'current/last/race', 'label' => 'Gara', 'date_key' => 'date', 'time_key' => 'time'],
        ['path' => 'current/last/qualy', 'label' => 'Qualifiche', 'date_key' => 'qualyDate', 'time_key' => 'qualyTime'],
        ['path' => 'current/last/sprint/race', 'label' => 'Sprint', 'date_key' => 'date', 'time_key' => 'time'],
        ['path' => 'current/last/sprint/qualy', 'label' => 'Sprint Qualifying', 'date_key' => 'date', 'time_key' => 'time'],
        ['path' => 'current/last/fp3', 'label' => 'Prove Libere 3', 'date_key' => 'fp3Date', 'time_key' => 'fp3Time'],
        ['path' => 'current/last/fp2', 'label' => 'Prove Libere 2', 'date_key' => 'fp2Date', 'time_key' => 'fp2Time'],
        ['path' => 'current/last/fp1', 'label' => 'Prove Libere 1', 'date_key' => 'fp1Date', 'time_key' => 'fp1Time'],
    ];

    $best = null;
    foreach ($candidates as $candidate) {
        $data = f1ApiGet($candidate['path']);
        $race = is_array($data['races'] ?? null) ? $data['races'] : [];
        $rows = f1ApiResultRows($race);
        if (!$rows) {
            continue;
        }

        $timestamp = f1ApiSessionTimestamp($race, $candidate);
        if ($best === null || $timestamp > $best['timestamp']) {
            $best = [
                'timestamp' => $timestamp,
                'session_name' => $candidate['label'],
                'race_name' => (string)($race['raceName'] ?? ''),
                'round' => $race['round'] ?? null,
                'date' => $timestamp > 0 ? date('Y-m-d H:i', $timestamp) : '',
                'source' => 'F1 API',
                'rows' => f1ApiNormalizeRows($rows),
            ];
        }
    }

    if ($best === null) {
        jsonResponse([
            'session_name' => '',
            'race_name' => '',
            'source' => 'F1 API',
            'rows' => [],
        ]);
    }

    unset($best['timestamp']);
    jsonResponse($best);
}

jsonResponse(['message' => 'Azione non valida'], 400);
