<?php
require_once 'includes/db.php';
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current Tables in fiora_db:\n";
    foreach ($tables as $t) {
        echo "- $t\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
