<?php
require_once 'includes/db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM books");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in books table:\n";
    foreach ($columns as $col) {
        echo "- " . $col . "\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
