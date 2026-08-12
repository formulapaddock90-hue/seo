<?php

require __DIR__ . '/bootstrap.php';

// Registra shortcode per grafici Post Gara (solo se in WordPress)
if (function_exists('add_shortcode')) {
    require __DIR__ . '/postgara-shortcodes.php';
}

$baseUrl = rtrim($appConfig['openf1_base_url'], '/');
$action = $_GET['action'] ?? '';

function getPreviousSeasonYear(): int
{
    return (int)date('Y') - 1;
}

function openF1(string $path): array
{
    global $baseUrl;
    return getJson($baseUrl . '/' . ltrim($path, '/'));
}

function parseGapToSeconds($value): ?float
{
    if ($value === null) return null;
    $s = trim((string)$value);
    if ($s === '' || stripos($s, 'lap') !== false) return null;
    $s = ltrim($s, '+');
    return is_numeric($s) ? (float)$s : null;
}

function topDriversWithColors(array $drivers, int $limit = 8): array
{
    $out = [];
    foreach ($drivers as $d) {
        if (!isset($d['driver_number'])) continue;
        $out[] = [
            'driver_number' => $d['driver_number'],
            'name' => $d['name_acronym'] ?? (string)$d['driver_number'],
            'color' => isset($d['team_colour']) ? '#' . ltrim($d['team_colour'], '#') : '#3366cc',
            'team_name' => $d['team_name'] ?? 'N/A',
            'full_name' => $d['full_name'] ?? '',
        ];
    }
    return array_slice($out, 0, $limit);
}

function discoverAvailableYears(): array
{
    $years = [];
    $meetings = openF1('meetings');
    foreach ($meetings as $m) {
        $y = (int)($m['year'] ?? 0);
        if ($y > 0) {
            $years[$y] = true;
        }
    }

    if (!$years) {
        $current = (int)date('Y');
        for ($y = $current; $y >= $current - 7; $y--) {
            $rows = openF1('meetings?year=' . $y);
            if (!empty($rows)) {
                $years[$y] = true;
            }
        }
    }

    $list = array_keys($years);
    sort($list);
    return $list;
}

if ($action === 'teams_2026') {
    $teams = [
        'Red Bull Racing',
        'Ferrari',
        'Mercedes',
        'McLaren',
        'Aston Martin',
        'Alpine',
        'Williams',
        'RB',
        'Sauber',
        'Haas',
    ];

    $driversLatest = openF1('drivers?session_key=latest');
    foreach ($driversLatest as $driver) {
        $team = trim((string)($driver['team_name'] ?? ''));
        if ($team !== '' && !in_array($team, $teams, true)) {
            $teams[] = $team;
        }
    }

    $teams = array_values(array_unique($teams));
    sort($teams);
    jsonResponse(['teams' => $teams]);
}

if ($action === 'meetings_2026') {
    $targetYear = getPreviousSeasonYear();
    $meetings = openF1('meetings?year=' . $targetYear);
    $result = [];
    foreach ($meetings as $m) {
        if (!isset($m['meeting_key'])) continue;
        $result[] = [
            'meeting_key' => $m['meeting_key'],
            'country_name' => $m['country_name'] ?? 'N/A',
            'meeting_name' => $m['meeting_name'] ?? '',
            'location' => $m['location'] ?? '',
            'year' => $m['year'] ?? $targetYear,
        ];
    }
    jsonResponse($result);
}

