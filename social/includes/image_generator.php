<?php
/**
 * image_generator.php — Formula Paddock Editorial Visual System
 * Tre template automatici: Breaking News, Race Result, Analisi.
 */

function sanitizeTextForGd(string $text): string
{
    return strtr($text, [
        'è' => "e'", 'é' => "e'", 'à' => "a'", 'á' => "a'",
        'ì' => "i'", 'í' => "i'", 'ò' => "o'", 'ó' => "o'",
        'ù' => "u'", 'ú' => "u'", 'È' => "E'", 'À' => "A'",
        '’' => "'", '“' => '"', '”' => '"', '«' => '"', '»' => '"',
        '—' => '-', '–' => '-'
    ]);
}

function loadRemoteOrLocalImage(string $source)
{
    if ($source === '') return null;
    $imageData = null;
    if (filter_var($source, FILTER_VALIDATE_URL)) {
        $ch = curl_init($source);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FormulaPaddock/1.0',
        ]);
        $imageData = curl_exec($ch);
        curl_close($ch);
    } elseif (is_file($source)) {
        $imageData = file_get_contents($source);
    }
    return $imageData ? @imagecreatefromstring($imageData) : null;
}

function drawRoundedRect($img, int $x, int $y, int $w, int $h, int $radius, int $color): void
{
    imagefilledrectangle($img, $x + $radius, $y, $x + $w - $radius, $y + $h, $color);
    imagefilledrectangle($img, $x, $y + $radius, $x + $w, $y + $h - $radius, $color);
    imagefilledellipse($img, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x + $w - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x + $radius, $y + $h - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x + $w - $radius, $y + $h - $radius, $radius * 2, $radius * 2, $color);
}

function wrapTextTtf(string $text, string $fontPath, int $fontSize, int $maxWidth): array
{
    $words = preg_split('/\s+/u', trim($text));
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        if ($word === '') continue;
        $test = $current === '' ? $word : $current . ' ' . $word;
        $box = imagettfbbox($fontSize, 0, $fontPath, $test);
        $textWidth = abs($box[2] - $box[0]);
        if ($textWidth > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') $lines[] = $current;
    return $lines;
}

function wrapTextGd(string $text, int $gdFont, int $maxWidth): array
{
    $maxChars = max(10, (int)($maxWidth / max(1, imagefontwidth($gdFont))));
    return preg_split('/\n/', wordwrap($text, $maxChars, "\n", true));
}

function drawCoverImage($canvas, $source, int $x, int $y, int $w, int $h): bool
{
    if (!$source) return false;
    $sw = imagesx($source); $sh = imagesy($source);
    if ($sw <= 0 || $sh <= 0) return false;
    $srcRatio = $sw / $sh; $dstRatio = $w / $h;
    if ($srcRatio > $dstRatio) {
        $cropH = $sh; $cropW = (int)round($sh * $dstRatio);
        $srcX = (int)(($sw - $cropW) / 2); $srcY = 0;
    } else {
        $cropW = $sw; $cropH = (int)round($sw / $dstRatio);
        $srcX = 0; $srcY = max(0, (int)(($sh - $cropH) * 0.32));
    }
    imagecopyresampled($canvas, $source, $x, $y, $srcX, $srcY, $w, $h, $cropW, $cropH);
    return true;
}

function detectInfographicTemplate(array $content): string
{
    $explicit = strtolower(trim((string)($content['infografica_template'] ?? '')));
    if (in_array($explicit, ['breaking', 'race', 'analysis'], true)) return $explicit;
    $haystack = mb_strtolower(implode(' ', [
        (string)($content['categoria'] ?? ''),
        (string)($content['infografica_titolo'] ?? ''),
        (string)($content['infografica_sottotitolo'] ?? ''),
    ]));
    foreach (['analisi','tecnica','strategia','approfondimento','perche','perché','confronto','dati'] as $kw) {
        if (mb_strpos($haystack, $kw) !== false) return 'analysis';
    }
    foreach (['gara','risultato','vittoria','podio','gp ','gran premio','classifica','qualifiche','pole'] as $kw) {
        if (mb_strpos($haystack, $kw) !== false) return 'race';
    }
    return 'breaking';
}

function templateLabel(string $template): string
{
    if ($template === 'race') return 'RACE RESULT';
    if ($template === 'analysis') return 'ANALISI';
    return 'BREAKING NEWS';
}

function drawTextBlockTtf($img, array $lines, int $x, int $y, int $fontSize, int $lineHeight, string $font, array $colors): int
{
    foreach ($lines as $i => $line) {
        $color = $colors[min($i, count($colors) - 1)];
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, 45);
        imagettftext($img, $fontSize, 0, $x + 2, $y + ($i * $lineHeight) + 2, $shadow, $font, $line);
        imagettftext($img, $fontSize, 0, $x, $y + ($i * $lineHeight), $color, $font, $line);
    }
    return $y + (count($lines) * $lineHeight);
}

