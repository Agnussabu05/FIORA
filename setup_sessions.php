<?php
require_once 'includes/db.php';

try {
    // Create study_group_sessions table
    $sql = "CREATE TABLE IF NOT EXISTS study_group_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        title VARCHAR(100) DEFAULT 'Study Session',
        meet_link VARCHAR(255) NOT NULL,
        scheduled_at DATETIME NOT NULL,
        status ENUM('scheduled', 'active', 'ended') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "SUCCESS: 'study_group_sessions' table created.\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
