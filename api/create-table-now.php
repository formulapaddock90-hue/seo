<?php
/**
 * Script di creazione tabella F1 Final Standings
 * Eseguibile direttamente via PHP CLI o browser
 */

require __DIR__ . '/conn.php';

try {
    $conn = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS f1_final_standings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        race_number INT NOT NULL,
        position INT NOT NULL,
        driver_number INT,
        driver_name VARCHAR(255),
        team_name VARCHAR(255),
        best_lap VARCHAR(20),
        last_lap VARCHAR(20),
        total_laps INT,
        gap VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_race_position (race_number, position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $conn->exec($sql);

    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head><meta charset='UTF-8'><title>✅ Tabella Creata</title></head>";
    echo "<body style='font-family:Arial; margin:50px;'>";
    echo "<h1>✅ Tabella Creata con Successo!</h1>";
    echo "<p><strong>Tabella:</strong> f1_final_standings</p>";
    echo "<p><strong>Database:</strong> $dbname</p>";
    echo "<p><strong>Host:</strong> $hostname</p>";
    echo "<p><a href='test-db-connection.php'>← Torna al Test</a></p>";
    echo "</body>";
    echo "</html>";

} catch (Exception $e) {
    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head><meta charset='UTF-8'><title>❌ Errore</title></head>";
    echo "<body style='font-family:Arial; margin:50px;'>";
    echo "<h1>❌ Errore Creazione Tabella</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='test-db-connection.php'>← Torna al Test</a></p>";
    echo "</body>";
    echo "</html>";
}
