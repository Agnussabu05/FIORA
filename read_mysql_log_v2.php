<?php
$logFile = 'c:/xampp/mysql/data/mysql_error.log';
if (!file_exists($logFile)) {
    echo "Log file not found.";
    exit;
}

$lines = file($logFile);
// Get last 40 lines
$lastLines = array_slice($lines, -40);
echo implode("", $lastLines);
?>
