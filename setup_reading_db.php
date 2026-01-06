<?php
require_once 'includes/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255),
        total_pages INT DEFAULT 0,
        current_page INT DEFAULT 0,
        status ENUM('reading', 'completed', 'wishlist') DEFAULT 'reading',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Table 'books' created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
