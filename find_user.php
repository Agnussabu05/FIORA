<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT id, username FROM users LIMIT 10");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
?>