if ($action === 'session_result_latest') {
    $rows = openF1('session_result?session_key=latest');
    if (!is_array($rows)) {
        jsonResponse([]);
    }

    $driversLatest = openF1('drivers?session_key=latest');
    $driverNamesByNumber = [];
    $teamNamesByNumber = [];
    foreach ($driversLatest as $d) {
        $num = (int)($d['driver_number'] ?? 0);
        if ($num <= 0) continue;
        $driverNamesByNumber[$num] = $d['full_name']
            ?? ($d['broadcast_name'] ?? ($d['name_acronym'] ?? (string)$num));
        $teamNamesByNumber[$num] = $d['team_name'] ?? '';
    }

    usort($rows, static function ($a, $b) {
        return (int)($a['position'] ?? 9999) <=> (int)($b['position'] ?? 9999);
    });

    $out = [];
    foreach ($rows as $r) {
        $driverNumber = (int)($r['driver_number'] ?? 0);
        $out[] = [
            'position' => (int)($r['position'] ?? 0),
            'driver_number' => $driverNumber,
            'driver_name' => $driverNamesByNumber[$driverNumber]
                ?? ($r['full_name'] ?? ($r['broadcast_name'] ?? ($r['abbreviation'] ?? 'N/A'))),
            'team_name' => $teamNamesByNumber[$driverNumber]
                ?? ($r['team_name'] ?? ''),
            'points' => isset($r['points']) ? (float)$r['points'] : null,
        ];
    }

    jsonResponse($out);
}

if ($action === 'weather_by_meeting') {
    $meetingKey = (int)($_GET['meeting_key'] ?? 0);
    if ($meetingKey <= 0) {
        jsonResponse(['message' => 'meeting_key mancante'], 400);
    }

    $sessions = openF1('sessions?meeting_key=' . $meetingKey);
    $rows = [];
    foreach ($sessions as $session) {
        $sessionKey = $session['session_key'] ?? null;
        if (!$sessionKey) continue;

        $weather = openF1('weather?session_key=' . $sessionKey);
        $maxTrack = null;
        $minTrack = null;
        $sumTrack = 0.0;
        $countTrack = 0;

        foreach ($weather as $w) {
            if (!isset($w['track_temperature'])) continue;
            $t = (float)$w['track_temperature'];
            $maxTrack = $maxTrack === null ? $t : max($maxTrack, $t);
            $minTrack = $minTrack === null ? $t : min($minTrack, $t);
            $sumTrack += $t;
            $countTrack++;
        }

        $rows[] = [
            'session_key' => $sessionKey,
            'session_name' => $session['session_name'] ?? '',
            'max_track_temperature' => $maxTrack,
            'min_track_temperature' => $minTrack,
            'avg_track_temperature' => $countTrack > 0 ? round($sumTrack / $countTrack, 2) : null,
        ];
    }

    jsonResponse($rows);
}

