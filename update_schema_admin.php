<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Using 127.0.0.1 instead of localhost for potentially faster/more reliable connection
$host = '127.0.0.1';
$username = 'root';
$password = '';

try {
    echo "<h1>Fiora Database Fix 🛠️</h1>";
    echo "Connecting to MySQL server at $host... ";
    
    // Set a timeout for the connection
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ];
    
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password, $options);
    echo "<span style='color:green;'>Connected!</span><br>";

    echo "Checking for database 'fiora_db'... ";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS fiora_db");
    $pdo->exec("USE fiora_db");
    echo "Done!<br>";

    echo "Checking 'users' table... ";
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    if (empty($tables)) {
        die("<br><span style='color:red;'>Error: 'users' table not found! Please run your setup script first.</span>");
    }
    echo "Exists.<br>";

    echo "Checking 'role' column... ";
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetchAll();
    if (empty($columns)) {
        echo "Adding 'role' column... ";
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user'");
        echo "Done!<br>";
    } else {
        echo "Already exists.<br>";
    }

    echo "Promoting 'demo' user to admin... ";
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE username = 'demo'");
    $stmt->execute();
    echo "Processed (" . $stmt->rowCount() . " rows updated).<br>";

    echo "<h2 style='color:green;'>✅ All Fixed!</h2>";
    echo "<div style='background: #f0fdf4; padding: 20px; border-radius: 12px; border: 1px solid #bbf7d0;'>";
    echo "<p>Follow these steps exactly:</p>";
    echo "1. <a href='logout.php' style='color:blue; font-weight:bold; font-size: 1.2rem;'>👉 CLICK HERE TO LOGOUT FIRST</a> (Crucial!)<br><br>";
    echo "2. Login with <b>demo</b> / <b>user123</b><br>";
    echo "3. Go to <a href='admin/index.php' style='color:blue;'>Admin Dashboard</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<br><br><div style='color:red; background: #fee2e2; padding: 20px; border-radius: 12px;'>";
    echo "<b>ERROR:</b> " . htmlspecialchars($e->getMessage());
    echo "<br><br>Suggestions:";
    echo "<ul><li>Ensure XAMPP MySQL is started</li><li>Check if your root user has a password</li></ul>";
    echo "</div>";
}
?>
