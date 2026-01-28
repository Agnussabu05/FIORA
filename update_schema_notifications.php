<?php
require_once 'includes/db.php';

try {
    echo "Updating schema for Notifications...<br>";
    
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "Table 'notifications' created/checked successfully.<br>";
    echo "Done!";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