function generateInfographic(
    string $outputPath,
    int $width,
    int $height,
    string $title,
    string $subtitle,
    string $categoria,
    array $config,
    ?string $bgImageUrl = null,
    string $template = 'breaking',
    array $facts = []
): string {
    $img = imagecreatetruecolor($width, $height);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $title = sanitizeTextForGd($title);
    $subtitle = sanitizeTextForGd($subtitle);
    $categoria = sanitizeTextForGd($categoria);
    $template = in_array($template, ['breaking','race','analysis'], true) ? $template : 'breaking';

    $black = imagecolorallocate($img, 7, 7, 9);
    $panel = imagecolorallocate($img, 12, 12, 15);
    $panel2 = imagecolorallocate($img, 21, 21, 25);
    $white = imagecolorallocate($img, 250, 250, 250);
    $gray = imagecolorallocate($img, 195, 195, 200);
    $red = imagecolorallocate($img, 225, 6, 0);
    $yellow = imagecolorallocate($img, 255, 209, 0);
    $border = imagecolorallocate($img, 60, 60, 66);
    imagefill($img, 0, 0, $black);

    $fontBold = $config['font_bold'] ?? (__DIR__ . '/../fonts/Montserrat-Bold.ttf');
    $fontRegular = $config['font_regular'] ?? (__DIR__ . '/../fonts/Montserrat-Regular.ttf');
    $useTtf = is_file($fontBold) && is_file($fontRegular);

    imagefilledrectangle($img, 0, 0, $width, 150, $black);
    imagefilledrectangle($img, 250, 54, 390, 57, $red);
    imagefilledrectangle($img, $width - 390, 54, $width - 250, 57, $red);
    if ($useTtf) {
        $brand = 'FORMULA PADDOCK';
        $bb = imagettfbbox(25, 0, $fontBold, $brand);
        $bw = abs($bb[2] - $bb[0]);
        imagettftext($img, 25, 0, (int)(($width - $bw) / 2), 76, $white, $fontBold, $brand);
    } else {
        imagestring($img, 5, (int)($width / 2 - 80), 58, 'FORMULA PADDOCK', $white);
    }

    $label = templateLabel($template);
    $badgeY = 108;
    $badgeW = $template === 'breaking' ? 340 : ($template === 'analysis' ? 250 : 300);
    $badgePoints = [30,$badgeY,30+$badgeW,$badgeY,30+$badgeW-24,$badgeY+48,30,$badgeY+48];
    imagefilledpolygon($img, $badgePoints, 4, $red);
    imagefilledrectangle($img, 30 + $badgeW - 24, $badgeY + 34, 30 + $badgeW - 10, $badgeY + 48, $yellow);
    if ($useTtf) {
        imagettftext($img, 18, 0, 58, $badgeY + 33, $white, $fontBold, $label);
        imagettftext($img, 12, 0, $width - 165, $badgeY + 31, $gray, $fontRegular, 'FORMULA PADDOCK');
    }

    $heroY = 150; $heroH = 410;
    $hero = $bgImageUrl ? loadRemoteOrLocalImage($bgImageUrl) : null;
    if (!$hero || !drawCoverImage($img, $hero, 0, $heroY, $width, $heroH)) {
        for ($y = $heroY; $y < $heroY + $heroH; $y++) {
            $p = ($y - $heroY) / $heroH;
            $c = imagecolorallocate($img, (int)(16 + 35 * $p), (int)(15 + 3 * $p), (int)(19 + 5 * $p));
            imageline($img, 0, $y, $width, $y, $c);
        }
        $grid = imagecolorallocatealpha($img, 255, 255, 255, 120);
        for ($i = -400; $i < $width + 400; $i += 45) imageline($img, $i, $heroY, $i + 420, $heroY + $heroH, $grid);
    }
    if ($hero) imagedestroy($hero);

    for ($i = 0; $i < 130; $i++) {
        $alpha = (int)(127 - ($i / 130) * 112);
        $c = imagecolorallocatealpha($img, 0, 0, 0, max(8, $alpha));
        imageline($img, 0, $heroY + $heroH - 130 + $i, $width, $heroY + $heroH - 130 + $i, $c);
    }

    imagefilledrectangle($img, 0, 550, $width, $height, $panel);
    imagefilledrectangle($img, 40, 550, $width - 40, 553, $red);

    $titleUpper = mb_strtoupper($title !== '' ? $title : 'FORMULA 1 NEWS');
    $titleSize = $template === 'race' ? 47 : 50;
    if (mb_strlen($titleUpper) > 46) $titleSize = 43;
    if (mb_strlen($titleUpper) > 64) $titleSize = 38;
    $titleStartY = 630;
    if ($useTtf) {
        $lines = array_slice(wrapTextTtf($titleUpper, $fontBold, $titleSize, $width - 120), 0, 3);
        $titleEnd = drawTextBlockTtf($img, $lines, 64, $titleStartY, $titleSize, (int)($titleSize * 1.18), $fontBold, [$white,$red,$red]);
    } else {
        $lines = array_slice(wrapTextGd($titleUpper, 5, $width - 120), 0, 3);
        foreach ($lines as $i => $line) imagestring($img, 5, 64, $titleStartY + $i * 24, $line, $i === 0 ? $white : $red);
        $titleEnd = $titleStartY + count($lines) * 24;
    }

    $subtitleY = $titleEnd + 8;
    if ($subtitle !== '') {
        imagefilledrectangle($img, 64, $subtitleY - 22, 69, $subtitleY + 45, $red);
        if ($useTtf) {
            $subLines = array_slice(wrapTextTtf($subtitle, $fontRegular, 18, $width - 155), 0, 2);
            foreach ($subLines as $i => $line) imagettftext($img, 18, 0, 86, $subtitleY + $i * 26, $gray, $fontRegular, $line);
        } else {
            imagestring($img, 4, 86, $subtitleY - 14, $subtitle, $gray);
        }
    }

    $factsY = max(865, $subtitleY + 72);
    $gap = 16;
    $boxW = (int)(($width - 128 - ($gap * 2)) / 3);
    $boxH = 132;
    $defaultLabels = $template === 'analysis'
        ? ['CHIAVE 1','CHIAVE 2','CHIAVE 3']
        : ($template === 'race' ? ['RISULTATO','GARA','DATO CHIAVE'] : ['PUNTO 1','PUNTO 2','PUNTO 3']);

    for ($i = 0; $i < 3; $i++) {
        $x = 64 + $i * ($boxW + $gap);
        drawRoundedRect($img, $x, $factsY, $boxW, $boxH, 10, $panel2);
        imagerectangle($img, $x, $factsY, $x + $boxW, $factsY + $boxH, $border);
        $labelText = sanitizeTextForGd(trim((string)($facts[$i]['label'] ?? '')) ?: $defaultLabels[$i]);
        $valueText = sanitizeTextForGd(trim((string)($facts[$i]['value'] ?? '')) ?: 'Formula Paddock');
        if ($useTtf) {
            imagettftext($img, 13, 0, $x + 16, $factsY + 28, $template === 'race' ? $yellow : $red, $fontBold, mb_strtoupper($labelText));
            $valueLines = array_slice(wrapTextTtf($valueText, $fontBold, 16, $boxW - 32), 0, 3);
            foreach ($valueLines as $j => $line) imagettftext($img, 16, 0, $x + 16, $factsY + 58 + $j * 21, $white, $fontBold, $line);
        } else {
            imagestring($img, 4, $x + 12, $factsY + 12, $labelText, $yellow);
            imagestring($img, 3, $x + 12, $factsY + 42, $valueText, $white);
        }
    }

    $footerY = $height - 45;
    imagefilledrectangle($img, 64, $footerY - 2, 320, $footerY, $red);
    imagefilledrectangle($img, $width - 320, $footerY - 2, $width - 64, $footerY, $red);
    if ($useTtf) {
        $tag = 'PASSIONE. ANALISI. VELOCITA.';
        $tb = imagettfbbox(12, 0, $fontRegular, $tag);
        $tw = abs($tb[2] - $tb[0]);
        imagettftext($img, 12, 0, (int)(($width - $tw) / 2), $footerY + 6, $gray, $fontRegular, $tag);
    }

    if (!is_dir(dirname($outputPath))) mkdir(dirname($outputPath), 0777, true);
    imagejpeg($img, $outputPath, 94);
    imagedestroy($img);
    return $outputPath;
}

function generateAllInfographics(array $content, string $slug, array $config, ?string $bgImageUrl = null): array
{
    $title = (string)($content['infografica_titolo'] ?? '');
    $subtitle = (string)($content['infografica_sottotitolo'] ?? '');
    $categoria = (string)($content['categoria'] ?? '');
    $template = detectInfographicTemplate($content);
    $facts = [];
    for ($i = 1; $i <= 3; $i++) {
        $facts[] = [
            'label' => (string)($content['infografica_label_' . $i] ?? ''),
            'value' => (string)($content['infografica_dato_' . $i] ?? ''),
        ];
    }
    $dir = rtrim($config['output_images_dir'], '/\\');
    $hdPath = $dir . '/visual_studio_hd.jpg';
    $fbPath = $dir . '/facebook.jpg';
    $igPath = $dir . '/instagram.jpg';
    generateInfographic($hdPath, 1080, 1080, $title, $subtitle, $categoria, $config, $bgImageUrl, $template, $facts);
    @copy($hdPath, $fbPath);
    @copy($hdPath, $igPath);
    return ['hd_image' => $hdPath, 'fb_image' => $fbPath, 'ig_image' => $igPath, 'template' => $template];
}
