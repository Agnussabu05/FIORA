<?php
// restore_safe.php
// A safer restoration script that avoids Emoji defaults which cause errors on some MySQL versions

$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=fiora_db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Restoring Remaining Tables...</h1>";

    // HABITS (Without emoji default to be safe)
    $pdo->exec("CREATE TABLE IF NOT EXISTS habits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        emoji VARCHAR(10) DEFAULT '', 
        frequency ENUM('daily', 'weekly') DEFAULT 'daily',
        is_admin_pushed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Fixed: Habits<br>";

    // HABIT LOGS
    $pdo->exec("CREATE TABLE IF NOT EXISTS habit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        habit_id INT NOT NULL,
        check_in_date DATE NOT NULL,
        status ENUM('completed', 'skipped') DEFAULT 'completed',
        FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE,
        UNIQUE KEY unique_log (habit_id, check_in_date)
    )");

    // MOOD LOGS
    $pdo->exec("CREATE TABLE IF NOT EXISTS mood_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mood_score INT NOT NULL,
        mood_label VARCHAR(50),
        note TEXT,
        sleep_hours FLOAT DEFAULT 0,
        activities JSON DEFAULT NULL,
        log_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_daily_mood (user_id, log_date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Fixed: Mood<br>";

    // STUDY SESSIONS
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(100) NOT NULL,
        duration_minutes INT NOT NULL,
        session_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // STUDY GROUPS
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        subject VARCHAR(100) NOT NULL,
        leader_id INT NOT NULL,
        description TEXT,
        members_count INT DEFAULT 1,
        max_members INT DEFAULT 10,
        is_verified BOOLEAN DEFAULT FALSE,
        meet_link VARCHAR(255),
        next_session DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (leader_id) REFERENCES users(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved') DEFAULT 'pending',
        FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // BOOKS
    $pdo->exec("CREATE TABLE IF NOT EXISTS books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(100),
        category VARCHAR(50),
        condition_text VARCHAR(50),
        type ENUM('sell', 'borrow') DEFAULT 'sell',
        price DECIMAL(10, 2) DEFAULT 0.00,
        image_url VARCHAR(255),
        status ENUM('available', 'sold', 'borrowed') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // NOTIFICATIONS
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        type VARCHAR(50) DEFAULT 'general',
        message TEXT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Fixed: Notifications<br>";

    // FINANCE
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        category VARCHAR(50) NOT NULL,
        type ENUM('income', 'expense') NOT NULL,
        transaction_date DATE NOT NULL,
        description VARCHAR(255),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // GOALS
    $pdo->exec("CREATE TABLE IF NOT EXISTS goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        target_date DATE,
        category VARCHAR(50) DEFAULT 'personal',
        progress INT DEFAULT 0,
        status ENUM('active', 'completed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // SYSTEM LOGS
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action_type VARCHAR(50),
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // MUSIC
    $pdo->exec("CREATE TABLE IF NOT EXISTS music (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        artist VARCHAR(100),
        link VARCHAR(500),
        file_path VARCHAR(255),
        playlist_name VARCHAR(100) DEFAULT 'Favorites',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    echo "<h2>✅ Repair Complete!</h2>";

} catch (PDOException $e) {
    die("<h2 style='color:red'>Setup Failed: " . $e->getMessage() . "</h2>");
}
?>
