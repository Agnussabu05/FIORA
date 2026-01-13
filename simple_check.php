<?php
ini_set('display_errors', 1);
echo "<h1>Connection Diagnostics</h1>";

$hosts = ['127.0.0.1', 'localhost', '::1'];
$port = 3306;
$user = 'root';
$pass = '';
$dbname = 'fiora_db';

foreach ($hosts as $h) {
    echo "<p><strong>Testing host: $h</strong>... ";
    try {
        $dsn = "mysql:host=$h;port=$port;dbname=$dbname;charset=utf8";
        // Short timeout
        $options = [PDO::ATTR_TIMEOUT => 2];
        $start = microtime(true);
        $pdo = new PDO($dsn, $user, $pass, $options);
        $end = microtime(true);
        echo "<span style='color:green'>SUCCESS</span> (" . round($end - $start, 4) . "s)</p>";
    } catch (PDOException $e) {
        echo "<span style='color:red'>FAILED</span>: " . $e->getMessage() . "</p>";
    }
}
?>
