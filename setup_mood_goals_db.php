<?php
require_once 'includes/db.php';

try {
    // Mood Logs Table
    $moodSql = "CREATE TABLE IF NOT EXISTS mood_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mood_score INT NOT NULL, -- 1 to 5
        mood_label VARCHAR(50),
        note TEXT,
        log_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        UNIQUE KEY user_daily_mood (user_id, log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    // Goals Table
    $goalSql = "CREATE TABLE IF NOT EXISTS goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        target_date DATE,
        category VARCHAR(50) DEFAULT 'personal',
        progress INT DEFAULT 0,
        status ENUM('active', 'completed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($moodSql);
    $pdo->exec($goalSql);
    echo "Tables 'mood_logs' and 'goals' created successfully!";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?>
