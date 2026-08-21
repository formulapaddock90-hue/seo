<?php
/**
 * image_generator.php — Formula Paddock Visual Studio HD (1080x1080)
 * Generatore grafico ad alto impatto per social media (Facebook & Instagram).
 */

function sanitizeTextForGd(string $text): string
{
    $replacements = [
        'è' => "e'", 'é' => "e'", 'à' => "a'", 'á' => "a'",
        'ì' => "i'", 'í' => "i'", 'ò' => "o'", 'ó' => "o'",
        'ù' => "u'", 'ú' => "u'", 'È' => "E'", 'À' => "A'",
        '’' => "'", '“' => '"', '”' => '"', '«' => '"', '»' => '"',
        '—' => '-', '–' => '-'
    ];
    return strtr($text, $replacements);
}

function loadRemoteOrLocalImage(string $source)
{
    if (empty($source)) {
        return null;
    }

    $imageData = null;
    if (filter_var($source, FILTER_VALIDATE_URL)) {
        $ch = curl_init($source);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $imageData = curl_exec($ch);
        curl_close($ch);
    } elseif (file_exists($source)) {
        $imageData = file_get_contents($source);
    }

    if (!$imageData) {
        return null;
    }

    return @imagecreatefromstring($imageData);
}

function drawRoundedRect($img, $x, $y, $w, $h, $radius, $color)
{
    imagefilledrectangle($img, $x + $radius, $y, $x + $w - $radius, $y + $h, $color);
    imagefilledrectangle($img, $x, $y + $radius, $x + $w, $y + $h - $radius, $color);
    imagefilledellipse($img, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x + $w - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x + $radius, $y + $h - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x + $w - $radius, $y + $h - $radius, $radius * 2, $radius * 2, $color);
}

