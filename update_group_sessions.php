<?php
require_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE study_group_sessions ADD COLUMN status VARCHAR(20) DEFAULT 'scheduled'");
    echo "Added status column to study_group_sessions.\n";
} catch (Exception $e) {
    echo "Column likely exists or error: " . $e->getMessage() . "\n";
}
?>
