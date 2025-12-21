<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = '';
$error = '';

// Handle Password Change
if (isset($_POST['change_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if ($new_pass === $confirm_pass) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        $success = "Password updated successfully!";
        
        // Log Activity
        $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'change_password', 'Admin changed their own password']);
    } else {
        $error = "Passwords do not match.";
    }
}

$page = 'security';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Management - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .security-container {
            max-width: 600px;
            margin-top: 20px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-input { 
            width: 100%; 
            padding: 12px; 
            border-radius: 10px; 
            border: 1px solid rgba(0,0,0,0.1); 
            background: rgba(255,255,255,0.2);
            color: var(--text-main);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>Security Management</h1>
                    <p>Maintain your account security and monitor access.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="security-container">
                    <div class="glass-card">
                        <h3>Change Admin Password</h3>
                        
                        <?php if($success): ?>
                            <div style="background: rgba(16, 185, 129, 0.1); color: #10B981; padding: 12px; border-radius: 8px; margin-top: 15px;">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($error): ?>
                            <div style="background: rgba(220, 38, 38, 0.1); color: #DC2626; padding: 12px; border-radius: 8px; margin-top: 15px;">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" style="margin-top: 20px;">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-input" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary" style="width: 100%;">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
