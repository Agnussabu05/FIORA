<?php
require_once 'includes/db.php';

$username = 'admin';
$password = 'admin 123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, role = ? WHERE username = ?");
        $stmt->execute([$hash, $role, $username]);
        echo "Admin user updated successfully.\n";
    } else {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hash, $role]);
        echo "Admin user created successfully.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
