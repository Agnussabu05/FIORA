<?php
require_once 'includes/db.php';
// Hard Reset for Notification ID 1 which was corrupted/mojibake
$sql = "UPDATE notifications 
        SET message = '💰 Cha-ching! agnussabu123 just bought your book \'5 Am club\' for ₹0.00.' 
        WHERE id = 1";
$pdo->exec($sql);
echo "Reset ID 1 to clean UTF-8 string.";
?>