function generateInfographic(
    string $outputPath,
    int $width,
    int $height,
    string $title,
    string $subtitle,
    string $categoria,
    array $config,
    ?string $bgImageUrl = null
): string {
    $img = imagecreatetruecolor($width, $height);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $title = sanitizeTextForGd($title);
    $subtitle = sanitizeTextForGd($subtitle);
    $categoria = sanitizeTextForGd($categoria);

    // 1. CARICAMENTO E RENDERING DELLO SFONDO FOTOGRAFICO
    $bgImg = !empty($bgImageUrl) ? loadRemoteOrLocalImage($bgImageUrl) : null;

    if ($bgImg) {
        $origW = imagesx($bgImg);
        $origH = imagesy($bgImg);

        // Aspect Fill con ritaglio centrato
        $ratio = max($width / $origW, $height / $origH);
        $newW = (int)($origW * $ratio);
        $newH = (int)($origH * $ratio);
        $srcX = (int)(($newW - $width) / (2 * $ratio));
        $srcY = (int)(($newH - $height) / (3 * $ratio)); // leggermente spostato verso l'alto per inquadrare bene auto/pilota

        imagecopyresampled($img, $bgImg, 0, 0, $srcX, $srcY, $width, $height, (int)($width / $ratio), (int)($height / $ratio));
        imagedestroy($bgImg);
    } else {
        // Sfondo dinamico ad alta definizione F1 Dark Carbon
        $colorTop    = [18, 12, 16];
        $colorBottom = [80, 8, 14];

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int) ($colorTop[0] + ($colorBottom[0] - $colorTop[0]) * $ratio);
            $g = (int) ($colorTop[1] + ($colorBottom[1] - $colorTop[1]) * $ratio);
            $b = (int) ($colorTop[2] + ($colorBottom[2] - $colorTop[2]) * $ratio);
            $color = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $width, $y, $color);
        }

        // Texture a trama sportiva / carbon fiber
        $gridColor = imagecolorallocatealpha($img, 255, 255, 255, 123);
        for ($i = - $height; $i < $width + $height; $i += 32) {
            imageline($img, $i, 0, $i + $height, $height, $gridColor);
        }
    }

    // 2. GRADIENTE SCRIM DARK PER CONTRASTO PERFETTO DEL TESTO (Da metà immagine fino a giù)
    $scrimStartY = (int) ($height * 0.36);
    for ($y = $scrimStartY; $y < $height; $y++) {
        $progress = ($y - $scrimStartY) / ($height - $scrimStartY);
        // Alpha da 127 (trasparente) a 10 (quasi opaco 92%)
        $alpha = (int) (127 - ($progress * 118));
        $alpha = max(0, min(127, $alpha));
        
        $r = (int) (8 * (1 - $progress));
        $g = (int) (4 * (1 - $progress));
        $b = (int) (6 * (1 - $progress));
        
        $scrimColor = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
        imageline($img, 0, $y, $width, $y, $scrimColor);
    }

    // Top overlay per far risaltare il logo e la categoria in alto
    for ($y = 0; $y < 160; $y++) {
        $alpha = (int) (65 + ($y / 160) * 62);
        $topDark = imagecolorallocatealpha($img, 0, 0, 0, $alpha);
        imageline($img, 0, $y, $width, $y, $topDark);
    }

    // Colori
    $white     = imagecolorallocate($img, 255, 255, 255);
    $black     = imagecolorallocate($img, 0, 0, 0);
    $shadow    = imagecolorallocatealpha($img, 0, 0, 0, 40);
    $yellow    = imagecolorallocate($img, 255, 209, 0);
    $red       = imagecolorallocate($img, 225, 6, 0);
    $darkBadge = imagecolorallocatealpha($img, 15, 15, 22, 30);
    $lightGray = imagecolorallocate($img, 220, 220, 225);

    $fontBold    = $config['font_bold'] ?? (__DIR__ . '/../fonts/Montserrat-Bold.ttf');
    $fontRegular = $config['font_regular'] ?? (__DIR__ . '/../fonts/Montserrat-Regular.ttf');
    $useTtf = file_exists($fontBold) && file_exists($fontRegular);

    // 3. TOP BRAND BAR (Logo Badge & Categoria Pill)
    $brandText = 'FORMULA PADDOCK';
    $catText   = mb_strtoupper($categoria !== '' ? $categoria : 'F1 NEWS');

    if ($useTtf) {
        // Badge Rosso Logo a Sinistra
        drawRoundedRect($img, 45, 45, 260, 48, 10, $red);
        imagettftext($img, 15, 0, 65, 76, $white, $fontBold, $brandText);

        // Badge Categoria a Destra
        $catBox = imagettfbbox(13, 0, $fontBold, $catText);
        $catW = abs($catBox[2] - $catBox[0]) + 36;
        $catX = $width - 45 - $catW;
        drawRoundedRect($img, (int)$catX, 45, (int)$catW, 48, 10, $darkBadge);
        imagettftext($img, 13, 0, (int)($catX + 18), 75, $yellow, $fontBold, $catText);
    } else {
        imagestring($img, 5, 50, 50, $brandText, $yellow);
        imagestring($img, 4, $width - 160, 50, $catText, $white);
    }

    // 4. STRISCIA RACING ACCENT ANGOLATA SOPRA IL TESTO
    $accentY = (int) ($height * 0.44);
    $points = [
        45, $accentY + 8,
        140, $accentY + 8,
        160, $accentY,
        65, $accentY
    ];
    imagefilledpolygon($img, $points, $red);

    $pointsYellow = [
        168, $accentY,
        210, $accentY,
        190, $accentY + 8,
        148, $accentY + 8
    ];
    imagefilledpolygon($img, $pointsYellow, $yellow);

    // 5. TITOLO NOTIZIA IN GRASSETTO AD ALTO IMPATTO
    $titleFontSize = 44;
    $maxWidth = $width - 100;

    if ($useTtf) {
        $lines = wrapTextTtf(mb_strtoupper($title), $fontBold, $titleFontSize, $maxWidth);
        if (count($lines) > 4) {
            $titleFontSize = 38;
            $lines = wrapTextTtf(mb_strtoupper($title), $fontBold, $titleFontSize, $maxWidth);
        }
        
        $lineHeight = (int) ($titleFontSize * 1.30);
        $startY = $accentY + 58;

        foreach ($lines as $i => $line) {
            $curY = $startY + ($i * $lineHeight);
            // Ombra per massima leggibilità
            imagettftext($img, $titleFontSize, 0, 48, $curY + 3, $black, $fontBold, $line);
            imagettftext($img, $titleFontSize, 0, 47, $curY + 2, $shadow, $fontBold, $line);
            // Testo bianco principale
            imagettftext($img, $titleFontSize, 0, 45, $curY, $white, $fontBold, $line);
        }
        $afterTitleY = $startY + (count($lines) * $lineHeight) + 20;
    } else {
        $lines = wrapTextGd($title, 5, $maxWidth);
        $lineHeight = 22;
        $startY = $accentY + 40;
        foreach ($lines as $i => $line) {
            imagestring($img, 5, 45, $startY + $i * $lineHeight, $line, $white);
        }
        $afterTitleY = $startY + count($lines) * $lineHeight + 15;
    }

    // 6. SOTTOTITOLO CON BARRA ACCENTO GIALLA
    if ($subtitle !== '') {
        $subFontSize = 21;
        if ($useTtf) {
            $subLines = wrapTextTtf($subtitle, $fontRegular, $subFontSize, $maxWidth - 30);
            $subLineHeight = (int) ($subFontSize * 1.45);
            $totalSubH = count($subLines) * $subLineHeight;

            // Barra verticale gialla a sinistra del sottotitolo
            imagefilledrectangle($img, 45, $afterTitleY - 18, 50, $afterTitleY - 18 + $totalSubH, $yellow);

            foreach ($subLines as $i => $line) {
                $curSubY = $afterTitleY + ($i * $subLineHeight);
                imagettftext($img, $subFontSize, 0, 64, $curSubY + 2, $black, $fontRegular, $line);
                imagettftext($img, $subFontSize, 0, 62, $curSubY, $lightGray, $fontRegular, $line);
            }
        } else {
            $subLines = wrapTextGd($subtitle, 4, $maxWidth);
            foreach ($subLines as $i => $line) {
                imagestring($img, 4, 60, $afterTitleY + $i * 18, $line, $lightGray);
            }
        }
    }

    // 7. FOOTER BROADCAST / WATERMARK IN BASSO
    // Linea rossa di fondo a tutta larghezza
    imagefilledrectangle($img, 0, $height - 8, $width, $height, $red);

    $footerBrand = 'FORMULAPADDOCK.IT';
    $footerTag   = 'VISUAL STUDIO HD  •  F1 INSIDER';

    if ($useTtf) {
        imagettftext($img, 16, 0, 48, $height - 30, $black, $fontBold, $footerBrand);
        imagettftext($img, 16, 0, 45, $height - 32, $yellow, $fontBold, $footerBrand);

        $tagBox = imagettfbbox(12, 0, $fontBold, $footerTag);
        $tagW = abs($tagBox[2] - $tagBox[0]);
        imagettftext($img, 12, 0, (int)($width - 45 - $tagW), $height - 32, $lightGray, $fontBold, $footerTag);
    } else {
        imagestring($img, 5, 45, $height - 40, $footerBrand, $yellow);
        imagestring($img, 4, $width - 240, $height - 40, $footerTag, $lightGray);
    }

    if (!is_dir(dirname($outputPath))) {
        mkdir(dirname($outputPath), 0777, true);
    }
    imagejpeg($img, $outputPath, 92);
    imagedestroy($img);

    return $outputPath;
}