if ($action === 'analysis_latest') {
    $driversRaw = openF1('drivers?session_key=latest');
    $laps = openF1('laps?session_key=latest');
    $intervals = openF1('intervals?session_key=latest');
    $pit = openF1('pit?session_key=latest');
    $weather = openF1('weather?session_key=latest');
    $raceControl = openF1('race_control?session_key=latest');

    $topDrivers = topDriversWithColors($driversRaw, 8);

    $lapComparison = [];
    foreach ($topDrivers as $td) {
        $points = [];
        foreach ($laps as $lap) {
            if (($lap['driver_number'] ?? null) != $td['driver_number']) continue;
            if (!isset($lap['lap_number'], $lap['lap_duration'])) continue;
            if (!empty($lap['is_pit_out_lap'])) continue;
            $points[] = ['x' => (int)$lap['lap_number'], 'y' => (float)$lap['lap_duration']];
        }
        if ($points) {
            $lapComparison[] = [
                'driver' => $td['name'],
                'color' => $td['color'],
                'points' => $points,
            ];
        }
    }

    $gapByLap = [];
    foreach ($intervals as $row) {
        $lap = $row['lap_number'] ?? null;
        $gap = parseGapToSeconds($row['interval'] ?? null);
        if ($lap === null || $gap === null) continue;
        if (!isset($gapByLap[$lap])) $gapByLap[$lap] = ['sum' => 0.0, 'count' => 0];
        $gapByLap[$lap]['sum'] += $gap;
        $gapByLap[$lap]['count']++;
    }

    ksort($gapByLap);
    $gapToLeader = [];
    foreach ($gapByLap as $lap => $agg) {
        $gapToLeader[] = [
            'lap' => (int)$lap,
            'gap' => $agg['count'] > 0 ? round($agg['sum'] / $agg['count'], 3) : 0,
        ];
    }

    $driverMap = mapBy($driversRaw, 'driver_number');
    $pitStops = [];
    foreach ($pit as $p) {
        $dur = $p['pit_duration'] ?? null;
        $dn = $p['driver_number'] ?? null;
        if ($dur === null || $dn === null) continue;
        $pitStops[] = [
            'driver' => $driverMap[$dn]['name_acronym'] ?? (string)$dn,
            'duration' => (float)$dur,
        ];
    }
    $pitStops = array_slice($pitStops, 0, 20);

    $avgLap = [];
    foreach ($laps as $lap) {
        if (!isset($lap['date_start'], $lap['lap_duration'])) continue;
        if (!empty($lap['is_pit_out_lap'])) continue;
        $time = substr((string)$lap['date_start'], 11, 5);
        if (!isset($avgLap[$time])) $avgLap[$time] = ['sum' => 0.0, 'count' => 0];
        $avgLap[$time]['sum'] += (float)$lap['lap_duration'];
        $avgLap[$time]['count']++;
    }

    $weatherByTime = [];
    foreach ($weather as $w) {
        if (!isset($w['date'], $w['track_temperature'])) continue;
        $time = substr((string)$w['date'], 11, 5);
        $weatherByTime[$time] = (float)$w['track_temperature'];
    }

    $weatherVsPace = [];
    foreach ($avgLap as $time => $agg) {
        $weatherVsPace[] = [
            'time' => $time,
            'trackTemp' => $weatherByTime[$time] ?? null,
            'avgLap' => $agg['count'] > 0 ? round($agg['sum'] / $agg['count'], 3) : null,
        ];
    }

    $rcTimeline = [];
    foreach (array_slice($raceControl, 0, 40) as $rc) {
        $rcTimeline[] = [
            'date' => $rc['date'] ?? '',
            'message' => $rc['message'] ?? ($rc['flag'] ?? ''),
        ];
    }

    jsonResponse([
        'lapComparison' => $lapComparison,
        'gapToLeader' => $gapToLeader,
        'pitStops' => $pitStops,
        'weatherVsPace' => $weatherVsPace,
        'raceControl' => $rcTimeline,
    ]);
}

if ($action === 'teams_latest') {
    $drivers = openF1('drivers?session_key=latest');
    $laps = openF1('laps?session_key=latest');

    $driversByTeam = [];
    $driverToTeam = [];

    foreach ($drivers as $d) {
        $team = $d['team_name'] ?? null;
        $number = $d['driver_number'] ?? null;
        if (!$team || $number === null) continue;
        $driverToTeam[$number] = $team;
        $driversByTeam[$team][] = $d['full_name'] ?? ($d['name_acronym'] ?? (string)$number);
    }

    $teams = array_slice(array_values(array_unique(array_keys($driversByTeam))), 0, 10);
    $lapsByTeam = [];

    foreach ($laps as $lap) {
        $dn = $lap['driver_number'] ?? null;
        $team = $driverToTeam[$dn] ?? null;
        if (!$team || !in_array($team, $teams, true)) continue;
        if (!isset($lap['lap_number'], $lap['lap_duration'])) continue;
        if (!empty($lap['is_pit_out_lap'])) continue;
        $lapsByTeam[$team][] = [
            'lap' => (int)$lap['lap_number'],
            'lap_duration' => (float)$lap['lap_duration'],
        ];
    }

    jsonResponse([
        'teams' => $teams,
        'driversByTeam' => $driversByTeam,
        'lapsByTeam' => $lapsByTeam,
    ]);
}

