<?php
require_once 'includes/db.php';

try {
    echo "Updating tasks table schema...\n";

    // 1. Add new columns to tasks table
    $columns = [
        'recurrent_type' => "ENUM('none', 'daily', 'weekly', 'monthly') DEFAULT 'none'",
        'estimated_time' => "INT DEFAULT 0 COMMENT 'in minutes'",
        'category' => "VARCHAR(50) DEFAULT 'Personal'",
        'parent_id' => "INT NULL",
        'dependency_id' => "INT NULL",
        'user_order' => "INT DEFAULT 0"
    ];

    foreach ($columns as $col => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM tasks LIKE '$col'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN $col $definition");
            echo "Added column '$col' to tasks table.\n";
        } else {
            echo "Column '$col' already exists.\n";
        }
    }

    // 2. Create Tags table
    echo "Creating tags table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'tags' ready.\n";

    // 3. Create Task_Tags junction table
    echo "Creating task_tags junction table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_tags (
        task_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (task_id, tag_id),
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    )");
    echo "Table 'task_tags' ready.\n";

    // 4. Set Foreign Keys for parent_id and dependency_id if not already set (optional but good practice)
    // Note: In some environments, adding FKs to existing tables might need care. 
    // For simplicity in this dev environment, we'll stick to basic columns first.

    echo "Database migration complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
