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

// Handle Notification (Global or Targeted)
if (isset($_POST['send_alert'])) {
    $msg = $_POST['message'];
    $target_user_id = $_POST['user_id']; // 'all' or specific ID
    
    try {
        if ($target_user_id === 'all') {
            // Global notification (user_id is NULL)
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (NULL, ?, 'info')");
            $stmt->execute([$msg]);
            $log_details = "Sent global alert: $msg";
        } else {
            // Targeted notification
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'info')");
            $stmt->execute([(int)$target_user_id, $msg]);
            $log_details = "Sent targeted alert to user ID $target_user_id: $msg";
        }
        
        // Log Activity
        $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'send_notification', $log_details]);
        $success = "Notification sent successfully!";
    } catch (PDOException $e) {
        $error = "Error sending notification: " . $e->getMessage();
    }
}

// Handle Push Feature (Task/Habit)
if (isset($_POST['push_feature'])) {
    $feature_type = $_POST['feature_type']; // 'task' or 'habit'
    $title = $_POST['title'];
    
    try {
        $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        
        if ($feature_type === 'task') {
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, status, is_admin_pushed) VALUES (?, ?, 'pending', 1)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, is_admin_pushed) VALUES (?, ?, 1)");
        }
        
        foreach ($users as $u_id) {
            $stmt->execute([$u_id, $title]);
        }
        
        // Log Activity
        $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'push_feature', "Pushed $feature_type: $title to all users"]);
        $success = "Feature '$title' pushed to all users!";
    } catch (PDOException $e) {
        $error = "Error pushing feature: " . $e->getMessage();
    }
}

// Fetch users for dropdown
$users_list = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();

$page = 'notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-select {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.2);
            color: var(--text-main);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>Notifications & Alerts</h1>
                    <p>Manage broadcasts and push defaults to users.</p>
                </div>
            </header>

            <div class="content-wrapper" style="max-width: 800px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <!-- Notification Box -->
                <div class="glass-card">
                    <h3>Send Notification</h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px;">
                        Communicate with your users.
                    </p>
                    
                    <?php if($success): ?>
                        <div style="background: rgba(16, 185, 129, 0.1); color: #10B981; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <label style="display: block; margin-bottom: 8px; font-size: 0.9rem;">Recipient</label>
                        <select name="user_id" class="form-select">
                            <option value="all">Everywhere (All Users)</option>
                            <?php foreach($users_list as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label style="display: block; margin-bottom: 8px; font-size: 0.9rem;">Message</label>
                        <textarea name="message" class="form-input" style="width: 100%; height: 100px; margin-bottom: 20px;" placeholder="Message content..." required></textarea>
                        
                        <button type="submit" name="send_alert" class="btn btn-primary" style="width: 100%;">🚀 Send Alert</button>
                    </form>
                </div>
                
                <!-- Push Feature Box -->
                <div class="glass-card">
                    <h3>Push Default Feature</h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px;">
                        Add a task or habit to every user's list.
                    </p>

                    <form method="POST">
                        <label style="display: block; margin-bottom: 8px; font-size: 0.9rem;">Feature Type</label>
                        <select name="feature_type" class="form-select">
                            <option value="task">New Task ✅</option>
                            <option value="habit">New Habit 🔥</option>
                        </select>

                        <label style="display: block; margin-bottom: 8px; font-size: 0.9rem;">Title / Description</label>
                        <input type="text" name="title" class="form-input" style="width: 100%; margin-bottom: 20px;" placeholder="e.g. Drink 2L water" required>
                        
                        <button type="submit" name="push_feature" class="btn btn-primary" style="width: 100%; background: var(--secondary);">✨ Push to Everyone</button>
                    </form>
                    
                    <div style="margin-top: 20px; font-size: 0.85rem; color: var(--text-muted);">
                        <p>Note: Users can delete these items if they choose to.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
