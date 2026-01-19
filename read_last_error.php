<?php
$logFile = 'c:/xampp/mysql/data/mysql_error.log';
if (!file_exists($logFile)) {
    echo "Log file not found.";
    exit;
}
$fp = fopen($logFile, 'r');
fseek($fp, -5000, SEEK_END);
$content = fread($fp, 5000);
fclose($fp);
echo $content;
?>
