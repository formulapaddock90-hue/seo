<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generatore Contenuti Social F1 — FormulaPaddock</title>
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
            max-width: 680px;
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
        textarea { min-height: 140px; }
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
        .cloud-link-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(225,6,0,0.15);
            border: 1px solid #e10600;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .cloud-link-bar a {
            color: #ffd100;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="cloud-link-bar">
            <span>🎬 Reel Engine Cloud 24/7 collegato</span>
            <a href="https://reel-engine-dcnr.onrender.com" target="_blank">Apri Reel Engine &rarr;</a>
        </div>

        <h1>🏎️ Generatore Contenuti <span class="accent">F1</span></h1>
        <p class="desc">
            Inserisci un <strong>testo</strong> o un <strong>URL dell'articolo</strong>. Verranno generati automaticamente:
            post Facebook, Twitter/X, LinkedIn, infografiche Facebook & Instagram (salvate nella cartella Drive <strong>creatività</strong>)
            e verrai collegato al **Reel Engine 9:16 Cloud** per il video finale.
        </p>

        <form action="process.php" method="post" id="genForm">
            <label for="input_text">Testo oppure URL articolo</label>
            <textarea id="input_text" name="input_text" placeholder="Incolla qui il testo della notizia, oppure un link tipo https://www.formulapaddock.it/articolo..." required><?php echo htmlspecialchars($_GET['url'] ?? ''); ?></textarea>
            <p class="hint">Se inserisci un URL, il testo verra' estratto automaticamente dalla pagina.</p>

            <label for="article_url">Link Articolo da allegare ai post (opzionale)</label>
            <input type="text" id="article_url" name="article_url" placeholder="es. https://www.formulapaddock.it/titolo-articolo..." />
            <p class="hint">Specifica l'URL esatto dell'articolo di origine se in alto hai incollato solo testo.</p>

            <button type="submit">Genera contenuti & Apri Reel Engine</button>
            <div class="loading" id="loadingMsg">⏳ Generazione in corso ed apertura Reel Engine...</div>
        </form>
    </div>

    <script>
        document.getElementById('genForm').addEventListener('submit', function () {
            document.getElementById('loadingMsg').style.display = 'block';
        });
        <?php if (!empty($_GET['url'])): ?>
        document.getElementById('genForm').submit();
        <?php endif; ?>
    </script>
</body>
</html>
