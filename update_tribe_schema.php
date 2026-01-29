<?php
require_once 'includes/db.php';

try {
    // 1. study_group_messages
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_group_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT,
        file_path VARCHAR(255),
        file_name VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table 'study_group_messages' checked/created.\n";

    // 2. study_group_sessions (for scheduling)
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_group_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        meet_link VARCHAR(255) NOT NULL,
        scheduled_at DATETIME NOT NULL,
        status ENUM('scheduled', 'live', 'locked', 'ended') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE
    )");
    echo "Table 'study_group_sessions' checked/created.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
