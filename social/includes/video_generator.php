<?php
/**
 * Generazione Reel legacy via FFmpeg locale.
 * Il flusso di produzione usa reel_builder.php nel browser; nessun fallback cloud.
 */

function generateReelVideo(
    string $imagePath,
    string $outputPath,
    string $overlayText,
    array $config,
    int $durationSeconds = 15
): string {
    if (!is_dir(dirname($outputPath))) {
        mkdir(dirname($outputPath), 0777, true);
    }

    if (!function_exists('exec')) {
        throw new Exception('FFmpeg server non disponibile: usa il Reel Builder browser-side.');
    }

    $ffmpegCandidatePaths = array_filter([
        $config['ffmpeg_path'] ?? null,
        __DIR__ . '/../bin/ffmpeg-n7.1-latest-win64-gpl-7.1/bin/ffmpeg.exe',
        __DIR__ . '/../bin/ffmpeg.exe',
        'ffmpeg',
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/opt/ffmpeg/bin/ffmpeg'
    ]);

    $ffmpeg = null;
    foreach ($ffmpegCandidatePaths as $candidate) {
        $checkOut = [];
        $checkCode = 1;
        exec(escapeshellarg($candidate) . ' -version 2>&1', $checkOut, $checkCode);
        if ($checkCode === 0) {
            $ffmpeg = $candidate;
            break;
        }
    }

    if (!$ffmpeg) {
        throw new Exception('FFmpeg non trovato: usa il Reel Builder browser-side.');
    }

    return generateLocalFfmpegReel($ffmpeg, $imagePath, $outputPath, $overlayText, $config, $durationSeconds);
}

function generateLocalFfmpegReel($ffmpeg, $imagePath, $outputPath, $overlayText, $config, $durationSeconds = 15)
{
    $audioPath = getFolderRandomMp3($config['reel_music_dir'] ?? (__DIR__ . '/../music'));
    $targetW = 1080;
    $targetH = 1920;
    $fps = 30;
    $totalFrames = $durationSeconds * $fps;

    $cleanText = trim($overlayText);
    if ($cleanText === '') {
        $cleanText = "FORMULAPADDOCK.IT • REEL F1\nLEGGI SU FORMULAPADDOCK.IT";
    }

    $escapedText = prepareDrawtextText($cleanText);
    $ctaText = prepareDrawtextText('SEGUI FORMULAPADDOCK.IT SU INSTAGRAM E TIKTOK');
    $fontFile = $config['font_bold'] ?? '';
    $fontPart = file_exists($fontFile) ? ":fontfile='" . str_replace(':', '\\:', $fontFile) . "'" : '';

    $vfFilters = [
        'scale=2160:-1',
        "zoompan=z='min(zoom+0.0015,1.15)':d={$totalFrames}:s={$targetW}x{$targetH}:fps={$fps}",
        'drawbox=x=40:y=60:w=420:h=80:color=black@0.85:t=fill',
        'drawbox=x=40:y=60:w=420:h=80:color=#e10600@1.0:t=4',
        "drawtext=text='FORMULAPADDOCK.IT'{$fontPart}:fontcolor=white:fontsize=32:x=60:y=85",
        'drawbox=x=60:y=' . ($targetH - 360) . ':w=' . ($targetW - 120) . ':h=240:color=black@0.9:t=fill',
        'drawbox=x=60:y=' . ($targetH - 360) . ':w=16:h=240:color=#e10600@1.0:t=fill',
        "drawtext=text='FORMULAPADDOCK.IT • REEL F1'{$fontPart}:fontcolor=#ffeb3b:fontsize=26:x=100:y=" . ($targetH - 320),
        "drawtext=text='{$escapedText}'{$fontPart}:fontcolor=white:fontsize=44:x=100:y=" . ($targetH - 260) . ':line_spacing=12',
        "drawtext=text='{$ctaText}'{$fontPart}:fontcolor=white:fontsize=30:x=(w-text_w)/2:y=" . ($targetH - 70)
    ];
    $vf = implode(',', $vfFilters);

    $audioArgs = '';
    $audioCodec = '';
    if ($audioPath && file_exists($audioPath)) {
        $audioArgs = ' -i ' . escapeshellarg($audioPath);
        $audioCodec = ' -c:a aac -b:a 192k -shortest';
    }

    $cmd = sprintf(
        '%s -y -loop 1 -i %s%s -t %d -vf %s -c:v libx264 -preset medium -crf 18%s -pix_fmt yuv420p -movflags +faststart %s 2>&1',
        escapeshellarg($ffmpeg),
        escapeshellarg($imagePath),
        $audioArgs,
        $durationSeconds,
        escapeshellarg($vf),
        $audioCodec,
        escapeshellarg($outputPath)
    );

    $output = [];
    $returnCode = 1;
    exec($cmd, $output, $returnCode);
    if ($returnCode !== 0 || !file_exists($outputPath)) {
        throw new Exception("Errore FFmpeg locale:\n" . implode("\n", $output));
    }

    return $outputPath;
}

function getFolderRandomMp3(string $musicDir): ?string
{
    if (!is_dir($musicDir) || !is_readable($musicDir)) return null;
    $files = glob(rtrim($musicDir, '/\\') . '/*.{mp3,m4a,aac,wav,ogg}', GLOB_BRACE) ?: [];
    $files = array_values(array_filter($files, 'is_file'));
    return $files ? $files[array_rand($files)] : null;
}

function prepareDrawtextText(string $text): string
{
    $text = trim($text);
    if ($text === '') return '';

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
    if ($current !== '') $lines[] = $current;
    $text = implode("\n", $lines);
    $text = str_replace(['\\', ':', "'", '%'], ['\\\\', '\\:', "\u{2019}", '\\%'], $text);
    return str_replace("\n", '\\n', $text);
}