if ($action === 'pirelli_infographic') {
    $country = trim((string)($_GET['country'] ?? ''));
    if ($country === '') {
        jsonResponse(['message' => 'country mancante'], 400);
    }

    $meetingsPrevYear = openF1('meetings?year=' . getPreviousSeasonYear());
    $selectedMeeting = null;
    foreach ($meetingsPrevYear as $m) {
        if (($m['country_name'] ?? '') === $country) {
            $selectedMeeting = $m;
        }
    }

    if (!$selectedMeeting || !isset($selectedMeeting['meeting_key'])) {
        jsonResponse([], 200);
    }

    $sessions = openF1('sessions?meeting_key=' . $selectedMeeting['meeting_key']);
    if (!$sessions) {
        jsonResponse([], 200);
    }

    usort($sessions, static function ($a, $b) {
        return strcmp((string)($a['date_end'] ?? ''), (string)($b['date_end'] ?? ''));
    });

    $lastSession = end($sessions);
    $sessionKey = $lastSession['session_key'] ?? null;
    if (!$sessionKey) {
        jsonResponse([], 200);
    }

    $laps = openF1('laps?session_key=' . $sessionKey);
    $stints = openF1('stints?session_key=' . $sessionKey);

    $bestLapByDriver = [];
    foreach ($laps as $lap) {
        $dn = $lap['driver_number'] ?? null;
        $dur = $lap['lap_duration'] ?? null;
        if ($dn === null || $dur === null) continue;
        if (!empty($lap['is_pit_out_lap'])) continue;
        $dur = (float)$dur;
        if (!isset($bestLapByDriver[$dn]) || $dur < $bestLapByDriver[$dn]) {
            $bestLapByDriver[$dn] = $dur;
        }
    }

    $compoundStats = [];
    foreach ($stints as $stint) {
        $compound = strtoupper((string)($stint['compound'] ?? 'UNKNOWN'));
        $dn = $stint['driver_number'] ?? null;
        $lapsInStint = (int)($stint['lap_end'] ?? 0) - (int)($stint['lap_start'] ?? 0) + 1;
        $bestLap = $dn !== null ? ($bestLapByDriver[$dn] ?? null) : null;

        if (!isset($compoundStats[$compound])) {
            $compoundStats[$compound] = [
                'compound' => $compound,
                'best_lap' => $bestLap,
                'max_stint_laps' => max(0, $lapsInStint),
            ];
        } else {
            if ($bestLap !== null && ($compoundStats[$compound]['best_lap'] === null || $bestLap < $compoundStats[$compound]['best_lap'])) {
                $compoundStats[$compound]['best_lap'] = $bestLap;
            }
            $compoundStats[$compound]['max_stint_laps'] = max($compoundStats[$compound]['max_stint_laps'], $lapsInStint);
        }
    }

    $out = array_values($compoundStats);
    usort($out, static function ($a, $b) {
        return strcmp($a['compound'], $b['compound']);
    });

    foreach ($out as &$row) {
        $row['best_lap'] = $row['best_lap'] !== null ? round((float)$row['best_lap'], 3) : null;
    }

    jsonResponse($out);
}

if ($action === 'yearly_max_temperature') {
    $years = discoverAvailableYears();
    $out = [];

    foreach ($years as $year) {
        $meetings = openF1('meetings?year=' . $year);
        $yearMax = null;

        foreach ($meetings as $meeting) {
            $meetingKey = (int)($meeting['meeting_key'] ?? 0);
            if ($meetingKey <= 0) continue;

            $sessions = openF1('sessions?meeting_key=' . $meetingKey);
            foreach ($sessions as $session) {
                $sessionKey = (int)($session['session_key'] ?? 0);
                if ($sessionKey <= 0) continue;

                $weather = openF1('weather?session_key=' . $sessionKey);
                foreach ($weather as $w) {
                    if (!isset($w['track_temperature'])) continue;
                    $temp = (float)$w['track_temperature'];
                    $yearMax = $yearMax === null ? $temp : max($yearMax, $temp);
                }
            }
        }

        if ($yearMax !== null) {
            $out[] = [
                'year' => $year,
                'max_track_temperature' => round($yearMax, 2),
            ];
        }
    }

    jsonResponse(['rows' => $out]);
}

