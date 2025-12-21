<?php
require_once 'includes/db.php';

try {
    echo "Updating database for new features...\n";

    // 1. Update Admin Credentials (demo -> admin / admin123)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'demo' OR username = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET username = 'admin', password_hash = ?, role = 'admin' WHERE id = ?");
        $stmt->execute([$hash, $admin['id']]);
        echo "Admin credentials updated to admin / admin123.\n";
    } else {
        // Create it if it doesn't exist
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES ('admin', ?, 'admin')");
        $stmt->execute([$hash]);
        echo "Default admin user created.\n";
    }

    // 2. Create Notifications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL, -- NULL implies global/all users
        message TEXT NOT NULL,
        type VARCHAR(20) DEFAULT 'info', -- info, success, warning
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'notifications' ready.\n";

    // 3. Update Music Table for System vs User
    // First check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM music LIKE 'is_system'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE music ADD COLUMN is_system TINYINT(1) DEFAULT 0");
        echo "Column 'is_system' added to music table.\n";
    }

    echo "Database migrations complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
