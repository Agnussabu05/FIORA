<?php
require_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE study_requests ADD COLUMN group_name VARCHAR(100) DEFAULT NULL");
    echo "Column 'group_name' added to 'study_requests' successfully.";
} catch (Exception $e) {
    echo "Error or column already exists: " . $e->getMessage();
}
?>
