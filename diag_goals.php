<?php
require_once 'includes/db.php';
$stmt = $pdo->query("DESCRIBE goals");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";
?>
