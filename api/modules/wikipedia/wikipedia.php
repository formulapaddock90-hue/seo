<?php

declare(strict_types=1);

$driverFallbackOptions = [
    ['label' => 'Lewis Hamilton', 'driverId' => 'hamilton'],
    ['label' => 'Max Verstappen', 'driverId' => 'verstappen'],
    ['label' => 'Charles Leclerc', 'driverId' => 'leclerc'],
    ['label' => 'Lando Norris', 'driverId' => 'norris'],
    ['label' => 'Fernando Alonso', 'driverId' => 'alonso'],
    ['label' => 'Michael Schumacher', 'driverId' => 'michael_schumacher'],
    ['label' => 'Ayrton Senna', 'driverId' => 'senna'],
    ['label' => 'Sebastian Vettel', 'driverId' => 'vettel'],
];

$teamPrincipalOptions = [
    ['label' => 'Toto Wolff', 'wikiTitle' => 'Toto Wolff', 'constructorTimeline' => [['constructorId' => 'williams', 'from' => 2012, 'to' => 2012], ['constructorId' => 'mercedes', 'from' => 2013, 'to' => 2026]]],
    ['label' => 'Christian Horner', 'wikiTitle' => 'Christian Horner', 'constructorTimeline' => [['constructorId' => 'red_bull', 'from' => 2005, 'to' => 2026]]],
    ['label' => 'Frédéric Vasseur', 'wikiTitle' => 'Frédéric Vasseur', 'constructorTimeline' => [['constructorId' => 'sauber', 'from' => 2017, 'to' => 2017], ['constructorId' => 'alfa', 'from' => 2022, 'to' => 2022], ['constructorId' => 'ferrari', 'from' => 2023, 'to' => 2026]]],
    ['label' => 'Andrea Stella', 'wikiTitle' => 'Andrea Stella (engineer)', 'constructorTimeline' => [['constructorId' => 'mclaren', 'from' => 2023, 'to' => 2026]]],
    ['label' => 'James Vowles', 'wikiTitle' => 'James Vowles', 'constructorTimeline' => [['constructorId' => 'williams', 'from' => 2023, 'to' => 2026]]],
    ['label' => 'Zak Brown', 'wikiTitle' => 'Zak Brown', 'constructorTimeline' => [['constructorId' => 'mclaren', 'from' => 2018, 'to' => 2026]]],
    ['label' => 'Guenther Steiner', 'wikiTitle' => 'Guenther Steiner', 'constructorTimeline' => [['constructorId' => 'haas', 'from' => 2016, 'to' => 2023]]],
    ['label' => 'Ron Dennis', 'wikiTitle' => 'Ron Dennis', 'constructorTimeline' => [['constructorId' => 'mclaren', 'from' => 1981, 'to' => 2008]]],
    ['label' => 'Jean Todt', 'wikiTitle' => 'Jean Todt', 'constructorTimeline' => [['constructorId' => 'ferrari', 'from' => 1993, 'to' => 2007]]],
    ['label' => 'Ross Brawn', 'wikiTitle' => 'Ross Brawn', 'constructorTimeline' => [['constructorId' => 'ferrari', 'from' => 1997, 'to' => 2006], ['constructorId' => 'brawn', 'from' => 2009, 'to' => 2009], ['constructorId' => 'mercedes', 'from' => 2010, 'to' => 2013]]],
];

