<?php
require __DIR__ . '/auth.php';
checkAuth();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Classifica Sessione</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="file"] { padding: 8px; }
        button { background: #0b57d0; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0845a6; }
        .info { background: #f0f4f9; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .success { color: green; margin-top: 10px; }
        .error { color: red; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>📤 Upload Classifica Sessione</h1>

    <div class="info">
        <h3>Formato atteso (tab-separated):</h3>
        <pre>Pos	Pilota	Team	Tempo	Punti
1	Hamilton	Mercedes	1:20.567	25
2	Verstappen	Red Bull	1:21.234	18
3	Leclerc	Ferrari	1:21.890	15</pre>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $uploadDir = __DIR__ . '/storage/session-results/';

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'session-results.txt';
        $filepath = $uploadDir . $filename;

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo '<p class="error">❌ Errore upload: ' . htmlspecialchars($file['error']) . '</p>';
        } else if ($file['type'] !== 'text/plain') {
            echo '<p class="error">❌ Solo file .txt sono permessi</p>';
        } else if ($file['size'] > 1000000) { // 1MB limit
            echo '<p class="error">❌ File troppo grande (max 1MB)</p>';
        } else {
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                echo '<p class="success">✅ File caricato con successo!</p>';
                echo '<p>Il file è disponibile a: <code>api/session-results-gdrive.php</code></p>';
            } else {
                echo '<p class="error">❌ Errore durante il salvataggio del file</p>';
            }
        }
    }
    ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="file">Seleziona file TXT:</label>
            <input type="file" id="file" name="file" accept=".txt" required>
        </div>
        <button type="submit">📤 Carica File</button>
    </form>

    <p><a href="<?= BASE_PATH ?>">← Torna all'app</a></p>
</body>
</html>
