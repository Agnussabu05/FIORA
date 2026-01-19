<?php
$logFile = 'c:/xampp/mysql/data/mysql_error.log';
$lines = file($logFile);
$last = array_slice($lines, -20);
foreach($last as $line) {
    echo trim($line) . "\n";
}
?>
