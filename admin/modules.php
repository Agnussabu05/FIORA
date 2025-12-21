<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Handle Module Toggle
if (isset($_POST['toggle_module'])) {
    $module_key = $_POST['module_key'];
    $current_val = $_POST['current_value'] == '1' ? '0' : '1';
    
    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$current_val, $module_key]);
    
    // Log Activity
    $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'toggle_module', "Changed $module_key to $current_val"]);
}

// Fetch Modules
$stmt = $pdo->query("SELECT * FROM system_settings WHERE category = 'modules' ORDER BY setting_key ASC");
$modules = $stmt->fetchAll();

$page = 'modules';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Management - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .module-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            padding: 24px;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .module-info h3 {
            margin: 0;
            text-transform: capitalize;
            font-size: 1.1rem;
        }
        .module-info p {
            margin: 4px 0 0 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .toggle-btn {
            padding: 8px 16px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-enabled { background: #10B981; color: white; }
        .btn-disabled { background: #6B7280; color: white; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>Module Management</h1>
                    <p>Enable or disable Fiora features system-wide.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="module-grid">
                    <?php foreach ($modules as $mod): ?>
                    <?php 
                        $displayName = str_replace(['module_', '_enabled'], '', $mod['setting_key']);
                    ?>
                    <div class="module-card">
                        <div class="module-info">
                            <h3><?php echo htmlspecialchars($displayName); ?></h3>
                            <p>Status: <?php echo $mod['setting_value'] == '1' ? 'Enabled' : 'Disabled'; ?></p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="module_key" value="<?php echo $mod['setting_key']; ?>">
                            <input type="hidden" name="current_value" value="<?php echo $mod['setting_value']; ?>">
                            <button type="submit" name="toggle_module" class="toggle-btn <?php echo $mod['setting_value'] == '1' ? 'btn-enabled' : 'btn-disabled'; ?>">
                                <?php echo $mod['setting_value'] == '1' ? 'Disable' : 'Enable'; ?>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
