<?php
/**
 * Generazione delle infografiche (immagini JPG) per Facebook e Instagram
 * usando la libreria GD di PHP.
 */

/**
 * Crea un'immagine infografica con sfondo a gradiente, titolo e sottotitolo.
 *
 * @param string $outputPath  Percorso file di destinazione (.jpg)
 * @param int    $width
 * @param int    $height
 * @param string $title
 * @param string $subtitle
 * @param string $categoria
 * @param array  $config
 * @return string Percorso del file creato
 */
function generateInfographic(
    string $outputPath,
    int $width,
    int $height,
    string $title,
    string $subtitle,
    string $categoria,
    array $config
): string {
    $img = imagecreatetruecolor($width, $height);

    // ---- Sfondo a gradiente (stile "racing", nero -> rosso scuro) ----
    $colorTop    = [10, 10, 15];
    $colorBottom = [120, 10, 20];

    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int) ($colorTop[0] + ($colorBottom[0] - $colorTop[0]) * $ratio);
        $g = (int) ($colorTop[1] + ($colorBottom[1] - $colorTop[1]) * $ratio);
        $b = (int) ($colorTop[2] + ($colorBottom[2] - $colorTop[2]) * $ratio);
        $color = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $width, $y, $color);
    }

    // ---- Banda diagonale decorativa ----
    $accent = imagecolorallocatealpha($img, 255, 24, 24, 60);
    $points = [
        0, (int) ($height * 0.65),
        $width, (int) ($height * 0.45),
        $width, (int) ($height * 0.55),
        0, (int) ($height * 0.75),
    ];
    imagefilledpolygon($img, $points, $accent);

    // ---- Colori testo ----
    $white  = imagecolorallocate($img, 255, 255, 255);
    $yellow = imagecolorallocate($img, 255, 209, 0);

    $fontBold    = $config['font_bold'];
    $fontRegular = $config['font_regular'];
    $useTtf = file_exists($fontBold) && file_exists($fontRegular);

    // ---- Etichetta categoria (in alto) ----
    $categoriaLabel = mb_strtoupper($categoria !== '' ? $categoria : 'F1 NEWS');
    if ($useTtf) {
        imagettftext($img, 22, 0, 40, 70, $yellow, $fontBold, $categoriaLabel);
    } else {
        imagestring($img, 5, 40, 40, $categoriaLabel, $yellow);
    }

    // ---- Titolo (wrap automatico) ----
    $titleFontSize = $width >= 1080 ? 60 : 46;
    $maxWidth = $width - 80;

    if ($useTtf) {
        $lines = wrapTextTtf($title, $fontBold, $titleFontSize, $maxWidth);
        $lineHeight = (int) ($titleFontSize * 1.3);
        $startY = (int) ($height * 0.45);
        foreach ($lines as $i => $line) {
            imagettftext($img, $titleFontSize, 0, 40, $startY + $i * $lineHeight, $white, $fontBold, $line);
        }
        $afterTitleY = $startY + count($lines) * $lineHeight + 20;
    } else {
        $lines = wrapTextGd($title, 5, $maxWidth);
        $lineHeight = 20;
        $startY = (int) ($height * 0.45);
        foreach ($lines as $i => $line) {
            imagestring($img, 5, 40, $startY + $i * $lineHeight, $line, $white);
        }
        $afterTitleY = $startY + count($lines) * $lineHeight + 10;
    }

    // ---- Sottotitolo ----
    if ($subtitle !== '') {
        $subFontSize = $width >= 1080 ? 28 : 22;
        if ($useTtf) {
            $subLines = wrapTextTtf($subtitle, $fontRegular, $subFontSize, $maxWidth);
            $subLineHeight = (int) ($subFontSize * 1.4);
            foreach ($subLines as $i => $line) {
                imagettftext($img, $subFontSize, 0, 40, $afterTitleY + $i * $subLineHeight, $white, $fontRegular, $line);
            }
        } else {
            $subLines = wrapTextGd($subtitle, 4, $maxWidth);
            foreach ($subLines as $i => $line) {
                imagestring($img, 4, 40, $afterTitleY + $i * 18, $line, $white);
            }
        }
    }

    // ---- Logo / firma in basso ----
    $footer = 'F1 INSIDER';
    if ($useTtf) {
        imagettftext($img, 20, 0, 40, $height - 30, $yellow, $fontBold, $footer);
    } else {
        imagestring($img, 4, 40, $height - 30, $footer, $yellow);
    }

    // ---- Salvataggio JPG ----
    if (!is_dir(dirname($outputPath))) {
        mkdir(dirname($outputPath), 0777, true);
    }
    imagejpeg($img, $outputPath, 90);
    imagedestroy($img);

    return $outputPath;
}

/**
 * Divide un testo in piu' righe in base alla larghezza massima (font TTF).
 */
function wrapTextTtf(string $text, string $fontPath, int $fontSize, int $maxWidth): array
{
    $words = preg_split('/\s+/u', trim($text));
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $test = $current === '' ? $word : $current . ' ' . $word;
        $box = imagettfbbox($fontSize, 0, $fontPath, $test);
        $textWidth = $box[2] - $box[0];

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

/**
 * Divide un testo in piu' righe usando i font interni di GD (fallback senza TTF).
 */
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

/**
 * Genera le due infografiche richieste (Facebook 1200x630, Instagram 1080x1080)
 * e restituisce i percorsi locali dei file creati.
 *
 * @return array{fb_image:string, ig_image:string}
 */
function generateAllInfographics(array $content, string $slug, array $config): array
{
    $title    = $content['infografica_titolo'] ?? '';
    $subtitle = $content['infografica_sottotitolo'] ?? '';
    $categoria = $content['categoria'] ?? '';

    // Nomi file fissi: ogni generazione sovrascrive la precedente (anche su Drive)
    $fbPath = $config['output_images_dir'] . "/facebook.jpg";
    $igPath = $config['output_images_dir'] . "/instagram.jpg";

    generateInfographic($fbPath, 1200, 630, $title, $subtitle, $categoria, $config);
    generateInfographic($igPath, 1080, 1080, $title, $subtitle, $categoria, $config);

    return [
        'fb_image' => $fbPath,
        'ig_image' => $igPath,
    ];
}
