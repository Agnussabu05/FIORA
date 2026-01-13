<?php
require 'includes/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT,
            target_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Table 'system_logs' created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
