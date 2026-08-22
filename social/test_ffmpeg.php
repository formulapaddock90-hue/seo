<?php
/**
 * Diagnostica FFmpeg per FormulaPaddock Social.
 * Verifica se PHP puo avviare FFmpeg anche quando exec() e disabilitato.
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

function fpAvailableRunners(): array
{
    $names = ['exec', 'proc_open', 'shell_exec', 'system', 'passthru', 'popen'];
    $result = [];
    foreach ($names as $name) {
        $result[$name] = fpFunctionAvailable($name);
    }
    return $result;
}

function fpRunCommand(string $command): array
{
    $result = [
        'ok' => false,
        'runner' => '',
        'code' => null,
        'output' => '',
    ];

    if (fpFunctionAvailable('exec')) {
        $lines = [];
        $code = 1;
        exec($command . ' 2>&1', $lines, $code);
        return [
            'ok' => $code === 0,
            'runner' => 'exec',
            'code' => $code,
            'output' => trim(implode("\n", $lines)),
        ];
    }

    if (fpFunctionAvailable('proc_open')) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = @proc_open($command, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
            return [
                'ok' => $code === 0,
                'runner' => 'proc_open',
                'code' => $code,
                'output' => trim((string)$stdout . "\n" . (string)$stderr),
            ];
        }
    }

    if (fpFunctionAvailable('shell_exec')) {
        $output = @shell_exec($command . ' 2>&1');
        $text = trim((string)$output);
        return [
            'ok' => $text !== '' && stripos($text, 'ffmpeg version') !== false,
            'runner' => 'shell_exec',
            'code' => null,
            'output' => $text,
        ];
    }

    if (fpFunctionAvailable('system')) {
        ob_start();
        $code = 1;
        @system($command . ' 2>&1', $code);
        $output = trim((string)ob_get_clean());
        return [
            'ok' => $code === 0,
            'runner' => 'system',
            'code' => $code,
            'output' => $output,
        ];
    }

    if (fpFunctionAvailable('passthru')) {
        ob_start();
        $code = 1;
        @passthru($command . ' 2>&1', $code);
        $output = trim((string)ob_get_clean());
        return [
            'ok' => $code === 0,
            'runner' => 'passthru',
            'code' => $code,
            'output' => $output,
        ];
    }

    if (fpFunctionAvailable('popen')) {
        $handle = @popen($command . ' 2>&1', 'r');
        if (is_resource($handle)) {
            $output = '';
            while (!feof($handle)) {
                $output .= fgets($handle);
            }
            $code = pclose($handle);
            $output = trim($output);
            return [
                'ok' => $code === 0 || stripos($output, 'ffmpeg version') !== false,
                'runner' => 'popen',
                'code' => $code,
                'output' => $output,
            ];
        }
    }

    return $result;
}

function fpRunVersionCheck(string $candidate): array
{
    $command = escapeshellarg($candidate) . ' -version';
    $run = fpRunCommand($command);
    $firstLine = '';

    if ($run['output'] !== '') {
        $lines = preg_split('/\R/', $run['output']);
        $firstLine = trim((string)($lines[0] ?? ''));
    }

    return [
        'candidate' => $candidate,
        'ok' => (bool)$run['ok'] && stripos($run['output'], 'ffmpeg') !== false,
        'version' => $firstLine,
        'code' => $run['code'],
        'runner' => $run['runner'],
    ];
}

$runners = fpAvailableRunners();
$anyRunner = in_array(true, $runners, true);
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

if ($anyRunner) {
    foreach ($candidates as $candidate) {
        $check = fpRunVersionCheck((string)$candidate);
        $checks[] = $check;
        if ($check['ok']) {
            $found = $check;
            break;
        }
    }
}

$ready = $anyRunner && $found !== null && $outputDirWritable;
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
        body { margin:0; padding:32px 18px; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; background:#09090d; color:#f5f5f5; }
        .wrap { max-width:900px; margin:0 auto; }
        h1 { margin:0 0 8px; font-size:28px; }
        .sub { color:#aaa; margin:0 0 26px; }
        .hero,.card { border:1px solid #2b2b34; border-radius:14px; background:#131319; padding:20px; margin-bottom:16px; }
        .hero.ok { border-color:#1f9d62; }
        .hero.bad { border-color:#c94545; }
        .hero h2 { margin:0 0 8px; font-size:22px; }
        .ok-text { color:#57df96; }
        .bad-text { color:#ff7777; }
        .warn-text { color:#ffd166; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .item { background:#0d0d12; border:1px solid #24242d; border-radius:10px; padding:14px; }
        .label { color:#8f8f9c; font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
        .value { margin-top:6px; font-size:15px; overflow-wrap:anywhere; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th,td { text-align:left; padding:10px; border-bottom:1px solid #2a2a32; vertical-align:top; }
        th { color:#aaa; font-size:12px; text-transform:uppercase; }
        code { color:#ffd166; word-break:break-all; }
        .pill { display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill.ok { background:rgba(31,157,98,.16); color:#57df96; }
        .pill.bad { background:rgba(201,69,69,.16); color:#ff7777; }
        a { color:#ffd100; }
        .note { color:#aaa; font-size:13px; line-height:1.55; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🏎️ Test FFmpeg FormulaPaddock</h1>
    <p class="sub">Controllo completo del server per generare Reel 1080×1920 senza OnRender.</p>

    <section class="hero <?= $ready ? 'ok' : 'bad' ?>">
        <?php if ($ready): ?>
            <h2 class="ok-text">✅ Server pronto per i Reel locali</h2>
            <p>FFmpeg è disponibile tramite <strong><?= htmlspecialchars($found['runner']) ?></strong> e la cartella Reel è scrivibile.</p>
        <?php else: ?>
            <h2 class="bad-text">❌ Server non ancora pronto</h2>
            <p>Ora vengono controllati anche i metodi alternativi a <code>exec()</code>.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="grid">
            <div class="item"><div class="label">PHP</div><div class="value"><?= htmlspecialchars(PHP_VERSION) ?></div></div>
            <div class="item"><div class="label">Sistema</div><div class="value"><?= htmlspecialchars(PHP_OS_FAMILY) ?></div></div>
            <div class="item"><div class="label">Cartella Reel</div><div class="value"><span class="pill <?= $outputDirWritable ? 'ok' : 'bad' ?>"><?= $outputDirWritable ? 'SCRIVIBILE' : 'NON SCRIVIBILE' ?></span></div></div>
        </div>
    </section>

    <section class="card">
        <h2>Metodi PHP per avviare FFmpeg</h2>
        <div class="grid">
            <?php foreach ($runners as $name => $available): ?>
                <div class="item">
                    <div class="label"><?= htmlspecialchars($name) ?>()</div>
                    <div class="value"><span class="pill <?= $available ? 'ok' : 'bad' ?>"><?= $available ? 'ABILITATO' : 'BLOCCATO' ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$anyRunner): ?>
            <p class="bad-text"><strong>Tutti i metodi di esecuzione esterna risultano bloccati.</strong></p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>FFmpeg</h2>
        <?php if (!$anyRunner): ?>
            <p class="bad-text">PHP non dispone di alcun metodo utilizzabile per avviare programmi esterni.</p>
        <?php elseif ($found): ?>
            <p class="ok-text"><strong>FFmpeg trovato e avviabile.</strong></p>
            <p>Metodo usato: <strong><?= htmlspecialchars($found['runner']) ?>()</strong></p>
            <p><code><?= htmlspecialchars($found['candidate']) ?></code></p>
            <p><?= htmlspecialchars($found['version']) ?></p>
        <?php else: ?>
            <p class="bad-text"><strong>Un metodo PHP è disponibile, ma FFmpeg non è stato trovato nei percorsi testati.</strong></p>
        <?php endif; ?>

        <?php if (!empty($checks)): ?>
            <table>
                <thead><tr><th>Percorso</th><th>Metodo</th><th>Esito</th></tr></thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($check['candidate']) ?></code></td>
                        <td><?= htmlspecialchars($check['runner'] ?: '—') ?></td>
                        <td><span class="pill <?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'TROVATO' : 'NO' ?></span></td>
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
            <p class="ok-text"><strong>Possiamo togliere OnRender e adattare il generatore Reel al metodo PHP disponibile.</strong></p>
        <?php elseif (!$anyRunner): ?>
            <p>Se anche questa versione mostra tutto bloccato, il Reel non può essere renderizzato dal PHP del server. In quel caso useremo una soluzione senza OnRender e senza esecuzione server.</p>
        <?php else: ?>
            <p>PHP può avviare programmi esterni, ma dobbiamo rendere FFmpeg disponibile sul server o individuarne il percorso corretto.</p>
        <?php endif; ?>
        <p class="note">Questa pagina non visualizza chiavi API, token, variabili d'ambiente o credenziali.</p>
        <p><a href="index.php">← Torna al Social Hub</a></p>
    </section>
</div>
</body>
</html>
