<?php
// restore_full_fiora_v2.php
// MASTER RESTORATION SCRIPT
// Run this to completely rebuild the database after a crash.

$host = 'localhost';
$username = 'root';
$password = '';

/* --- 1. CONNECT & CREATE DB --- */
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Rebuilding Fiora Database... 🚧</h1>";
    
    $pdo->exec("DROP DATABASE IF EXISTS fiora_db");
    echo "Dropped old database.<br>";
    
    $pdo->exec("CREATE DATABASE fiora_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Created new 'fiora_db'.<br>";
    
    $pdo->exec("USE fiora_db");

    /* --- 2. CREATE BASE TABLES --- */
    
    // USERS
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE,
        role ENUM('user', 'admin') DEFAULT 'user',
        reset_token VARCHAR(64) DEFAULT NULL,
        reset_expires DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created 'users' table.<br>";

    // TASKS
    $pdo->exec("CREATE TABLE tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        deadline DATETIME,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        status ENUM('pending', 'completed') DEFAULT 'pending',
        is_admin_pushed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // HABITS
    $pdo->exec("CREATE TABLE habits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        emoji VARCHAR(10) DEFAULT '🔥',
        frequency ENUM('daily', 'weekly') DEFAULT 'daily',
        is_admin_pushed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // HABIT LOGS
    $pdo->exec("CREATE TABLE habit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        habit_id INT NOT NULL,
        check_in_date DATE NOT NULL,
        status ENUM('completed', 'skipped') DEFAULT 'completed',
        FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE,
        UNIQUE KEY unique_log (habit_id, check_in_date)
    )");

    // MOOD LOGS (Updated Schema)
    $pdo->exec("CREATE TABLE mood_logs (
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
    echo "Created 'mood_logs' table (v2).<br>";

    // STUDY SESSIONS
    $pdo->exec("CREATE TABLE study_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(100) NOT NULL,
        duration_minutes INT NOT NULL,
        session_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // STUDY GROUPS (Tribes)
    $pdo->exec("CREATE TABLE study_groups (
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
    
    $pdo->exec("CREATE TABLE group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved') DEFAULT 'pending',
        FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // BOOKS
    $pdo->exec("CREATE TABLE books (
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
    $pdo->exec("CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL, -- NULL for global
        type VARCHAR(50) DEFAULT 'general',
        message TEXT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // FINANCE
    $pdo->exec("CREATE TABLE expenses (
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
    $pdo->exec("CREATE TABLE goals (
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

    // SYSTEM LOGS (Admin)
    $pdo->exec("CREATE TABLE system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action_type VARCHAR(50),
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    /* --- 3. SEED DEFAULT DATA --- */
    
    // Admin User (pass: admin123)
    $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
        ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
        
    // Demo User (pass: user123)
    $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")
        ->execute(['demo', password_hash('user123', PASSWORD_DEFAULT), 'user']);
    
    // Add Demo Tasks
    $demoId = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tasks (user_id, title, deadline, priority) VALUES (?, ?, ?, ?)")
        ->execute([$demoId, 'Complete Setup', date('Y-m-d H:i:s', strtotime('+1 day')), 'high']);
    
    echo "<h2>✅ Success! All systems operational.</h2>";
    echo "<a href='login.php'>Login Now</a>";

} catch (PDOException $e) {
    die("<h2 style='color:red'>Setup Failed: " . $e->getMessage() . "</h2>");
}
?>
