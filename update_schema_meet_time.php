<?php
require_once 'includes/db.php';

try {
    // Add meet_scheduled_at column to study_groups
    $sql = "ALTER TABLE study_groups ADD COLUMN meet_scheduled_at DATETIME DEFAULT NULL";
    $pdo->exec($sql);
    echo "SUCCESS: 'meet_scheduled_at' column added to 'study_groups'.\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        echo "Column already exists. Skipping.\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
?>
