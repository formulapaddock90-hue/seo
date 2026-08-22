<?php
/**
 * Diagnostica FFmpeg per FormulaPaddock Social.
 * Non mostra variabili d'ambiente, token o segreti.
 */

$config = require __DIR__ . '/config.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function fpFunctionAvailable(string $name): bool
{
    if (!function_exists($name)) {
        return false;
    }

    $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
    return !in_array($name, $disabled, true);
}

function fpRunVersionCheck(string $candidate): array
{
    $result = [
        'candidate' => $candidate,
        'ok' => false,
        'version' => '',
        'code' => null,
    ];

    if (!fpFunctionAvailable('exec')) {
        return $result;
    }

    $output = [];
    $code = 1;
    $command = escapeshellarg($candidate) . ' -version 2>&1';
    exec($command, $output, $code);

    $result['code'] = $code;
    if ($code === 0 && !empty($output)) {
        $result['ok'] = true;
        $result['version'] = trim((string)$output[0]);
    }

    return $result;
}

$execAvailable = fpFunctionAvailable('exec');
$outputDir = $config['output_reels_dir'] ?? (__DIR__ . '/output/reels');
$outputDirExists = is_dir($outputDir);
$outputDirWritable = $outputDirExists && is_writable($outputDir);

$candidates = array_values(array_unique(array_filter([
    $config['ffmpeg_path'] ?? null,
    __DIR__ . '/bin/ffmpeg-n7.1-latest-win64-gpl-7.1/bin/ffmpeg.exe',
    __DIR__ . '/bin/ffmpeg.exe',
    'ffmpeg',
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg',
    '/opt/ffmpeg/bin/ffmpeg',
])));

$checks = [];
$found = null;

if ($execAvailable) {
    foreach ($candidates as $candidate) {
        $check = fpRunVersionCheck((string)$candidate);
        $checks[] = $check;
        if ($check['ok']) {
            $found = $check;
            break;
        }
    }
}

$ready = $execAvailable && $found !== null && $outputDirWritable;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Test FFmpeg — FormulaPaddock</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px 18px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #09090d;
            color: #f5f5f5;
        }
        .wrap { max-width: 900px; margin: 0 auto; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        .sub { color: #aaa; margin: 0 0 26px; }
        .hero, .card {
            border: 1px solid #2b2b34;
            border-radius: 14px;
            background: #131319;
            padding: 20px;
            margin-bottom: 16px;
        }
        .hero.ok { border-color: #1f9d62; }
        .hero.bad { border-color: #c94545; }
        .hero h2 { margin: 0 0 8px; font-size: 22px; }
        .ok-text { color: #57df96; }
        .bad-text { color: #ff7777; }
        .warn-text { color: #ffd166; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .item {
            background: #0d0d12;
            border: 1px solid #24242d;
            border-radius: 10px;
            padding: 14px;
        }
        .label { color: #8f8f9c; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
        .value { margin-top: 6px; font-size: 15px; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #2a2a32; vertical-align: top; }
        th { color: #aaa; font-size: 12px; text-transform: uppercase; }
        code { color: #ffd166; word-break: break-all; }
        .pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .pill.ok { background: rgba(31,157,98,.16); color: #57df96; }
        .pill.bad { background: rgba(201,69,69,.16); color: #ff7777; }
        a { color: #ffd100; }
        .note { color: #aaa; font-size: 13px; line-height: 1.55; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🏎️ Test FFmpeg FormulaPaddock</h1>
    <p class="sub">Controllo rapido del server per generare Reel 1080×1920 senza OnRender.</p>

    <section class="hero <?= $ready ? 'ok' : 'bad' ?>">
        <?php if ($ready): ?>
            <h2 class="ok-text">✅ Server pronto per i Reel locali</h2>
            <p>FFmpeg è disponibile, <code>exec()</code> funziona e la cartella Reel è scrivibile.</p>
        <?php else: ?>
            <h2 class="bad-text">❌ Server non ancora pronto</h2>
            <p>Guarda i controlli qui sotto: viene indicato esattamente cosa manca.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="grid">
            <div class="item">
                <div class="label">PHP</div>
                <div class="value"><?= htmlspecialchars(PHP_VERSION) ?></div>
            </div>
            <div class="item">
                <div class="label">Sistema</div>
                <div class="value"><?= htmlspecialchars(PHP_OS_FAMILY) ?></div>
            </div>
            <div class="item">
                <div class="label">exec()</div>
                <div class="value">
                    <span class="pill <?= $execAvailable ? 'ok' : 'bad' ?>"><?= $execAvailable ? 'ABILITATO' : 'BLOCCATO' ?></span>
                </div>
            </div>
            <div class="item">
                <div class="label">Cartella Reel scrivibile</div>
                <div class="value">
                    <span class="pill <?= $outputDirWritable ? 'ok' : 'bad' ?>"><?= $outputDirWritable ? 'SÌ' : 'NO' ?></span>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>FFmpeg</h2>
        <?php if (!$execAvailable): ?>
            <p class="bad-text"><strong>PHP non consente exec().</strong> In questa configurazione il server non può avviare FFmpeg.</p>
        <?php elseif ($found): ?>
            <p class="ok-text"><strong>FFmpeg trovato.</strong></p>
            <p><code><?= htmlspecialchars($found['candidate']) ?></code></p>
            <p><?= htmlspecialchars($found['version']) ?></p>
        <?php else: ?>
            <p class="bad-text"><strong>FFmpeg non trovato nei percorsi testati.</strong></p>
        <?php endif; ?>

        <?php if (!empty($checks)): ?>
            <table>
                <thead>
                    <tr><th>Percorso</th><th>Esito</th></tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($check['candidate']) ?></code></td>
                        <td>
                            <span class="pill <?= $check['ok'] ? 'ok' : 'bad' ?>">
                                <?= $check['ok'] ? 'TROVATO' : 'NO' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Cartella di output</h2>
        <p><code><?= htmlspecialchars($outputDir) ?></code></p>
        <?php if (!$outputDirExists): ?>
            <p class="warn-text">⚠️ La cartella non esiste ancora.</p>
        <?php elseif (!$outputDirWritable): ?>
            <p class="bad-text">❌ La cartella esiste ma PHP non può scriverci.</p>
        <?php else: ?>
            <p class="ok-text">✅ La cartella esiste ed è scrivibile.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Prossimo passo</h2>
        <?php if ($ready): ?>
            <p class="ok-text"><strong>Possiamo eliminare OnRender e collegare direttamente FFmpeg al generatore Reel.</strong></p>
        <?php else: ?>
            <p>Prima di togliere OnRender dobbiamo risolvere i controlli segnati in rosso.</p>
        <?php endif; ?>
        <p class="note">Questa pagina non visualizza chiavi API, token, variabili d'ambiente o credenziali.</p>
        <p><a href="index.php">← Torna al Social Hub</a></p>
    </section>
</div>
</body>
</html>
