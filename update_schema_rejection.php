<?php
require_once 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE study_groups ADD COLUMN rejection_reason VARCHAR(255) DEFAULT NULL");
    echo "Added rejection_reason column to study_groups table.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column rejection_reason already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
