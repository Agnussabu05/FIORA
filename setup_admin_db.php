<?php
require_once 'includes/db.php';

try {
    echo "Creating supplemental tables...\n";

    // System Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        category VARCHAR(50) DEFAULT 'general',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Table 'system_settings' ready.\n";

    // Insert default module toggles if they don't exist
    $modules = ['tasks', 'habits', 'finance', 'study', 'reading', 'music', 'mood', 'goals', 'ai'];
    foreach ($modules as $module) {
        $key = 'module_' . $module . '_enabled';
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, category) VALUES (?, '1', 'modules')");
        $stmt->execute([$key]);
    }

    // Admin Activity Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table 'admin_activity_logs' ready.\n";

    // Content Table (Quotes/Tips)
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        content_type ENUM('quote', 'tip', 'suggestion') NOT NULL,
        content_text TEXT NOT NULL,
        category VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'admin_content' ready.\n";

    echo "Supplemental tables setup complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