if ($action === 'pirelli_driver_stats') {
    $country = trim((string)($_GET['country'] ?? ''));
    if ($country === '') {
        jsonResponse(['message' => 'country mancante'], 400);
    }

    $meetingsPrevYear = openF1('meetings?year=' . getPreviousSeasonYear());
    $selectedMeeting = null;
    foreach ($meetingsPrevYear as $m) {
        if (($m['country_name'] ?? '') === $country) {
            $selectedMeeting = $m;
        }
    }

    if (!$selectedMeeting || !isset($selectedMeeting['meeting_key'])) {
        jsonResponse(['best_lap_by_driver' => [], 'max_stint_by_driver' => []]);
    }

    $sessions = openF1('sessions?meeting_key=' . $selectedMeeting['meeting_key']);
    if (!$sessions) {
        jsonResponse(['best_lap_by_driver' => [], 'max_stint_by_driver' => []]);
    }

    usort($sessions, static function ($a, $b) {
        return strcmp((string)($a['date_end'] ?? ''), (string)($b['date_end'] ?? ''));
    });

    $lastSession = end($sessions);
    $sessionKey = $lastSession['session_key'] ?? null;
    if (!$sessionKey) {
        jsonResponse(['best_lap_by_driver' => [], 'max_stint_by_driver' => []]);
    }

    $drivers = openF1('drivers?session_key=' . $sessionKey);
    $driverMap = [];
    foreach ($drivers as $d) {
        if (!isset($d['driver_number'])) continue;
        $driverMap[(int)$d['driver_number']] = $d['full_name'] ?? ($d['name_acronym'] ?? (string)$d['driver_number']);
    }

    $laps = openF1('laps?session_key=' . $sessionKey);
    $stints = openF1('stints?session_key=' . $sessionKey);

    $bestLapByDriver = [];
    foreach ($laps as $lap) {
        $dn = (int)($lap['driver_number'] ?? 0);
        $dur = $lap['lap_duration'] ?? null;
        if ($dn <= 0 || $dur === null) continue;
        if (!empty($lap['is_pit_out_lap'])) continue;

        $dur = (float)$dur;
        if (!isset($bestLapByDriver[$dn]) || $dur < $bestLapByDriver[$dn]) {
            $bestLapByDriver[$dn] = $dur;
        }
    }

    $maxStintByDriver = [];
    foreach ($stints as $stint) {
        $dn = (int)($stint['driver_number'] ?? 0);
        if ($dn <= 0) continue;

        $len = (int)($stint['lap_end'] ?? 0) - (int)($stint['lap_start'] ?? 0) + 1;
        if (!isset($maxStintByDriver[$dn]) || $len > $maxStintByDriver[$dn]) {
            $maxStintByDriver[$dn] = max(0, $len);
        }
    }

    $bestRows = [];
    foreach ($bestLapByDriver as $dn => $time) {
        $bestRows[] = [
            'driver' => $driverMap[$dn] ?? (string)$dn,
            'best_lap' => round($time, 3),
        ];
    }

    $stintRows = [];
    foreach ($maxStintByDriver as $dn => $lapsCount) {
        $stintRows[] = [
            'driver' => $driverMap[$dn] ?? (string)$dn,
            'max_stint_laps' => (int)$lapsCount,
        ];
    }

    usort($bestRows, static fn($a, $b) => $a['best_lap'] <=> $b['best_lap']);
    usort($stintRows, static fn($a, $b) => $b['max_stint_laps'] <=> $a['max_stint_laps']);

    jsonResponse([
        'best_lap_by_driver' => $bestRows,
        'max_stint_by_driver' => $stintRows,
    ]);
}

