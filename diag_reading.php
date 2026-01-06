<?php
require_once 'includes/db.php';
$stmt = $pdo->query("DESCRIBE books");
echo "<pre>";
print_r($stmt->fetchAll());
echo "</pre>";
?>
