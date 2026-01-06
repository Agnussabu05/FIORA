<?php
require_once 'includes/db.php';

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM habits LIKE 'emoji'");
    $stmt = $pdo->query("SHOW CREATE TABLE habits");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Current Table Structure:\n";
    print_r($res);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