function wrapTextTtf(string $text, string $fontPath, int $fontSize, int $maxWidth): array
{
    $words = preg_split('/\s+/u', trim($text));
    $lines = [];
    $current = '';

    foreach ($words as $word) {
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
    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

function wrapTextGd(string $text, int $gdFont, int $maxWidth): array
{
    $charWidth = imagefontwidth($gdFont);
    $maxChars = max(10, (int) ($maxWidth / $charWidth));

    $words = preg_split('/\s+/u', trim($text));
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $test = $current === '' ? $word : $current . ' ' . $word;
        if (mb_strlen($test) > $maxChars && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

function generateAllInfographics(array $content, string $slug, array $config, ?string $bgImageUrl = null): array
{
    $title    = $content['infografica_titolo'] ?? '';
    $subtitle = $content['infografica_sottotitolo'] ?? '';
    $categoria = $content['categoria'] ?? '';

    $hdPath = $config['output_images_dir'] . "/visual_studio_hd.jpg";
    $fbPath = $config['output_images_dir'] . "/facebook.jpg";
    $igPath = $config['output_images_dir'] . "/instagram.jpg";

    generateInfographic($hdPath, 1080, 1080, $title, $subtitle, $categoria, $config, $bgImageUrl);
    @copy($hdPath, $fbPath);
    @copy($hdPath, $igPath);

    return [
        'hd_image' => $hdPath,
        'fb_image' => $fbPath,
        'ig_image' => $igPath,
    ];
}
