<?php
require_once 'includes/db.php';
try {
    echo "--- BOOKS ---\n";
    $stmt = $pdo->query("DESCRIBE books");
    foreach($stmt->fetchAll() as $col) echo $col['Field'] . " - " . $col['Type'] . "\n";
    
    echo "\n--- BOOK_TRANSACTIONS ---\n";
    $stmt = $pdo->query("DESCRIBE book_transactions");
    foreach($stmt->fetchAll() as $col) echo $col['Field'] . " - " . $col['Type'] . "\n";

} catch (Exception $e) {
    echo $e->getMessage();
}
?>
