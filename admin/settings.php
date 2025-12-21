<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = '';

// Handle Settings Update
if (isset($_POST['update_settings'])) {
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
    }
    $success = "Settings updated successfully!";
    
    // Log Activity
    $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'update_settings', 'Updated global system settings']);
}

// Fetch General Settings
$stmt = $pdo->query("SELECT * FROM system_settings WHERE category = 'general' OR category = 'branding' ORDER BY id ASC");
$settings = $stmt->fetchAll();

// If empty, add defaults
if (empty($settings)) {
    $defaults = [
        ['key' => 'app_name', 'val' => 'Fiora', 'cat' => 'branding'],
        ['key' => 'app_theme', 'val' => 'Earthy Clay', 'cat' => 'branding'],
        ['key' => 'allow_registration', 'val' => '1', 'cat' => 'general']
    ];
    foreach ($defaults as $d) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, category) VALUES (?, ?, ?)");
        $stmt->execute([$d['key'], $d['val'], $d['cat']]);
    }
    $stmt = $pdo->query("SELECT * FROM system_settings WHERE category = 'general' OR category = 'branding' ORDER BY id ASC");
    $settings = $stmt->fetchAll();
}

$page = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>System Settings</h1>
                    <p>Configure global application parameters and branding.</p>
                </div>
            </header>

            <div class="content-wrapper" style="max-width: 800px;">
                <div class="glass-card">
                    <h3>General Configuration</h3>
                    
                    <?php if($success): ?>
                        <div style="background: rgba(16, 185, 129, 0.1); color: #10B981; padding: 12px; border-radius: 8px; margin-top: 15px;">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" style="margin-top: 20px;">
                        <?php foreach ($settings as $s): ?>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; text-transform: capitalize;">
                                <?php echo str_replace('_', ' ', $s['setting_key']); ?>
                            </label>
                            <input type="text" name="settings[<?php echo $s['setting_key']; ?>]" value="<?php echo htmlspecialchars($s['setting_value']); ?>" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.1); background: rgba(255,255,255,0.2); color: var(--text-main);">
                        </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" name="update_settings" class="btn btn-primary" style="width: 100%;">Save All Changes</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
