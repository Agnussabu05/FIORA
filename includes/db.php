<?php
$host = 'localhost';
$dbname = 'fiora_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Check if the error is just "Unknown database", if so, we might need to run the schema.
    // For now, just die with error.
    die("Database Connection Failed: " . $e->getMessage() . "<br>Did you import the logic in sql/schema.sql?");
}
?>
