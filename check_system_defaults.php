<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SHOW TABLES LIKE 'system_defaults'");
if ($stmt->fetch()) {
    echo "EXISTS";
} else {
    echo "MISSING";
}
?>
