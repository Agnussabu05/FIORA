<?php
$logFile = 'c:/xampp/mysql/data/mysql_error.log';
if (!file_exists($logFile)) {
    echo "Log file not found.";
    exit;
}

// Read the last 4KB of data
$fp = fopen($logFile, 'r');
fseek($fp, -4096, SEEK_END);
$content = fread($fp, 4096);
fclose($fp);

echo "--- LAST ERROR LOG ENTRIES ---\n";
echo $content;
echo "\n--- END ---\n";
?>