if ($action === 'postgara_compound_stats') {
    $sessionKey = (int)($_GET['session_key'] ?? 0);
    if ($sessionKey <= 0) {
        jsonResponse(['message' => 'session_key mancante'], 400);
    }

    $drivers = openF1('drivers?session_key=' . $sessionKey);
    $laps = openF1('laps?session_key=' . $sessionKey);
    $stints = openF1('stints?session_key=' . $sessionKey);

    $effectiveSessionKey = $sessionKey;
    if ((empty($laps) && empty($stints)) || empty($drivers)) {
        $driversLatest = openF1('drivers?session_key=latest');
        $lapsLatest = openF1('laps?session_key=latest');
        $stintsLatest = openF1('stints?session_key=latest');

        if (!empty($driversLatest) || !empty($lapsLatest) || !empty($stintsLatest)) {
            $drivers = $driversLatest;
            $laps = $lapsLatest;
            $stints = $stintsLatest;
            $effectiveSessionKey = 'latest';
        }
    }

    $driverNames = [];
    foreach ($drivers as $driver) {
        $dn = (int)($driver['driver_number'] ?? 0);
        if ($dn <= 0) continue;
        $driverNames[$dn] = trim((string)($driver['full_name'] ?? ''))
            ?: trim((string)($driver['broadcast_name'] ?? ''))
            ?: trim((string)($driver['name_acronym'] ?? ''))
            ?: (string)$dn;
    }

    $normalizeCompound = static function ($value): string {
        $compound = strtoupper(trim((string)$value));
        return $compound !== '' ? $compound : 'UNKNOWN';
    };

    $stintsByDriver = [];
    $compoundUsage = [];
    $compoundUsageByDriver = [];

    foreach ($stints as $stint) {
        $dn = (int)($stint['driver_number'] ?? 0);
        if ($dn <= 0) continue;

        $compound = $normalizeCompound($stint['compound'] ?? '');
        $startLap = (int)($stint['lap_start'] ?? 0);
        $endLap = (int)($stint['lap_end'] ?? 0);

        if ($startLap > 0 && $endLap >= $startLap) {
            $stintsByDriver[$dn][] = [
                'start' => $startLap,
                'end' => $endLap,
                'compound' => $compound,
            ];
        }

        $compoundUsage[$compound] = ($compoundUsage[$compound] ?? 0) + 1;
        $compoundUsageByDriver[$dn][$compound] = ($compoundUsageByDriver[$dn][$compound] ?? 0) + 1;
    }

    foreach ($stintsByDriver as &$driverStints) {
        usort($driverStints, static fn($a, $b) => $a['start'] <=> $b['start']);
    }
    unset($driverStints);

    $lapCountByCompound = [];
    $lapCountByDriverCompound = [];
    $bestLapByCompoundDriver = [];

    foreach ($laps as $lap) {
        $dn = (int)($lap['driver_number'] ?? 0);
        $lapNumber = (int)($lap['lap_number'] ?? 0);
        if ($dn <= 0 || $lapNumber <= 0) continue;
        if (!empty($lap['is_pit_out_lap'])) continue;

        $compound = $normalizeCompound($lap['compound'] ?? '');
        if ($compound === 'UNKNOWN' && !empty($stintsByDriver[$dn])) {
            foreach ($stintsByDriver[$dn] as $stintRange) {
                if ($lapNumber >= $stintRange['start'] && $lapNumber <= $stintRange['end']) {
                    $compound = $stintRange['compound'];
                    break;
                }
            }
        }

        $lapCountByCompound[$compound] = ($lapCountByCompound[$compound] ?? 0) + 1;
        $lapCountByDriverCompound[$dn][$compound] = ($lapCountByDriverCompound[$dn][$compound] ?? 0) + 1;

        $lapDuration = $lap['lap_duration'] ?? null;
        if ($lapDuration === null || !is_numeric($lapDuration)) {
            continue;
        }

        $lapDuration = (float)$lapDuration;
        if (
            !isset($bestLapByCompoundDriver[$compound][$dn])
            || $lapDuration < $bestLapByCompoundDriver[$compound][$dn]
        ) {
            $bestLapByCompoundDriver[$compound][$dn] = $lapDuration;
        }
    }

    $bestLapRanking = [];
    foreach ($bestLapByCompoundDriver as $compound => $driverTimes) {
        foreach ($driverTimes as $dn => $bestLap) {
            $bestLapRanking[] = [
                'compound' => $compound,
                'driver' => $driverNames[$dn] ?? (string)$dn,
                'best_lap' => round($bestLap, 3),
            ];
        }
    }
    usort($bestLapRanking, static function ($a, $b) {
        $cmp = strcmp((string)$a['compound'], (string)$b['compound']);
        if ($cmp !== 0) return $cmp;
        return ($a['best_lap'] <=> $b['best_lap']);
    });

    $lapCountRows = [];
    foreach ($lapCountByCompound as $compound => $count) {
        $lapCountRows[] = [
            'compound' => $compound,
            'lap_count' => (int)$count,
        ];
    }
    usort($lapCountRows, static function ($a, $b) {
        $cmp = $b['lap_count'] <=> $a['lap_count'];
        return $cmp !== 0 ? $cmp : strcmp((string)$a['compound'], (string)$b['compound']);
    });

    $lapCountByDriverRows = [];
    foreach ($lapCountByDriverCompound as $dn => $rows) {
        foreach ($rows as $compound => $count) {
            $lapCountByDriverRows[] = [
                'driver' => $driverNames[$dn] ?? (string)$dn,
                'compound' => $compound,
                'lap_count' => (int)$count,
            ];
        }
    }
    usort($lapCountByDriverRows, static function ($a, $b) {
        $cmp = strcmp((string)$a['driver'], (string)$b['driver']);
        if ($cmp !== 0) return $cmp;
        return strcmp((string)$a['compound'], (string)$b['compound']);
    });

    $compoundUsageRows = [];
    foreach ($compoundUsage as $compound => $count) {
        $compoundUsageRows[] = [
            'compound' => $compound,
            'usage_count' => (int)$count,
        ];
    }
    usort($compoundUsageRows, static function ($a, $b) {
        $cmp = $b['usage_count'] <=> $a['usage_count'];
        return $cmp !== 0 ? $cmp : strcmp((string)$a['compound'], (string)$b['compound']);
    });

    $compoundUsageByDriverRows = [];
    foreach ($compoundUsageByDriver as $dn => $rows) {
        foreach ($rows as $compound => $count) {
            $compoundUsageByDriverRows[] = [
                'driver' => $driverNames[$dn] ?? (string)$dn,
                'compound' => $compound,
                'usage_count' => (int)$count,
            ];
        }
    }
    usort($compoundUsageByDriverRows, static function ($a, $b) {
        $cmp = strcmp((string)$a['driver'], (string)$b['driver']);
        if ($cmp !== 0) return $cmp;
        return strcmp((string)$a['compound'], (string)$b['compound']);
    });

    jsonResponse([
        'session_key' => $effectiveSessionKey,
        'best_lap_ranking_by_compound' => $bestLapRanking,
        'lap_count_by_compound' => $lapCountRows,
        'lap_count_by_driver_compound' => $lapCountByDriverRows,
        'compound_usage_count' => $compoundUsageRows,
        'compound_usage_by_driver' => $compoundUsageByDriverRows,
    ]);
}

// ── undercutf1_data ─────────────────────────────────────────────────────────
// Serve il JSON caricato dallo script f1_undercutf1_charts.ps1 (dati locali undercutf1)
if ($action === 'undercutf1_data') {
    $filePath = __DIR__ . '/undercutf1_race.json';
    if (!file_exists($filePath)) {
        jsonResponse(['message' => 'Nessun dato undercutf1 disponibile. Eseguire f1_undercutf1_charts.ps1 prima.'], 404);
    }
    $raw = file_get_contents($filePath);
    $data = json_decode($raw, true);
    if (!$data) {
        jsonResponse(['message' => 'File undercutf1_race.json non valido.'], 500);
    }
    jsonResponse($data);
}

jsonResponse(['message' => 'Azione non valida'], 400);
