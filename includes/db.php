<?php
$host = 'localhost';
$pdo = null; // Initialize to avoid undefined variable warnings
$dbname = 'fiora_db';
$username = 'root';
$password = '';


// Circuit Breaker: Check if MySQL port is even accepting connections
$circuit_breaker = @fsockopen($host, 3306, $errno, $errstr, 1); // 1 second timeout

if ($circuit_breaker) {
    fclose($circuit_breaker);
    try {
        // Set a hard timeout for the network stream itself
        ini_set('default_socket_timeout', 3);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3
        ];
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, $options);
    } catch (PDOException $e) {
        $pdo = null;
        $db_connection_error = "Database unavailable: " . $e->getMessage();
    }
} else {
    // MySQL is effectively dead/zombie, don't even try to connect (avoids hang)
    $pdo = null;
    $db_connection_error = "MySQL Service Unresponsive (Port 3306 Closed/Blocked)";
}

