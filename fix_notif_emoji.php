<?php
require_once 'includes/db.php';
header('Content-Type: text/plain');

if (!$pdo) {
    die("DB Connection failed");
}

try {
    // 1. Show matching rows BEFORE fix
    echo "--- BEFORE FIX ---\n";
    $stmt = $pdo->prepare("SELECT id, message FROM notifications WHERE message LIKE '%Cha-ching%'");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "[{$r['id']}] {$r['message']}\n";
    }

    // 2. Apply Fix: Smart replace
    // Find any message containing 'Cha-ching' that DOES NOT start with '💰'
    // We will extract the part starting from 'Cha-ching' and prepend '💰 '
    echo "\n--- APPLYING FIX ---\n";
    
    // Logic: 
    // If message is '???? Cha-ching! ...'
    // LOCATE('Cha-ching', message) returns index of 'C'.
    // SUBSTRING(message, index) returns 'Cha-ching! ...'
    // CONCAT('💰 ', ...) returns '💰 Cha-ching! ...'
    
    $sql = "UPDATE notifications 
            SET message = CONCAT('💰 ', SUBSTRING(message, LOCATE('Cha-ching', message)))
            WHERE message LIKE '%Cha-ching%' 
            AND message NOT LIKE '💰%'";
            
    $count = $pdo->exec($sql);
    echo "Updated $count rows.\n";

    // 3. Show matching rows AFTER fix
    echo "\n--- AFTER FIX ---\n";
    $stmt->execute(); // Re-run select
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "[{$r['id']}] {$r['message']}\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
