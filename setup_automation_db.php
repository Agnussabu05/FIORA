<?php
require_once 'includes/db.php';

try {
    echo "<h1>Setting up Automation Rules Table...</h1>";

    $sql = "CREATE TABLE IF NOT EXISTS automation_rules (
        rule_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        rule_name VARCHAR(100) NOT NULL,
        rule_condition TEXT NOT NULL,
        action TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "✅ Table 'automation_rules' created successfully!<br>";
    echo "<a href='index.php'>Go Home</a>";

} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}
?>
