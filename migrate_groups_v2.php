<?php
require_once 'includes/db.php';

try {
    // 1. Update study_groups status enum to include 'forming'
    $pdo->exec("ALTER TABLE study_groups MODIFY COLUMN status ENUM('forming', 'pending_verification', 'active') DEFAULT 'forming'");
    echo "Updated study_groups status column.<br>";

    // 2. Add group_id to study_requests
    // Check if column exists first to avoid error
    $check = $pdo->query("SHOW COLUMNS FROM study_requests LIKE 'group_id'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE study_requests ADD COLUMN group_id INT AFTER id");
        echo "Added group_id column to study_requests.<br>";
    } else {
        echo "group_id column already exists.<br>";
    }

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>
