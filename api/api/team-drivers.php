<?php
/**
 * Endpoint API per caricare team e piloti della gara corrente da OpenF1
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/bootstrap.php';

$baseUrl = rtrim((string)($appConfig['openf1_base_url'] ?? 'https://api.openf1.org/v1'), '/');

/**
 * Funzione per caricare dati da OpenF1 API
 */
function fetchOpenF1Data(string $endpoint, array $params = []): array
{
    global $baseUrl;

    $url = $baseUrl . '/' . ltrim($endpoint, '/');
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => 'User-Agent: F1-Content-Hub/1.0'
        ]
    ]);

    try {
        $response = @file_get_contents($url, false, $ctx);
        if ($response !== false) {
            return json_decode($response, true) ?? [];
        }
    } catch (Exception $e) {
        // Ignora errori
    }

    return [];
}

function pickLatestMeeting(): ?array
{
    $latest = fetchOpenF1Data('meetings', ['meeting_key' => 'latest']);
    if (!empty($latest[0]) && is_array($latest[0])) {
        return $latest[0];
    }

    $currentYear = (int)date('Y');
    $meetings = fetchOpenF1Data('meetings', ['year' => $currentYear]);
    if ($meetings === []) {
        $meetings = fetchOpenF1Data('meetings');
    }

    if ($meetings === []) {
        return null;
    }

    usort($meetings, static function (array $a, array $b): int {
        return strcmp((string)($b['date_start'] ?? ''), (string)($a['date_start'] ?? ''));
    });

    return $meetings[0] ?? null;
}

function pickRaceSession(int $meetingKey): ?array
{
    $sessions = fetchOpenF1Data('sessions', ['meeting_key' => $meetingKey]);
    if ($sessions === []) {
        return null;
    }

    usort($sessions, static function (array $a, array $b): int {
        return strcmp((string)($a['date_start'] ?? ''), (string)($b['date_start'] ?? ''));
    });

    foreach ($sessions as $session) {
        $name = strtolower((string)($session['session_name'] ?? ''));
        $type = strtolower((string)($session['session_type'] ?? ''));
        if (str_contains($name, 'race') || str_contains($name, 'gara') || $type === 'race') {
            return $session;
        }
    }

    return end($sessions) ?: null;
}

function fetchSessionResults(int $sessionKey): array
{
    $rows = fetchOpenF1Data('session_results', ['session_key' => $sessionKey]);
    if ($rows !== []) {
        return $rows;
    }

    return fetchOpenF1Data('session_result', ['session_key' => $sessionKey]);
}

try {
    $meeting = pickLatestMeeting();
    if (!$meeting || empty($meeting['meeting_key'])) {
        jsonResponse([
            'success' => false,
            'error' => 'Nessuna gara trovata',
        ], 404);
    }

    $meetingKey = (int)$meeting['meeting_key'];
    $session = pickRaceSession($meetingKey);
    if (!$session || empty($session['session_key'])) {
        jsonResponse([
            'success' => false,
            'error' => 'Sessione gara non trovata',
        ], 404);
    }

    $sessionKey = (int)$session['session_key'];
    $drivers = fetchOpenF1Data('drivers', ['session_key' => $sessionKey]);
    $results = fetchSessionResults($sessionKey);

    if ($drivers === [] && $results === []) {
        jsonResponse([
            'success' => false,
            'error' => 'Dati gara non disponibili su OpenF1',
        ], 404);
    }

    $driverMap = [];
    foreach ($drivers as $driver) {
        $driverNumber = $driver['driver_number'] ?? null;
        if ($driverNumber === null) {
            continue;
        }

        $driverMap[(string)$driverNumber] = $driver;
    }

    $driversByTeam = [];

    if ($results !== []) {
        usort($results, static function (array $a, array $b): int {
            return (int)($a['position'] ?? 9999) <=> (int)($b['position'] ?? 9999);
        });

        foreach ($results as $result) {
            $driverNumber = (string)($result['driver_number'] ?? '');
            if ($driverNumber === '') {
                continue;
            }

            $driver = $driverMap[$driverNumber] ?? [];
            $teamName = trim((string)($driver['team_name'] ?? $result['team_name'] ?? ''));
            if ($teamName === '') {
                $teamName = 'Unknown';
            }

            $teamColour = (string)($driver['team_colour'] ?? $result['team_colour'] ?? '');
            $hexColor = $teamColour !== '' ? '#' . ltrim($teamColour, '#') : '#000000';

            if (!isset($driversByTeam[$teamName])) {
                $driversByTeam[$teamName] = [
                    'name' => $teamName,
                    'hex_color' => $hexColor,
                    'drivers' => [],
                ];
            }

            $driversByTeam[$teamName]['drivers'][] = [
                'driver_number' => (int)$driverNumber,
                'first_name' => $driver['first_name'] ?? ($result['first_name'] ?? ''),
                'last_name' => $driver['last_name'] ?? ($result['last_name'] ?? ''),
                'position' => isset($result['position']) ? (int)$result['position'] : null,
                'points' => isset($result['points']) ? (float)$result['points'] : 0,
            ];
        }
    }

    if ($driversByTeam === []) {
        foreach ($drivers as $driver) {
            $teamName = trim((string)($driver['team_name'] ?? ''));
            $driverNumber = $driver['driver_number'] ?? null;
            if ($teamName === '' || $driverNumber === null) {
                continue;
            }

            $teamColour = (string)($driver['team_colour'] ?? '');
            $hexColor = $teamColour !== '' ? '#' . ltrim($teamColour, '#') : '#000000';

            if (!isset($driversByTeam[$teamName])) {
                $driversByTeam[$teamName] = [
                    'name' => $teamName,
                    'hex_color' => $hexColor,
                    'drivers' => [],
                ];
            }

            $driversByTeam[$teamName]['drivers'][] = [
                'driver_number' => (int)$driverNumber,
                'first_name' => $driver['first_name'] ?? '',
                'last_name' => $driver['last_name'] ?? '',
                'position' => null,
                'points' => 0,
            ];
        }
    }

    $responseTeams = array_values($driversByTeam);

    jsonResponse([
        'success' => true,
        'race' => [
            'year' => (int)($meeting['year'] ?? date('Y')),
            'round' => (int)($meeting['meeting_key'] ?? 0),
            'name' => $meeting['meeting_name'] ?? ($meeting['country_name'] ?? 'Race'),
            'date' => $session['date_start'] ?? ($meeting['date_start'] ?? ''),
        ],
        'teams' => $responseTeams,
        'session_key' => $sessionKey,
        'meeting_key' => $meetingKey,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Errore nell\'elaborazione: ' . $e->getMessage(),
    ], 500);
}
