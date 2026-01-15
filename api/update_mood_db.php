<?php
// Standalone DB update script
$host = 'localhost';
$dbname = 'fiora_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected. Updating schema...<br>";

    // Add 'sleep_hours' column 
    $pdo->exec("ALTER TABLE mood_logs ADD COLUMN IF NOT EXISTS sleep_hours FLOAT DEFAULT 0");
    echo "Added sleep_hours.<br>";
    
    // Add 'activities' column
    $pdo->exec("ALTER TABLE mood_logs ADD COLUMN IF NOT EXISTS activities TEXT");
    echo "Added activities.<br>";

    echo "Done!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
