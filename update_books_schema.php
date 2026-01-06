<?php
require_once 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE books ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "Column 'created_at' added successfully!";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage();
}
?>
