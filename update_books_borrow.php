<?php
require_once 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE books ADD COLUMN borrowed_from INT DEFAULT NULL");
    echo "Added 'borrowed_from' column successfully.";
} catch (PDOException $e) {
    echo "Column likely exists or error: " . $e->getMessage();
}
?>