function fetchJson(string $url): ?array
{
    $headers = [
        'http' => [
            'header' => "User-Agent: Solution1F1Dashboard/1.0\r\nAccept: application/json\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ];

    $response = false;
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Solution1F1Dashboard/1.0',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    if (!is_string($response) || $response === '' || $statusCode >= 400) {
        $response = @file_get_contents($url, false, stream_context_create($headers));
        if ($response === false) {
            return null;
        }
    }

    $data = json_decode($response, true);

    return is_array($data) ? $data : null;
}

function fetchAllPaginated(string $urlTemplate, callable $extractor): array
{
    $limit = 100;
    $offset = 0;
    $items = [];

    do {
        $url = sprintf($urlTemplate, $limit, $offset);
        $payload = fetchJson($url);
        $chunk = $extractor($payload);
        $total = (int) ($payload['MRData']['total'] ?? count($chunk));

        if ($chunk === []) {
            break;
        }

        $items = array_merge($items, $chunk);
        $offset += count($chunk);
    } while ($offset < $total);

    return $items;
}

function fetchAllEntityRaces(string $urlTemplate, string $entityValue): array
{
    $limit = 100;
    $offset = 0;
    $items = [];

    do {
        $url = sprintf($urlTemplate, rawurlencode($entityValue), $limit, $offset);
        $payload = fetchJson($url);
        $chunk = $payload['MRData']['RaceTable']['Races'] ?? [];
        $total = (int) ($payload['MRData']['total'] ?? count($chunk));

        if ($chunk === []) {
            break;
        }

        $items = array_merge($items, $chunk);
        $offset += count($chunk);
    } while ($offset < $total);

    return $items;
}

function getWikipediaTitleFromUrl(?string $url, string $fallbackTitle): string
{
    if (!$url) {
        return $fallbackTitle;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $fallbackTitle;
    }

    $title = basename($path);
    if ($title === '') {
        return $fallbackTitle;
    }

    return str_replace('_', ' ', urldecode($title));
}

function getWikipediaSummary(string $wikiTitle): ?array
{
    $normalizedTitle = str_replace(' ', '_', trim($wikiTitle));
    $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($normalizedTitle);
    $summary = fetchJson($summaryUrl);

    if (!is_array($summary) || (isset($summary['type']) && $summary['type'] === 'https://mediawiki.org/wiki/HyperSwitch/errors/not_found')) {
        return null;
    }

    return [
        'title' => $summary['title'] ?? $wikiTitle,
        'description' => $summary['description'] ?? '',
        'extract' => $summary['extract'] ?? '',
        'image' => $summary['thumbnail']['source'] ?? ($summary['originalimage']['source'] ?? null),
        'pageUrl' => $summary['content_urls']['desktop']['page'] ?? ('https://en.wikipedia.org/wiki/' . rawurlencode($normalizedTitle)),
    ];
}

function buildEmptyCharts(): array
{
    return [
        'winsByYear' => ['labels' => [], 'data' => []],
        'winsByTeam' => ['labels' => [], 'data' => []],
        'podiumsByTeam' => ['labels' => [], 'data' => []],
        'podiumsByYear' => ['labels' => [], 'data' => []],
        'polesByTeam' => ['labels' => [], 'data' => []],
        'polesByYear' => ['labels' => [], 'data' => []],
    ];
}

function getConstructorStandingForSeason(string $season, string $constructorId): ?array
{
    static $seasonCache = [];

    if (!isset($seasonCache[$season])) {
        $payload = fetchJson('https://api.jolpi.ca/ergast/f1/' . rawurlencode($season) . '/constructorStandings.json');
        $standings = $payload['MRData']['StandingsTable']['StandingsLists'][0]['ConstructorStandings'] ?? [];

        if ($standings === [] && (int) $season >= 1958) {
            $retryPayload = fetchJson('https://api.jolpi.ca/ergast/f1/' . rawurlencode($season) . '/constructorStandings.json');
            $standings = $retryPayload['MRData']['StandingsTable']['StandingsLists'][0]['ConstructorStandings'] ?? [];
        }

        $seasonCache[$season] = $standings;
    }

    foreach ($seasonCache[$season] as $standing) {
        if (($standing['Constructor']['constructorId'] ?? '') !== $constructorId) {
            continue;
        }

        return [
            'position' => (string) ($standing['position'] ?? ''),
            'positionText' => (string) ($standing['positionText'] ?? ''),
            'points' => (string) ($standing['points'] ?? ''),
            'wins' => (string) ($standing['wins'] ?? ''),
            'team' => (string) ($standing['Constructor']['name'] ?? $constructorId),
        ];
    }

    return null;
}

function formatConstructorStandingResult(?array $standing): string
{
    if ($standing === null) {
        return 'Classifica costruttori non disponibile';
    }

    $position = $standing['position'] !== '' ? $standing['position'] . '° posto' : 'Posizione non disponibile';
    $points = $standing['points'] !== '' ? $standing['points'] . ' pt' : 'punti n/d';

    return $position . ' · ' . $points;
}

function getTeamPrincipalTimeline(string $label, string $wikiTitle, array $constructorTimeline): array
{
    $summary = getWikipediaSummary($wikiTitle);
    $pageUrl = $summary['pageUrl'] ?? ('https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $wikiTitle)));
    $description = $summary['description'] ?? 'Team principal di Formula 1';

    $timeline = [];
    foreach ($constructorTimeline as $period) {
        $from = (int) ($period['from'] ?? 0);
        $to = (int) ($period['to'] ?? 0);
        $constructorId = (string) ($period['constructorId'] ?? '');

        if ($from === 0 || $to === 0 || $constructorId === '') {
            continue;
        }

        for ($season = $from; $season <= $to; $season++) {
            $timeline[] = [
                'year' => $season,
                'team' => strtoupper(str_replace('_', ' ', $constructorId)),
                'badge' => 'Team principal',
            ];
        }
    }

    if ($timeline === []) {
        $timeline[] = [
            'year' => (int) date('Y'),
            'team' => 'Team non disponibile',
            'badge' => 'Team principal',
        ];
    }

    return buildTimelineResult('teamPrincipal', $summary['title'] ?? $label, 'Timeline anno e team.', $description, $pageUrl, $timeline);
}

function buildTimelineResult(string $entityType, string $title, string $subtitle, string $description, string $pageUrl, array $timeline): array
{
    return [
        'mode' => 'timeline',
        'entityType' => $entityType,
        'title' => $title,
        'subtitle' => $subtitle,
        'description' => $description,
        'extract' => '',
        'image' => null,
        'pageUrl' => $pageUrl,
        'timeline' => $timeline,
        'charts' => buildEmptyCharts(),
    ];
}

function buildTimelineFromSummary(string $entityType, string $label, string $wikiTitle): array
{
    $summary = getWikipediaSummary($wikiTitle);
    $pageUrl = $summary['pageUrl'] ?? ('https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $wikiTitle)));
    $extract = $summary['extract'] ?? '';
    $description = $summary['description'] ?? '';

    $timelineMap = [];
    $sentences = preg_split('/(?<=[.!?])\s+/', $extract) ?: [];

    foreach ($sentences as $sentence) {
        if (preg_match_all('/\b(?:19|20)\d{2}\b/', $sentence, $matches) === 0) {
            continue;
        }

        foreach (array_unique($matches[0]) as $year) {
            $timelineMap[$year] = trimTimelineText($sentence);
        }
    }

    if ($timelineMap === []) {
        $timelineMap[(string) date('Y')] = $extract !== '' ? trimTimelineText($extract) : 'Voce F1 disponibile su Wikipedia.';
    }

    ksort($timelineMap, SORT_NUMERIC);

    $timeline = [];
    foreach ($timelineMap as $year => $text) {
        $timeline[] = [
            'year' => (int) $year,
            'team' => $text,
            'badge' => 'Timeline',
        ];
    }

    return buildTimelineResult(
        $entityType,
        $summary['title'] ?? $label,
        'Timeline F1 essenziale.',
        $description !== '' ? $description : 'Voce F1',
        $pageUrl,
        array_slice($timeline, 0, 12)
    );
}

function getDriverOptions(array $fallbackOptions): array
{
    $drivers = fetchAllPaginated(
        'https://api.jolpi.ca/ergast/f1/drivers.json?limit=%d&offset=%d',
        static fn (?array $payload): array => $payload['MRData']['DriverTable']['Drivers'] ?? []
    );

    $driverMap = [];
    foreach ($drivers as $driver) {
        $driverId = trim((string) ($driver['driverId'] ?? ''));
        $label = trim((string) (($driver['givenName'] ?? '') . ' ' . ($driver['familyName'] ?? '')));

        if ($driverId === '' || $label === '') {
            continue;
        }

        $driverMap[$driverId] = [
            'label' => $label,
            'driverId' => $driverId,
            'wikiTitle' => $label,
        ];
    }

    if ($driverMap === []) {
        return $fallbackOptions;
    }

    $options = array_values($driverMap);
    usort($options, static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label']));

    return $options;
}

function getConstructorOptions(): array
{
    $constructors = fetchAllPaginated(
        'https://api.jolpi.ca/ergast/f1/constructors.json?limit=%d&offset=%d',
        static fn (?array $payload): array => $payload['MRData']['ConstructorTable']['Constructors'] ?? []
    );

    $options = [];
    foreach ($constructors as $constructor) {
        $name = trim((string) ($constructor['name'] ?? ''));
        $constructorId = trim((string) ($constructor['constructorId'] ?? ''));

        if ($name === '' || $constructorId === '') {
            continue;
        }

        $options[$constructorId] = [
            'label' => $name,
            'constructorId' => $constructorId,
            'wikiTitle' => getWikipediaTitleFromUrl($constructor['url'] ?? null, $name),
        ];
    }

    $values = array_values($options);
    usort($values, static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label']));

    return $values;
}

function getCircuitOptions(): array
{
    $circuits = fetchAllPaginated(
        'https://api.jolpi.ca/ergast/f1/circuits.json?limit=%d&offset=%d',
        static fn (?array $payload): array => $payload['MRData']['CircuitTable']['Circuits'] ?? []
    );

    $options = [];
    foreach ($circuits as $circuit) {
        $name = trim((string) ($circuit['circuitName'] ?? ''));
        $circuitId = trim((string) ($circuit['circuitId'] ?? ''));
        $country = trim((string) ($circuit['Location']['country'] ?? ''));

        if ($name === '' || $circuitId === '') {
            continue;
        }

        $options[$circuitId] = [
            'label' => $name,
            'circuitId' => $circuitId,
            'country' => $country,
            'wikiTitle' => getWikipediaTitleFromUrl($circuit['url'] ?? null, $name),
        ];
    }

    $values = array_values($options);
    usort($values, static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label']));

    return $values;
}

function buildSearchEntities(array $drivers, array $constructors, array $principals, array $circuits): array
{
    $entities = [];

    foreach ($drivers as $driver) {
        $entities[] = [
            'label' => $driver['label'],
            'entityType' => 'driver',
            'entityValue' => $driver['driverId'],
            'wikiTitle' => $driver['wikiTitle'] ?? $driver['label'],
            'meta' => 'Pilota',
        ];
    }

    foreach ($constructors as $constructor) {
        $entities[] = [
            'label' => $constructor['label'],
            'entityType' => 'team',
            'entityValue' => $constructor['constructorId'],
            'wikiTitle' => $constructor['wikiTitle'] ?? $constructor['label'],
            'meta' => 'Team',
        ];
    }

    foreach ($principals as $principal) {
        $entities[] = [
            'label' => $principal['label'],
            'entityType' => 'teamPrincipal',
            'entityValue' => $principal['label'],
            'wikiTitle' => $principal['wikiTitle'] ?? $principal['label'],
            'meta' => 'Team principal',
            'constructorTimeline' => $principal['constructorTimeline'] ?? [],
        ];
    }

    foreach ($circuits as $circuit) {
        $entities[] = [
            'label' => $circuit['label'],
            'entityType' => 'circuit',
            'entityValue' => $circuit['circuitId'],
            'wikiTitle' => $circuit['wikiTitle'] ?? $circuit['label'],
            'meta' => $circuit['country'] !== '' ? 'Circuito · ' . $circuit['country'] : 'Circuito',
        ];
    }

    usort($entities, static function (array $left, array $right): int {
        $typeCompare = strcasecmp($left['meta'], $right['meta']);
        if ($typeCompare !== 0) {
            return $typeCompare;
        }

        return strcasecmp($left['label'], $right['label']);
    });

    return $entities;
}

$driverOptions = getDriverOptions($driverFallbackOptions);
$constructorOptions = getConstructorOptions();
$circuitOptions = getCircuitOptions();
$searchEntities = buildSearchEntities($driverOptions, $constructorOptions, $teamPrincipalOptions, $circuitOptions);

function incrementMetric(array &$metrics, string $key, int $amount = 1): void
{
    $metrics[$key] = ($metrics[$key] ?? 0) + $amount;
}

function buildYearSeries(array $metrics): array
{
    if ($metrics === []) {
        return ['labels' => [], 'data' => []];
    }

    ksort($metrics, SORT_NUMERIC);

    return [
        'labels' => array_map('strval', array_keys($metrics)),
        'data' => array_values($metrics),
    ];
}

function buildTeamSeries(array $metrics): array
{
    if ($metrics === []) {
        return ['labels' => [], 'data' => []];
    }

    arsort($metrics);

    return [
        'labels' => array_keys($metrics),
        'data' => array_values($metrics),
    ];
}

function fetchAllDriverRaces(string $driverId, string $endpoint): array
{
    return fetchAllEntityRaces('https://api.jolpi.ca/ergast/f1/drivers/%s/' . $endpoint . '.json?limit=%d&offset=%d', $driverId);
}

function getDriverCareerStats(string $driverId): array
{
    $races = fetchAllDriverRaces($driverId, 'results');
    $qualifyingRaces = fetchAllDriverRaces($driverId, 'qualifying');

    if ($races === []) {
        throw new RuntimeException('Dati carriera non disponibili per il pilota selezionato.');
    }

    $driverInfo = $races[0]['Results'][0]['Driver'] ?? [];
    $driverName = trim(($driverInfo['givenName'] ?? '') . ' ' . ($driverInfo['familyName'] ?? ''));

    $timelineMap = [];
    $winsByYear = [];
    $winsByTeam = [];
    $podiumsByYear = [];
    $podiumsByTeam = [];
    $polesByYear = [];
    $polesByTeam = [];

    foreach ($races as $race) {
        $season = (string) ($race['season'] ?? '');
        $result = $race['Results'][0] ?? null;

        if ($season === '' || !is_array($result)) {
            continue;
        }

        $team = $result['Constructor']['name'] ?? 'Team sconosciuto';
        $position = (int) ($result['position'] ?? 0);

        $timelineMap[$season]['teams'][$team] = true;

        if ($position === 1) {
            incrementMetric($winsByYear, $season);
            incrementMetric($winsByTeam, $team);
        }

        if ($position > 0 && $position <= 3) {
            incrementMetric($podiumsByYear, $season);
            incrementMetric($podiumsByTeam, $team);
        }
    }

    foreach ($qualifyingRaces as $race) {
        $season = (string) ($race['season'] ?? '');
        $qualifying = $race['QualifyingResults'][0] ?? null;

        if ($season === '' || !is_array($qualifying)) {
            continue;
        }

        $team = $qualifying['Constructor']['name'] ?? 'Team sconosciuto';
        $position = (int) ($qualifying['position'] ?? 0);

        $timelineMap[$season]['teams'][$team] = true;

        if ($position === 1) {
            incrementMetric($polesByYear, $season);
            incrementMetric($polesByTeam, $team);
        }
    }

    ksort($timelineMap, SORT_NUMERIC);

    $timeline = [];
    foreach ($timelineMap as $season => $data) {
        $teams = array_keys($data['teams'] ?? []);
        sort($teams);

        $timeline[] = [
            'year' => (int) $season,
            'team' => implode(' / ', $teams),
            'badge' => 'Stagione',
        ];
    }

    return [
        'mode' => 'driver',
        'entityType' => 'driver',
        'driverId' => $driverId,
        'title' => $driverName !== '' ? $driverName : $driverId,
        'subtitle' => 'Timeline anno-team e grafici aggregati di carriera.',
        'description' => 'Pilota di Formula 1',
        'extract' => '',
        'image' => null,
        'pageUrl' => 'https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $driverName !== '' ? $driverName : $driverId)),
        'timeline' => $timeline,
        'charts' => [
            'winsByYear' => buildYearSeries($winsByYear),
            'winsByTeam' => buildTeamSeries($winsByTeam),
            'podiumsByTeam' => buildTeamSeries($podiumsByTeam),
            'podiumsByYear' => buildYearSeries($podiumsByYear),
            'polesByTeam' => buildTeamSeries($polesByTeam),
            'polesByYear' => buildYearSeries($polesByYear),
        ],
    ];
}

function getTeamTimeline(string $constructorId, string $label, string $wikiTitle): array
{
    $races = fetchAllEntityRaces('https://api.jolpi.ca/ergast/f1/constructors/%s/results.json?limit=%d&offset=%d', $constructorId);
    $summary = getWikipediaSummary($wikiTitle);
    $pageUrl = $summary['pageUrl'] ?? ('https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $wikiTitle)));
    $description = $summary['description'] ?? 'Team di Formula 1';

    $seasons = [];
    foreach ($races as $race) {
        $season = (string) ($race['season'] ?? '');
        if ($season !== '') {
            $seasons[$season] = true;
        }
    }

    ksort($seasons, SORT_NUMERIC);
    $timeline = [];
    foreach (array_keys($seasons) as $season) {
        $timeline[] = [
            'year' => (int) $season,
            'team' => $label,
            'badge' => 'Costruttori',
        ];
    }

    if ($timeline === []) {
        $timeline[] = [
            'year' => (int) date('Y'),
            'team' => $label,
            'badge' => 'Costruttori',
        ];
    }

    return buildTimelineResult('team', $summary['title'] ?? $label, 'Timeline anno e team.', $description, $pageUrl, $timeline);
}

function getCircuitTimeline(string $circuitId, string $label, string $wikiTitle): array
{
    $races = fetchAllEntityRaces('https://api.jolpi.ca/ergast/f1/circuits/%s/results.json?limit=%d&offset=%d', $circuitId);
    $summary = getWikipediaSummary($wikiTitle);
    $pageUrl = $summary['pageUrl'] ?? ('https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $wikiTitle)));
    $description = $summary['description'] ?? 'Circuito di Formula 1';

    $timeline = [];
    foreach ($races as $race) {
        $season = (string) ($race['season'] ?? '');
        $winner = $race['Results'][0]['Constructor'] ?? null;
        if ($season === '' || !is_array($winner)) {
            continue;
        }

        $timeline[] = [
            'year' => (int) $season,
            'team' => $winner['name'] ?? 'Team non disponibile',
            'badge' => 'Vincitore GP',
        ];
    }

    if ($timeline === []) {
        $timeline[] = [
            'year' => (int) date('Y'),
            'team' => 'Team non disponibile',
            'badge' => 'Circuito',
        ];
    }

    return buildTimelineResult('circuit', $summary['title'] ?? $label, 'Timeline anno e team vincitore.', $description, $pageUrl, $timeline);
}

function getNonDriverTimeline(string $entityType, string $entityValue, string $wikiTitle, string $label, array $constructorTimeline = []): array
{
    return match ($entityType) {
        'team' => getTeamTimeline($entityValue, $label, $wikiTitle),
        'circuit' => getCircuitTimeline($entityValue, $label, $wikiTitle),
        'teamPrincipal' => getTeamPrincipalTimeline($label, $wikiTitle, $constructorTimeline),
        default => buildTimelineResult($entityType, $label, 'Timeline non disponibile.', 'Voce F1', 'https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $wikiTitle)), [[
            'year' => (int) date('Y'),
            'team' => 'Team non disponibile',
            'badge' => 'Timeline',
        ]]),
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'searchEntity') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $entityType = trim((string) ($_POST['entityType'] ?? ''));
        $entityValue = trim((string) ($_POST['entityValue'] ?? ''));
        $wikiTitle = trim((string) ($_POST['wikiTitle'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        $constructorTimeline = is_array($_POST['constructorTimeline'] ?? null) ? $_POST['constructorTimeline'] : [];

        if ($entityType === '' || $entityValue === '') {
            throw new InvalidArgumentException('Seleziona una voce F1 valida.');
        }

        $result = $entityType === 'driver'
            ? getDriverCareerStats($entityValue)
            : getNonDriverTimeline($entityType, $entityValue, $wikiTitle !== '' ? $wikiTitle : $label, $label !== '' ? $label : $entityValue, $constructorTimeline);

        echo json_encode([
            'success' => true,
            'result' => $result,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $exception) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard F1 Piloti</title>
    <link rel="stylesheet" href="wikipedia.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <main class="page-shell">
        <section class="hero">
            <div>
                <p class="eyebrow">Formula 1 Dashboard</p>
                <h1>Ricerca F1 estesa</h1>
                <p class="hero-copy">Cerca piloti, team, team principal e altre voci F1. I piloti mostrano timeline e grafici; le altre entità mostrano solo una timeline.</p>
            </div>
        </section>

        <section class="panel">
            <form id="career-form" class="search-form" autocomplete="off">
                <div class="field-group field-grow">
                    <label for="driver-input">Ricerca F1</label>
                    <div class="autocomplete">
                        <input id="driver-input" type="text" placeholder="Cerca pilota, team, team principal o circuito F1">
                        <input id="entity-type" name="entityType" type="hidden">
                        <input id="entity-value" name="entityValue" type="hidden">
                        <input id="entity-wiki-title" name="wikiTitle" type="hidden">
                        <input id="entity-label" name="label" type="hidden">
                        <div id="driver-suggestions" class="suggestions hidden"></div>
                    </div>
                </div>

                <button type="submit" class="search-button">Carica scheda</button>
            </form>
        </section>

        <section id="status-box" class="status-box" aria-live="polite"></section>

        <section id="result-card" class="result-card hidden">
            <div class="result-header">
                <div>
                    <p id="result-eyebrow" class="eyebrow">Scheda F1</p>
                    <h2 id="result-title"></h2>
                </div>
            </div>

            <section id="bio-section" class="bio-section hidden">
                <div class="bio-layout">
                    <img id="bio-image" class="bio-image hidden" alt="Immagine della voce F1">
                    <div class="bio-card">
                        <p id="bio-extract" class="bio-extract"></p>
                    </div>
                </div>
            </section>

            <section id="timeline-section" class="timeline-section hidden">
                <div class="section-heading">
                    <h3>Timeline</h3>
                </div>
                <div id="timeline-list" class="timeline-list"></div>
            </section>

            <section id="charts-section" class="charts-section hidden">
                <div class="section-heading">
                    <h3>Grafici</h3>
                </div>
                <div class="charts-grid">
                    <article class="chart-card">
                        <h4>Vittorie per anno</h4>
                        <div class="chart-panel"><canvas id="winsByYear-chart"></canvas></div>
                    </article>
                    <article class="chart-card">
                        <h4>Vittorie per team</h4>
                        <div class="chart-panel"><canvas id="winsByTeam-chart"></canvas></div>
                    </article>
                    <article class="chart-card">
                        <h4>Podi per team</h4>
                        <div class="chart-panel"><canvas id="podiumsByTeam-chart"></canvas></div>
                    </article>
                    <article class="chart-card">
                        <h4>Podi per anno</h4>
                        <div class="chart-panel"><canvas id="podiumsByYear-chart"></canvas></div>
                    </article>
                    <article class="chart-card">
                        <h4>Pole position per team</h4>
                        <div class="chart-panel"><canvas id="polesByTeam-chart"></canvas></div>
                    </article>
                    <article class="chart-card">
                        <h4>Pole position per anno</h4>
                        <div class="chart-panel"><canvas id="polesByYear-chart"></canvas></div>
                    </article>
                </div>
            </section>
        </section>
    </main>

    <script id="entity-data" type="application/json"><?= json_encode($searchEntities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script src="wikipedia.js"></script>
</body>
</html>
