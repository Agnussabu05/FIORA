<?php
require_once 'includes/db.php';

try {
    // Add meet_link column to study_groups
    $sql = "ALTER TABLE study_groups ADD COLUMN meet_link VARCHAR(255) DEFAULT NULL";
    $pdo->exec($sql);
    echo "SUCCESS: 'meet_link' column added to 'study_groups'.\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        echo "Column already exists. Skipping.\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
?>
