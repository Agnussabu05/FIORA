<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

try {
    echo "<h1>Study Collective Database Migration 🛠️</h1>";
    
    // 1. Add interested_study to users table
    echo "Checking 'interested_study' column in 'users'... ";
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'interested_study'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN interested_study BOOLEAN DEFAULT FALSE");
        echo "<span style='color:green;'>Added.</span><br>";
    } else {
        echo "<span style='color:orange;'>Already exists.</span><br>";
    }

    // 2. Create study_requests table
    echo "Creating 'study_requests' table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "<span style='color:green;'>Done.</span><br>";

    // 3. Create study_groups table
    echo "Creating 'study_groups' table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        status ENUM('pending_verification', 'active') DEFAULT 'pending_verification',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<span style='color:green;'>Done.</span><br>";

    // 4. Create study_group_members table
    echo "Creating 'study_group_members' table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('leader', 'member') DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "<span style='color:green;'>Done.</span><br>";

    echo "<h2 style='color:green;'>✅ Migration Successful!</h2>";
    echo "<p><a href='study.php'>Go to Study Planner</a></p>";

} catch (Exception $e) {
    echo "<br><br><div style='color:red;'>";
    echo "<b>ERROR:</b> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
