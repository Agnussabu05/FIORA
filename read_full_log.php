<?php
$logFile = 'c:/xampp/mysql/data/mysql_error.log';
// Read last 5KB
$fp = fopen($logFile, 'r');
fseek($fp, -5000, SEEK_END);
echo fread($fp, 5000);
fclose($fp);
?>
