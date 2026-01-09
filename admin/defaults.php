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

// Handle New Default
if (isset($_POST['add_default'])) {
    $type = $_POST['type'];
    $title = $_POST['title'];
    $desc = $_POST['description'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO system_defaults (type, title, description, priority) VALUES (?, ?, ?, ?)");
        $stmt->execute([$type, $title, $desc, $priority]);
        $success = "Global default added successfully!";
        
        // Log Activity
        $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'add_default', "Added $type default: $title"]);
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM system_defaults WHERE id = ?");
    $stmt->execute([$id]);
}

// Handle Sync (Push to All Existing Users who don't have it)
if (isset($_POST['sync_defaults'])) {
    try {
        $defaults = $pdo->query("SELECT * FROM system_defaults")->fetchAll();
        $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($defaults as $d) {
            if ($d['type'] === 'task') {
                $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, priority, is_admin_pushed) VALUES (?, ?, ?, ?, 1)");
            } else {
                $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, is_admin_pushed) VALUES (?, ?, 1)");
            }
            
            foreach ($users as $u_id) {
                // Check if user already has a task/habit with this title
                if ($d['type'] === 'task') {
                    $check = $pdo->prepare("SELECT id FROM tasks WHERE user_id = ? AND title = ?");
                } else {
                    $check = $pdo->prepare("SELECT id FROM habits WHERE user_id = ? AND name = ?");
                }
                $check->execute([$u_id, $d['title']]);
                
                if (!$check->fetch()) {
                    if ($d['type'] === 'task') {
                        $stmt->execute([$u_id, $d['title'], $d['description'], $d['priority']]);
                    } else {
                        $stmt->execute([$u_id, $d['title']]);
                    }
                }
            }
        }
        $success = "Successfully synced defaults to all existing users!";
    } catch (PDOException $e) {
        $error = "Sync failed: " . $e->getMessage();
    }
}

// Fetch Current Defaults
$defaults = $pdo->query("SELECT * FROM system_defaults ORDER BY type ASC, created_at DESC")->fetchAll();

$page = 'defaults';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Defaults - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .defaults-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-top: 20px; }
        .default-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .badge {
            font-size: 0.7rem; padding: 3px 8px; border-radius: 6px; font-weight: 700; text-transform: uppercase;
        }
        .badge-task { background: var(--secondary); color: white; }
        .badge-habit { background: var(--success); color: white; }
        .delete-btn { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>Global Default Templates</h1>
                    <p>Define tasks and habits that every new user gets by default.</p>
                </div>
                <form method="POST">
                    <button type="submit" name="sync_defaults" class="btn btn-primary" onclick="return confirm('Ensure all existing users have these defaults? This will not duplicate existing items.')">🔄 Sync to All Users</button>
                </form>
            </header>

            <div class="content-wrapper">
                <?php if($success): ?>
                    <div style="background: rgba(16, 185, 129, 0.1); color: #10B981; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <div class="defaults-grid">
                    <div class="glass-card">
                        <h3>Add New Default</h3>
                        <form method="POST" style="margin-top: 20px;">
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Template Type</label>
                                <select name="type" class="form-input" style="width: 100%;" onchange="toggleTaskFields(this.value)">
                                    <option value="task">Default Task ✅</option>
                                    <option value="habit">Default Habit 🔥</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Title</label>
                                <input type="text" name="title" id="default-title" class="form-input" style="width: 100%;" required>
                            </div>
                            <div id="task-fields">
                                <div style="margin-bottom: 15px;">
                                    <label style="display:block; margin-bottom:5px;">Description</label>
                                    <textarea name="description" class="form-input" style="width: 100%; height: 80px;"></textarea>
                                </div>
                                <div style="margin-bottom: 15px;">
                                    <label style="display:block; margin-bottom:5px;">Priority</label>
                                    <select name="priority" class="form-input" style="width: 100%;">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="add_default" class="btn btn-primary" style="width: 100%;">💾 Save Template</button>
                        </form>
                    </div>

                    <div class="glass-card">
                        <h3>Active Templates</h3>
                        <div style="margin-top: 20px;">
                            <?php if(empty($defaults)): ?>
                                <p style="text-align: center; color: var(--text-muted); padding: 50px;">No global defaults set.</p>
                            <?php else: ?>
                                <?php foreach ($defaults as $d): ?>
                                <div class="default-card">
                                    <div>
                                        <span class="badge badge-<?php echo $d['type']; ?>"><?php echo $d['type']; ?></span>
                                        <div style="font-weight: 600; margin-top: 5px;"><?php echo htmlspecialchars($d['title']); ?></div>
                                        <?php if($d['type'] === 'task'): ?>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">Priority: <?php echo ucfirst($d['priority']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Delete this global template? Existing users will keep their copies.');" style="margin:0;">
                                        <input type="hidden" name="delete_id" value="<?php echo $d['id']; ?>">
                                        <button type="submit" class="delete-btn">🗑️</button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleTaskFields(type) {
            document.getElementById('task-fields').style.display = (type === 'task') ? 'block' : 'none';
        }

        const titleInput = document.getElementById('default-title');
        
        titleInput.addEventListener('blur', function() {
            validateField(this);
        });

        titleInput.addEventListener('input', function() {
            if(this.value.trim() !== '') {
                clearError(this);
            }
        });

        function validateField(field) {
            const existingError = field.parentNode.querySelector('.error-message');
            
            if (field.value.trim() === '') {
                if (!existingError) {
                    const error = document.createElement('div');
                    error.className = 'error-message';
                    error.style.color = '#ef4444'; // Red
                    error.style.fontSize = '0.85rem';
                    error.style.marginTop = '5px';
                    error.innerText = 'This field is compulsory';
                    field.parentNode.appendChild(error);
                    field.style.borderColor = '#ef4444';
                }
            } else {
                clearError(field);
            }
        }

        function clearError(field) {
            const existingError = field.parentNode.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
                field.style.borderColor = ''; // Reset
            }
        }
    </script>
</body>
</html>
