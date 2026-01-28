<?php
require_once 'includes/db.php';
session_start();
$user_id = $_SESSION['user_id'] ?? 1; // Fallback to 1 for testing if session lost

echo "<h1>Debug Tasks</h1>";
echo "User ID: $user_id<br>";

// 1. Check all tasks
$stmt = $pdo->prepare("SELECT id, title, deadline, status, category FROM tasks WHERE user_id = ?");
$stmt->execute([$user_id]);
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>All Tasks:</h3><pre>";
print_r($all);
echo "</pre>";

// 2. Check Overdue Query
$sql = "SELECT * FROM tasks WHERE user_id = ? AND status = 'pending' AND deadline < NOW()";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Overdue Tasks (SQL NOW()):</h3><pre>";
print_r($overdue);
echo "</pre>";

// 3. Check PHP Time vs MySQL Time
$stmt = $pdo->query("SELECT NOW() as mysql_time");
$mysql_time = $stmt->fetchColumn();
echo "MySQL Time: " . $mysql_time . "<br>";
echo "PHP Time: " . date('Y-m-d H:i:s') . "<br>";

?>
