<?php
require_once 'includes/db.php';

try {
    echo "<h1>Updating Schema for Google Sign-In...</h1>";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if (!$stmt->fetch()) {
        echo "Adding 'google_id' column to 'users' table... ";
        $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) UNIQUE AFTER email");
        echo "<span style='color:green;'>Done!</span><br>";
    } else {
        echo "Column 'google_id' already exists.<br>";
    }

    // Also ensure email column exists as it's used in register.php but maybe not in original schema.sql?
    // Let's check schema.sql again.
    // In schema.sql: username, password_hash. No email.
    // In register.php: full_name, username, email, password_hash.
    // Wait, let's verify users table columns.
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    if (!$stmt->fetch()) {
        echo "Adding 'email' column... ";
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) UNIQUE AFTER username");
        echo "<span style='color:green;'>Done!</span><br>";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'full_name'");
    if (!$stmt->fetch()) {
        echo "Adding 'full_name' column... ";
        $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER username");
        echo "<span style='color:green;'>Done!</span><br>";
    }

} catch (Exception $e) {
    echo "<span style='color:red;'>Error: " . $e->getMessage() . "</span><br>";
}
?>
