<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL server first (without database)
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Fiora Database Setup 🛠️</h1>";

    // Create Database
    echo "Creating database 'fiora_db'... ";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS fiora_db");
    echo "<span style='color:green;'>Done!</span><br>";

    // Switch to database
    $pdo->exec("USE fiora_db");

    // Read schema file
    $schemaFile = __DIR__ . '/sql/schema.sql';
    if (!file_exists($schemaFile)) {
        die("<span style='color:red;'>Error: sql/schema.sql not found!</span>");
    }

    $sql = file_get_contents($schemaFile);
    
    // Execute multiple queries
    // Note: PDO doesn't always support multiple queries in one exec() call depending on driver settings,
    // so we might need splitting, but let's try raw exec first as it works for simple dumps.
    // If that fails, we split by semicolon.
    
    echo "Importing tables... ";
    
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $pdo->exec($query);
            } catch (Exception $e) {
                // Ignore empty query errors or repeat table creation warnings
                if (!strpos($e->getMessage(), "already exists")) {
                    echo "<br>Warning on query: " . htmlspecialchars(substr($query, 0, 50)) . "... " . $e->getMessage();
                }
            }
        }
    }
    
    echo "<span style='color:green;'>Tables imported successfully!</span><br>";
    echo "<h2>✅ Setup Complete!</h2>";
    echo "<a href='index.php'>Go to Dashboard</a>";

} catch (PDOException $e) {
    die("Setup Failed: " . $e->getMessage());
}
?>
