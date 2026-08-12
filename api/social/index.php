<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generatore Contenuti Social F1</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #0a0a0f 0%, #2b0a0f 100%);
            color: #fff;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 40px;
            max-width: 640px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        h1 {
            margin-top: 0;
            font-size: 26px;
        }
        .accent { color: #ffd100; }
        p.desc {
            color: #ccc;
            font-size: 14px;
            line-height: 1.5;
        }
        label {
            display: block;
            margin: 20px 0 8px;
            font-weight: 600;
            font-size: 14px;
        }
        textarea, input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 14px;
            resize: vertical;
        }
        textarea { min-height: 160px; }
        textarea:focus, input:focus { outline: 2px solid #ffd100; }
        button {
            margin-top: 24px;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #ffd100;
            color: #1a1a1a;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.1s ease;
        }
        button:hover { transform: translateY(-1px); }
        .hint {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #ffd100;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🏎️ Generatore Contenuti <span class="accent">F1</span></h1>
        <p class="desc">
            Inserisci un <strong>testo</strong> (es. un comunicato o una notizia) oppure un
            <strong>URL</strong> di un articolo. Verranno generati automaticamente:
            post Facebook, tweet, post LinkedIn, infografica Facebook, infografica Instagram
            e un Reel verticale. Tutto viene salvato su Google Sheets e Google Drive.
        </p>

        <form action="process.php" method="post" id="genForm">
            <label for="input_text">Testo oppure URL articolo</label>
            <textarea id="input_text" name="input_text" placeholder="Incolla qui il testo della notizia, oppure un link tipo https://www.sito.it/articolo..." required><?php echo htmlspecialchars($_GET['url'] ?? ''); ?></textarea>
            <p class="hint">Se inserisci un URL, il testo verra' estratto automaticamente dalla pagina.</p>

            <button type="submit">Genera contenuti</button>
            <div class="loading" id="loadingMsg">⏳ Generazione in corso, attendere (puo' richiedere fino a 1-2 minuti per il reel)...</div>
        </form>
    </div>

    <script>
        document.getElementById('genForm').addEventListener('submit', function () {
            document.getElementById('loadingMsg').style.display = 'block';
        });
        // Auto-submit se URL già present nel GET
        <?php if (!empty($_GET['url'])): ?>
        document.getElementById('genForm').submit();
        <?php endif; ?>
    </script>
</body>
</html>
