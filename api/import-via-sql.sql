-- Import F1 Standings da CSV file direttamente in MySQL
-- Esegui questa query da phpMyAdmin o da MySQL CLI

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

-- Pulisci tabella (opzionale - rimuovi il commento se vuoi)
-- DELETE FROM f1_final_standings;

-- Verifica che la tabella esista
SHOW TABLES LIKE 'f1_final_standings';

-- Mostra i record attuali
SELECT COUNT(*) as total_records FROM f1_final_standings;
SELECT * FROM f1_final_standings ORDER BY race_number DESC, position ASC LIMIT 10;
