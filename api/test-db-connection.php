<?php
/**
 * Test Connessione Database
 */

require __DIR__ . '/conn.php';

echo "<!DOCTYPE html>";
echo "<html>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>🧪 Test Connessione Database</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }";
echo ".test { margin: 20px 0; padding: 15px; border-left: 4px solid #ddd; }";
echo ".ok { border-color: #4caf50; background: #e8f5e9; }";
echo ".error { border-color: #d32f2f; background: #ffebee; }";
echo "code { background: #f5f5f5; padding: 5px 10px; border-radius: 3px; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<h1>🧪 Test Connessione Database</h1>";

// Test 1: Verifica variabili
echo "<div class='test ok'>";
echo "<h3>✅ Credenziali Caricate</h3>";
echo "<p><strong>Host:</strong> <code>$hostname</code></p>";
echo "<p><strong>Database:</strong> <code>$dbname</code></p>";
echo "<p><strong>Username:</strong> <code>$username</code></p>";
echo "<p><strong>Password:</strong> " . (strlen($password) > 0 ? "✅ Presente" : "❌ Vuota") . "</p>";
echo "</div>";

// Test 2: Connessione PDO
echo "<div class='test'>";
echo "<h3>Test Connessione PDO</h3>";

try {
    $conn = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<div class='ok'>";
    echo "<p>✅ <strong>Connessione riuscita!</strong></p>";

    // Test 3: Verifica tabella
    $stmt = $conn->query("SHOW TABLES LIKE 'f1_final_standings'");
    $table_exists = $stmt->rowCount() > 0;

    if ($table_exists) {
        echo "<p>✅ <strong>Tabella f1_final_standings:</strong> Esiste</p>";

        // Conta record
        $count_stmt = $conn->query("SELECT COUNT(*) as total FROM f1_final_standings");
        $count = $count_stmt->fetch()['total'];
        echo "<p>📊 <strong>Record:</strong> $count</p>";

        // Ultimi record
        $latest_stmt = $conn->query("SELECT * FROM f1_final_standings ORDER BY race_number DESC, position ASC LIMIT 5");
        $latest = $latest_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($latest) > 0) {
            echo "<p><strong>Ultimi 5 record:</strong></p>";
            echo "<table style='width:100%; border-collapse:collapse;'>";
            echo "<tr style='background:#f0f0f0;'>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Pos</th>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Pilota</th>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Team</th>";
            echo "</tr>";

            foreach ($latest as $row) {
                echo "<tr>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>" . $row['position'] . "</td>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>" . $row['driver_name'] . "</td>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>" . $row['team_name'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>⚠️ <strong>Tabella vuota</strong> — Nessun record ancora</p>";
        }
    } else {
        echo "<p>❌ <strong>Tabella f1_final_standings:</strong> Non esiste</p>";
        echo "<p>Prova: <a href='api/standings-direct-db.php?action=create_table' target='_blank'>Crea Tabella</a></p>";
    }

    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<p>❌ <strong>Errore Connessione:</strong></p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Verifica:</strong></p>";
    echo "<ul>";
    echo "<li>Host raggiungibile: <code>$hostname:3306</code></li>";
    echo "<li>Username corretto: <code>$username</code></li>";
    echo "<li>Database esiste: <code>$dbname</code></li>";
    echo "<li>Password corretta</li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div>";

// Test 4: Verifica API Endpoints
echo "<div class='test ok'>";
echo "<h3>✅ API Endpoints Disponibili</h3>";
echo "<p>Prova questi endpoint:</p>";
echo "<ul>";
echo "<li><a href='api/standings-direct-db.php?action=create_table' target='_blank'>Crea Tabella</a></li>";
echo "<li><a href='api/standings-direct-db.php?action=status' target='_blank'>Status Database</a></li>";
echo "<li><a href='api/standings-direct-db.php?action=get_latest' target='_blank'>Carica Classifica</a></li>";
echo "</ul>";
echo "</div>";

// Test 5: Info Sistema
echo "<div class='test ok'>";
echo "<h3>ℹ️ Info Sistema</h3>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>PDO MySQL:</strong> " . (extension_loaded('pdo_mysql') ? '✅ Disponibile' : '❌ Non disponibile') . "</p>";
echo "<p><strong>Percorso Script:</strong> <code>" . __FILE__ . "</code></p>";
echo "</div>";

echo "</body>";
echo "</html>";
?>
