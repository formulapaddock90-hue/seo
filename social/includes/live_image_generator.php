<?php
/**
 * live_image_generator.php
 * Tre grafiche automatiche quando il Content Hub è in modalità Sessione Live:
 * 1) Top 3, 2) Risultato Ferrari, 3) Classifica Top 10.
 */

function liveRowValue(array $row, string $key, string $fallback = '-'): string
{
    $value = trim((string)($row[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function liveDriverIsFerrari(array $row): bool
{
    $team = mb_strtolower((string)($row['team_name'] ?? ''));
    $driver = mb_strtolower((string)($row['driver_name'] ?? ''));
    return mb_strpos($team, 'ferrari') !== false
        || mb_strpos($driver, 'leclerc') !== false
        || mb_strpos($driver, 'hamilton') !== false;
}

function sortLiveRows(array $rows): array
{
    usort($rows, static function (array $a, array $b): int {
        $pa = (int)preg_replace('/\D+/', '', (string)($a['position'] ?? '999'));
        $pb = (int)preg_replace('/\D+/', '', (string)($b['position'] ?? '999'));
        if ($pa <= 0) $pa = 999;
        if ($pb <= 0) $pb = 999;
        return $pa <=> $pb;
    });
    return $rows;
}

function generateLiveStandingsCard(
    string $outputPath,
    string $title,
    string $subtitle,
    array $rows,
    array $config,
    bool $highlightFerrari = false
): string {
    $width = 1080;
    $height = 1080;
    $img = imagecreatetruecolor($width, $height);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $black = imagecolorallocate($img, 7, 7, 9);
    $panel = imagecolorallocate($img, 18, 18, 22);
    $panelAlt = imagecolorallocate($img, 27, 27, 32);
    $white = imagecolorallocate($img, 250, 250, 250);
    $gray = imagecolorallocate($img, 175, 175, 183);
    $red = imagecolorallocate($img, 225, 6, 0);
    $yellow = imagecolorallocate($img, 255, 209, 0);
    $border = imagecolorallocate($img, 58, 58, 66);
    imagefill($img, 0, 0, $black);

    $fontBold = $config['font_bold'] ?? (__DIR__ . '/../fonts/Montserrat-Bold.ttf');
    $fontRegular = $config['font_regular'] ?? (__DIR__ . '/../fonts/Montserrat-Regular.ttf');
    $useTtf = is_file($fontBold) && is_file($fontRegular);

    // Brand header
    imagefilledrectangle($img, 0, 0, $width, 145, $black);
    imagefilledrectangle($img, 48, 112, $width - 48, 116, $red);
    if ($useTtf) {
        imagettftext($img, 24, 0, 48, 62, $white, $fontBold, 'FORMULA PADDOCK');
        imagettftext($img, 15, 0, 48, 96, $yellow, $fontBold, 'SESSIONE LIVE');
    } else {
        imagestring($img, 5, 48, 38, 'FORMULA PADDOCK', $white);
        imagestring($img, 4, 48, 75, 'SESSIONE LIVE', $yellow);
    }

    // Titolo
    $safeTitle = sanitizeTextForGd(mb_strtoupper($title));
    $safeSubtitle = sanitizeTextForGd($subtitle);
    if ($useTtf) {
        $titleSize = mb_strlen($safeTitle) > 28 ? 40 : 48;
        imagettftext($img, $titleSize, 0, 50, 205, $white, $fontBold, $safeTitle);
        $subLines = array_slice(wrapTextTtf($safeSubtitle, $fontRegular, 18, $width - 100), 0, 2);
        foreach ($subLines as $i => $line) {
            imagettftext($img, 18, 0, 52, 242 + ($i * 25), $gray, $fontRegular, $line);
        }
    } else {
        imagestring($img, 5, 50, 175, $safeTitle, $white);
        imagestring($img, 4, 50, 220, $safeSubtitle, $gray);
    }

    $rows = array_values($rows);
    $count = max(1, count($rows));
    $tableTop = 295;
    $tableBottom = 1000;
    $available = $tableBottom - $tableTop;
    $rowGap = $count <= 3 ? 20 : 8;
    $rowHeight = (int)(($available - (($count - 1) * $rowGap)) / $count);
    $rowHeight = max(54, min($rowHeight, $count <= 3 ? 190 : 78));

    foreach ($rows as $i => $row) {
        $y = $tableTop + $i * ($rowHeight + $rowGap);
        $isFerrari = liveDriverIsFerrari($row);
        $bg = ($i % 2 === 0) ? $panel : $panelAlt;
        drawRoundedRect($img, 48, $y, $width - 96, $rowHeight, 12, $bg);
        imagerectangle($img, 48, $y, $width - 48, $y + $rowHeight, ($highlightFerrari && $isFerrari) ? $red : $border);

        $pos = sanitizeTextForGd(liveRowValue($row, 'position', (string)($i + 1)));
        $driver = sanitizeTextForGd(liveRowValue($row, 'driver_name'));
        $team = sanitizeTextForGd(liveRowValue($row, 'team_name'));
        $timing = sanitizeTextForGd(liveRowValue($row, 'gap', liveRowValue($row, 'time')));

        $accent = ($highlightFerrari && $isFerrari) ? $red : ($i < 3 ? $yellow : $gray);

        if ($useTtf) {
            $posSize = $count <= 3 ? 46 : 25;
            $driverSize = $count <= 3 ? 30 : 20;
            $teamSize = $count <= 3 ? 19 : 14;
            $timeSize = $count <= 3 ? 22 : 16;
            $centerY = $y + (int)($rowHeight / 2);

            imagettftext($img, $posSize, 0, 72, $centerY + (int)($posSize / 3), $accent, $fontBold, $pos);
            imagettftext($img, $driverSize, 0, 170, $centerY - 5, $white, $fontBold, mb_strtoupper($driver));
            imagettftext($img, $teamSize, 0, 172, $centerY + 30, ($highlightFerrari && $isFerrari) ? $red : $gray, $fontRegular, $team);

            $tb = imagettfbbox($timeSize, 0, $fontBold, $timing);
            $tw = abs($tb[2] - $tb[0]);
            imagettftext($img, $timeSize, 0, $width - 74 - $tw, $centerY + 8, $white, $fontBold, $timing);
        } else {
            imagestring($img, 5, 72, $y + 18, $pos, $accent);
            imagestring($img, 5, 160, $y + 14, $driver, $white);
            imagestring($img, 3, 160, $y + 40, $team, $gray);
            imagestring($img, 4, $width - 260, $y + 24, $timing, $white);
        }
    }

    if (!$rows) {
        if ($useTtf) {
            imagettftext($img, 24, 0, 60, 420, $gray, $fontRegular, 'Dati sessione non ancora disponibili');
        } else {
            imagestring($img, 5, 60, 400, 'Dati sessione non ancora disponibili', $gray);
        }
    }

    imagefilledrectangle($img, 48, 1030, 300, 1033, $red);
    imagefilledrectangle($img, $width - 300, 1030, $width - 48, 1033, $red);
    if ($useTtf) {
        $footer = 'FORMULAPADDOCK.IT';
        $box = imagettfbbox(13, 0, $fontBold, $footer);
        $fw = abs($box[2] - $box[0]);
        imagettftext($img, 13, 0, (int)(($width - $fw) / 2), 1038, $gray, $fontBold, $footer);
    }

    if (!is_dir(dirname($outputPath))) mkdir(dirname($outputPath), 0777, true);
    imagejpeg($img, $outputPath, 94);
    imagedestroy($img);
    return $outputPath;
}

function generateLiveSessionInfographics(array $context, array $config): array
{
    $rows = sortLiveRows(is_array($context['rows'] ?? null) ? $context['rows'] : []);
    $session = trim((string)($context['session_name'] ?? 'Sessione Live'));
    $meeting = trim((string)($context['meeting_name'] ?? ''));
    $subtitle = trim($session . ($meeting !== '' ? ' · ' . $meeting : ''));

    $top3 = array_slice($rows, 0, 3);
    $ferrari = array_values(array_filter($rows, 'liveDriverIsFerrari'));
    $top10 = array_slice($rows, 0, 10);

    $dir = rtrim((string)$config['output_images_dir'], '/\\');
    $top3Path = $dir . '/live_top3.jpg';
    $ferrariPath = $dir . '/live_ferrari.jpg';
    $top10Path = $dir . '/live_top10.jpg';

    generateLiveStandingsCard($top3Path, 'TOP 3', $subtitle, $top3, $config, false);
    generateLiveStandingsCard($ferrariPath, 'RISULTATO FERRARI', $subtitle, $ferrari, $config, true);
    generateLiveStandingsCard($top10Path, 'CLASSIFICA TOP 10', $subtitle, $top10, $config, true);

    return [
        'top3' => $top3Path,
        'ferrari' => $ferrariPath,
        'top10' => $top10Path,
        'session_name' => $session,
        'meeting_name' => $meeting,
        'rows_count' => count($rows),
    ];
}
