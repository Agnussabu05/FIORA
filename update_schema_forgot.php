<?php
require_once 'includes/db.php';

echo "<h2>Updating Schema for Forgot Password...</h2>";

try {
    // Check if reset_token already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL;");
        echo "Column 'reset_token' added.<br>";
    } else {
        echo "Column 'reset_token' already exists.<br>";
    }

    // Check if reset_expires already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_expires'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL;");
        echo "Column 'reset_expires' added.<br>";
    } else {
        echo "Column 'reset_expires' already exists.<br>";
    }

    echo "<br><b>Schema updated successfully!</b>";
} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage();
}
?>
