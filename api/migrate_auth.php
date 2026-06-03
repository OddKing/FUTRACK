<?php
require_once 'config.php';

function runSql($pdo, $sql) {
    echo "Running: $sql\n";
    try {
        $pdo->exec($sql);
        echo "SUCCESS\n";
    } catch (Exception $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
    }
}

// 1. Crear tabla de usuarios
runSql($pdo, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255),
    google_id VARCHAR(255) UNIQUE,
    api_token VARCHAR(255) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. Añadir user_id a matches
runSql($pdo, "ALTER TABLE matches ADD COLUMN user_id INT AFTER id");

// 3. Añadir user_id a players
runSql($pdo, "ALTER TABLE players ADD COLUMN user_id INT AFTER id");

?>
