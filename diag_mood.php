<?php
require_once 'includes/db.php';
$stmt = $pdo->query("DESCRIBE mood_logs");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";
?>
