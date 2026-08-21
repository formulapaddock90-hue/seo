<?php
/**
 * Generazione di un breve video "Reel" verticale (1080x1920) a partire
 * dall'infografica Instagram, con effetto Ken Burns e testo sovraimpresso,
 * usando FFmpeg installato sul server.
 */

/**
 * Crea un video MP4 verticale (9:16) partendo da un'immagine statica.
 *
 * @param string $imagePath   Immagine sorgente (es. infografica Instagram, idealmente quadrata o verticale)
 * @param string $outputPath  Percorso del file .mp4 di destinazione
 * @param string $overlayText Testo breve da mostrare nel reel (reel_script)
 * @param array  $config
 * @param int    $durationSeconds
 * @return string Percorso del file creato
 * @throws Exception
 */
function generateReelVideo(
    string $imagePath,
    string $outputPath,
    string $overlayText,
    array $config,
    int $durationSeconds = 8
): string {
    if (!function_exists('exec')) {
        throw new Exception(
            'La funzione exec() e\' disabilitata su questo server (tipico degli hosting condivisi): ' .
            'impossibile usare FFmpeg, il reel non verra\' generato.'
        );
    }

    if (!is_dir(dirname($outputPath))) {
        mkdir(dirname($outputPath), 0777, true);
    }

    $ffmpeg = $config['ffmpeg_path'];

    // Verifica disponibilita' di ffmpeg
    exec(escapeshellarg($ffmpeg) . ' -version 2>&1', $checkOut, $checkCode);
    if ($checkCode !== 0) {
        throw new Exception('FFmpeg non disponibile sul server (controlla "ffmpeg_path" in config.php).');
    }

    $targetW = 1080;
    $targetH = 1920;
    $fps = 30;
    $totalFrames = $durationSeconds * $fps;

    // Effetto zoom (Ken Burns) leggero: zoompan da 1.0 a 1.15
    $zoompan = "scale=8000:-1,zoompan=z='min(zoom+0.0015,1.15)':d={$totalFrames}:s={$targetW}x{$targetH}:fps={$fps}";

    // Prepara il testo per drawtext (escape dei caratteri speciali)
    $escapedText = prepareDrawtextText($overlayText);

    $fontFile = $config['font_bold'];
    $hasFont = file_exists($fontFile);

    $drawtextFilter = '';
    if ($escapedText !== '') {
        $fontPart = $hasFont ? ":fontfile='" . str_replace(':', '\\:', $fontFile) . "'" : '';
        $drawtextFilter = ",drawtext=text='{$escapedText}'{$fontPart}:fontcolor=white:fontsize=58"
            . ":box=1:boxcolor=black@0.55:boxborderw=20"
            . ":x=(w-text_w)/2:y=h-(h*0.22):line_spacing=12";
    }

    $vf = $zoompan . $drawtextFilter;

    $cmd = sprintf(
        '%s -y -loop 1 -i %s -t %d -vf %s -c:v libx264 -pix_fmt yuv420p -movflags +faststart %s 2>&1',
        escapeshellarg($ffmpeg),
        escapeshellarg($imagePath),
        $durationSeconds,
        escapeshellarg($vf),
        escapeshellarg($outputPath)
    );

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($outputPath)) {
        throw new Exception("Errore nella generazione del video reel:\n" . implode("\n", $output));
    }

    return $outputPath;
}

/**
 * Prepara una stringa di testo per essere usata nel filtro drawtext di ffmpeg,
 * gestendo l'escaping dei caratteri speciali e andando a capo automaticamente.
 */
function prepareDrawtextText(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    // Spezza in righe brevi (max ~28 caratteri) per evitare overflow
    $words = preg_split('/\s+/u', $text);
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $test = $current === '' ? $word : $current . ' ' . $word;
        if (mb_strlen($test) > 28 && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    $text = implode("\n", $lines);

    // Escape caratteri speciali per ffmpeg drawtext
    $text = str_replace(['\\', ':', "'", '%'], ['\\\\', '\\:', "\u{2019}", '\\%'], $text);
    $text = str_replace("\n", '\\n', $text);

    return $text;
}
