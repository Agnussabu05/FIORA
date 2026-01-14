<?php
require_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE study_group_sessions MODIFY status VARCHAR(50) DEFAULT 'scheduled'");
    echo "Modified status column type.\n";
    
    // Force update again just in case
    $pdo->exec("UPDATE study_group_sessions SET status = 'live'");
    echo "Forced status to live.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
