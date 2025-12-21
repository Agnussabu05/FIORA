<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    // Refresh user data
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
    
    if ($role) {
        $_SESSION['role'] = $role;
        echo "<h1>Session Updated!</h1>";
        echo "<p>Your role is now: <strong>" . htmlspecialchars($role) . "</strong></p>";
        echo "<p><a href='admin/index.php'>Go to Admin Dashboard</a></p>";
    } else {
        echo "Error: User not found in DB.";
    }
} else {
    echo "You are not logged in. Please <a href='login.php'>login</a>.";
}
?>
