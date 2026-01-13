<?php
echo "<h1>Fiora Probe</h1>";
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
echo "<p>PHP is working correctly.</p>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";

require_once 'includes/db.php';
if ($pdo) {
    echo "<p style='color: green'>Database Connected Successfully</p>";
} else {
    echo "<p style='color: red'>Database Connection Failed</p>";
    if (isset($db_connection_error)) {
        echo "<p>Error: " . htmlspecialchars($db_connection_error) . "</p>";
    }
}
?>
