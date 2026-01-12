<?php
require_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE study_groups ADD COLUMN subject VARCHAR(255) AFTER name");
    echo "Successfully added subject column to study_groups.";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage();
}
?>
