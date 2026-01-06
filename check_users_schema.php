<?php
require_once 'includes/db.php';
if (!$pdo) {
    die("Connection failed: " . ($db_connection_error ?? "Unknown error") . "\n");
}
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in 'users' table:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
