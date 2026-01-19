<?php
require_once 'includes/db.php';

try {
    echo "Checking mood_logs schema...\n";
    
    // Add sleep_hours
    try {
        $pdo->exec("ALTER TABLE mood_logs ADD COLUMN sleep_hours FLOAT DEFAULT 0");
        echo "Added sleep_hours column.\n";
    } catch (PDOException $e) { /* Column likely exists */ }
    
    // Add activities
    try {
        $pdo->exec("ALTER TABLE mood_logs ADD COLUMN activities JSON DEFAULT NULL");
        echo "Added activities column.\n";
    } catch (PDOException $e) { /* Column likely exists */ }
    
    echo "Schema check complete.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
