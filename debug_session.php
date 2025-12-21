<?php
session_start();
require_once 'includes/db.php';

echo "<h1>Debug Info</h1>";
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not Set') . "<br>";
echo "Username: " . ($_SESSION['username'] ?? 'Not Set') . "<br>";
echo "Role in Session: " . ($_SESSION['role'] ?? 'Not Set') . "<br>";

if (isset($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h2>Database Record</h2>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
} else {
    echo "No user logged in to check DB.<br>";
}

echo "<br><a href='logout.php'>Logout</a> | <a href='login.php'>Login</a>";
?>
