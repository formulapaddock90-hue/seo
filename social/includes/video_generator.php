<?php
/**
 * Generazione di Reel 9:16 (1080x1920) verticale FormulaPaddock
 * Rendering 100% Diretto: FFmpeg locale o Cloud Render API (https://reel-engine-dcnr.onrender.com/api/render-reel)
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

    $cloudUrl = $config['reel_cloud_url'] ?? 'https://reel-engine-dcnr.onrender.com';

    // 1. TENTA LA GENERAZIONE TRAMITE FFMPEG LOCALE (Se disponibile su Linux/Windows host)
    if (function_exists('exec')) {
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
            if (empty($candidate)) continue;
            $checkCmd = escapeshellarg($candidate) . ' -version 2>&1';
            exec($checkCmd, $checkOut, $checkCode);
            if ($checkCode === 0) {
                $ffmpeg = $candidate;
                break;
            }
        }

        if ($ffmpeg) {
            return generateLocalFfmpegReel($ffmpeg, $imagePath, $outputPath, $overlayText, $config, $durationSeconds);
        }
    }

    // 2. RENDERING DIRETTO 100% CLOUD RENDERING API (Genera MP4 direttamente in social con Retry automatico)
    return generateCloudEngineReel($cloudUrl, $imagePath, $outputPath, $overlayText, $config);
}

function generateLocalFfmpegReel($ffmpeg, $imagePath, $outputPath, $overlayText, $config, $durationSeconds = 15)
{
    $audioPath = getFolderRandomMp3();

    $targetW = 1080;
    $targetH = 1920;
    $fps = 30;
    $totalFrames = $durationSeconds * $fps;

    $cleanText = trim($overlayText);
    if (empty($cleanText)) {
        $cleanText = "FORMULAPADDOCK.IT • REEL F1\nLEGGISU FORMULAPADDOCK.IT";
    }

    $escapedText = prepareDrawtextText($cleanText);
    $ctaText = prepareDrawtextText("SEGUI FORMULAPADDOCK.IT SU INSTAGRAM E TIKTOK");

    $fontFile = $config['font_bold'] ?? '';
    $hasFont = file_exists($fontFile);
    $fontPart = $hasFont ? ":fontfile='" . str_replace(':', '\\:', $fontFile) . "'" : '';

    $vfFilters = [
        "scale=2160:-1",
        "zoompan=z='min(zoom+0.0015,1.15)':d={$totalFrames}:s={$targetW}x{$targetH}:fps={$fps}",
        "drawbox=x=40:y=60:w=420:h=80:color=black@0.85:t=fill",
        "drawbox=x=40:y=60:w=420:h=80:color=#e10600@1.0:t=4",
        "drawtext=text='FORMULAPADDOCK.IT'{$fontPart}:fontcolor=white:fontsize=32:x=60:y=85",
        "drawbox=x=" . ($targetW - 360) . ":y=60:w=320:h=160:color=black@0.85:t=fill",
        "drawbox=x=" . ($targetW - 360) . ":y=60:w=320:h=160:color=white@0.2:t=3",
        "drawtext=text='334 KM/H'{$fontPart}:fontcolor=#ffeb3b:fontsize=48:x=" . ($targetW - 330) . ":y=90",
        "drawtext=text='DRS ATTIVO'{$fontPart}:fontcolor=#00e676:fontsize=22:x=" . ($targetW - 330) . ":y=160",
        "drawbox=x=60:y=" . ($targetH - 360) . ":w=" . ($targetW - 120) . ":h=240:color=black@0.9:t=fill:enable='lt(t,12)'",
        "drawbox=x=60:y=" . ($targetH - 360) . ":w=16:h=240:color=#e10600@1.0:t=fill:enable='lt(t,12)'",
        "drawtext=text='FORMULAPADDOCK.IT • REEL F1'{$fontPart}:fontcolor=#ffeb3b:fontsize=26:x=100:y=" . ($targetH - 320) . ":enable='lt(t,12)'",
        "drawtext=text='{$escapedText}'{$fontPart}:fontcolor=white:fontsize=44:x=100:y=" . ($targetH - 260) . ":line_spacing=12:enable='lt(t,12)'",
        "drawbox=x=0:y=0:w={$targetW}:h={$targetH}:color=#08090d@0.98:t=fill:enable='gte(t,12)'",
        "drawbox=x=40:y=40:w=" . ($targetW - 80) . ":h=" . ($targetH - 80) . ":color=#e10600@1.0:t=4:enable='gte(t,12)'",
        "drawbox=x=80:y=240:w=" . ($targetW - 160) . ":h=" . ($targetH - 480) . ":color=black@0.9:t=fill:enable='gte(t,12)'",
        "drawtext=text='FORMULAPADDOCK.IT'{$fontPart}:fontcolor=white:fontsize=64:x=(w-text_w)/2:y=480:enable='gte(t,12)'",
        "drawtext=text='{$ctaText}'{$fontPart}:fontcolor=white:fontsize=40:x=(w-text_w)/2:y=760:line_spacing=14:enable='gte(t,12)'",
        "drawbox=x=" . ($targetW/2 - 220) . ":y=1150:w=440:h=100:color=#e10600@1.0:t=fill:enable='gte(t,12)'",
        "drawtext=text='SEGUI ORA'{$fontPart}:fontcolor=white:fontsize=40:x=(w-text_w)/2:y=1182:enable='gte(t,12)'"
    ];

    $vf = implode(',', $vfFilters);

    if ($audioPath && file_exists($audioPath)) {
        $cmd = sprintf(
            '%s -y -loop 1 -i %s -i %s -t %d -vf %s -c:v libx264 -preset ultrafast -tune zerolatency -c:a aac -b:a 192k -pix_fmt yuv420p -shortest -movflags +faststart %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($imagePath),
            escapeshellarg($audioPath),
            $durationSeconds,
            escapeshellarg($vf),
            escapeshellarg($outputPath)
        );
    } else {
        $cmd = sprintf(
            '%s -y -loop 1 -i %s -t %d -vf %s -c:v libx264 -preset ultrafast -tune zerolatency -pix_fmt yuv420p -movflags +faststart %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($imagePath),
            $durationSeconds,
            escapeshellarg($vf),
            escapeshellarg($outputPath)
        );
    }

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($outputPath)) {
        $simpleVf = "scale={$targetW}:{$targetH}:force_original_aspect_ratio=increase,crop={$targetW}:{$targetH}";
        $cmdFallback = sprintf(
            '%s -y -loop 1 -i %s -t %d -vf %s -c:v libx264 -preset ultrafast -tune zerolatency -pix_fmt yuv420p -movflags +faststart %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($imagePath),
            $durationSeconds,
            escapeshellarg($simpleVf),
            escapeshellarg($outputPath)
        );
        exec($cmdFallback, $out2, $code2);
        
        if ($code2 !== 0 || !file_exists($outputPath)) {
            throw new Exception("Errore generico FFmpeg:\n" . implode("\n", $output));
        }
    }

    return $outputPath;
}

function generateCloudEngineReel(string $cloudUrl, string $imagePath, string $outputPath, string $overlayText, array $config): string
{
    $renderApiUrl = rtrim($cloudUrl, '/') . '/api/render-reel';
    
    $webImageUrl = '';
    if (!empty($_SERVER['HTTP_HOST']) && file_exists($imagePath)) {
        $scheme = (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) ? 'https' : 'http';
        $webImageUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/seo/social/output/images/' . basename($imagePath);
    }

    $payload = json_encode([
        'text' => $overlayText,
        'image_url' => $webImageUrl
    ]);

    // Tenta fino a 2 volte in caso di risveglio da idle del server Render
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init($renderApiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json']
        ]);

        $videoBytes = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $videoBytes && strlen($videoBytes) > 1000) {
            file_put_contents($outputPath, $videoBytes);
            return $outputPath;
        }

        if ($attempt < 2) {
            sleep(2);
        }
    }

    throw new Exception("Rendering Reel Cloud (HTTP {$httpCode}): " . ($curlErr ?: "Inizializzazione server in corso, riprova tra pochi secondi."));
}

function getFolderRandomMp3(): ?string
{
    $downloadsDir = $_SERVER['USERPROFILE'] ?? 'C:\\Users\\formu';
    $downloadsPath = $downloadsDir . '\\Downloads';

    $mp3Files = [];
    if (is_dir($downloadsPath)) {
        foreach (scandir($downloadsPath) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'mp3') {
                $mp3Files[] = $downloadsPath . '\\' . $file;
            }
        }
    }

    if (!empty($mp3Files)) {
        return $mp3Files[array_rand($mp3Files)];
    }

    return null;
}

function prepareDrawtextText(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

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

    $text = str_replace(['\\', ':', "'", '%'], ['\\\\', '\\:', "\u{2019}", '\\%'], $text);
    $text = str_replace("\n", '\\n', $text);

    return $text;
}
