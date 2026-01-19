<?php
require 'includes/db.php';
try {
    // Update database charset
    $pdo->exec("ALTER DATABASE fiora_db CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci");
    
    // Update specific text-heavy tables
    $tables = ['notifications', 'books', 'users', 'book_transactions', 'book_reservations'];
    foreach ($tables as $t) {
        $pdo->exec("ALTER TABLE $t CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Updated $t to utf8mb4<br>";
    }
    echo "Done.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
