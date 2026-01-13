<?php
require_once 'includes/db.php';

try {
    // Attempt to add 'description' column
    echo "Attempting to add 'description' column...\n";
    $pdo->exec("ALTER TABLE study_groups ADD COLUMN description TEXT AFTER subject");
    echo "SUCCESS: 'description' column added.\n";
} catch (PDOException $e) {
    // If error contains "Duplicate" or similar, it's fine
    echo "INFO: " . $e->getMessage() . "\n";
}
?>
