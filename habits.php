<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'habits';

// Handle Add Habit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = $_POST['name'];
    $frequency = $_POST['frequency'];
    $is_global = isset($_POST['is_global']) && ($_SESSION['role'] ?? '') === 'admin';
    
    if ($is_global) {
        // Push to ALL users
        $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, frequency, is_admin_pushed) VALUES (?, ?, ?, 1)");
        foreach ($users as $u_id) {
            $stmt->execute([$u_id, $name, $frequency]);
        }
        
        // Also add to system_defaults for future users
        $def_stmt = $pdo->prepare("INSERT INTO system_defaults (type, title) VALUES ('habit', ?)");
        $def_stmt->execute([$name]);
    } else {
        // Just for current user
        $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, frequency) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $name, $frequency]);
    }
    header("Location: habits.php");
    exit;
}

// Handle Check-in / Delete (Simple toggle for today not fully implemented in one click without AJAX, so we do reload)
if (isset($_GET['checkin'])) {
    $habit_id = $_GET['checkin'];
    $today = date('Y-m-d');
    
    // Check if already checked in
    $checkInfo = $pdo->prepare("SELECT id FROM habit_logs WHERE habit_id = ? AND check_in_date = ?");
    $checkInfo->execute([$habit_id, $today]);
    
    if (!$checkInfo->fetch()) {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO habit_logs (habit_id, check_in_date, status) VALUES (?, ?, 'completed')");
        $stmt->execute([$habit_id, $today]);
    }
    header("Location: habits.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM habits WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: habits.php");
    exit;
}

// Fetch Habits
$stmt = $pdo->prepare("SELECT * FROM habits WHERE user_id = ?");
$stmt->execute([$user_id]);
$habits = $stmt->fetchAll();

// Fetch today's logs to see what's done
$logStmt = $pdo->prepare("SELECT habit_id FROM habit_logs WHERE check_in_date = ?");
$logStmt->execute([date('Y-m-d')]);
$todaysLogs = $logStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Habit Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .habit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .habit-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: transform 0.2s;
        }
        .habit-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        
        .check-btn {
            width: 50px; height: 50px;
            border-radius: 50%;
            border: 2px solid var(--secondary);
            margin-top: 15px;
            display: flex; align-items: center; justify-content: center;
            color: var(--secondary);
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .check-btn.completed {
            background: var(--secondary);
            color: white;
            box-shadow: 0 0 15px var(--secondary);
        }

        .delete-icon {
            position: absolute; top: 10px; right: 10px;
            color: var(--text-muted); opacity: 0.5;
            cursor: pointer;
        }
        .delete-icon:hover { opacity: 1; color: var(--danger); }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Habit Tracker 🔥</h1>
                    <p style="color: var(--text-muted);">Build consistency, one day at a time.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ New Habit</button>
            </header>

            <div class="glass-card">
                <h3>Today's Habits (<?php echo date('D, M d'); ?>)</h3>
                <div class="habit-grid">
                    <?php if (count($habits) > 0): ?>
                        <?php foreach($habits as $habit): 
                            $isCompleted = in_array($habit['id'], $todaysLogs);
                        ?>
                            <div class="habit-card">
                                <a href="?delete=<?php echo $habit['id']; ?>" class="delete-icon">✕</a>
                                <h4 style="font-size: 1.1rem;"><?php echo htmlspecialchars($habit['name']); ?></h4>
                                <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo ucfirst($habit['frequency']); ?></span>
                                
                                <a href="<?php echo $isCompleted ? '#' : '?checkin=' . $habit['id']; ?>" 
                                   class="check-btn <?php echo $isCompleted ? 'completed' : ''; ?>">
                                   <?php echo $isCompleted ? '✓' : ''; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No habits yet. Start tracking a new habit!</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Modal -->
    <div class="modal" id="habitModal">
        <div class="glass-card" style="width: 350px;">
            <h3 style="margin-bottom: 20px;">Create New Habit</h3>
            <form action="habits.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Habit Name</label>
                    <input type="text" name="name" class="form-input" required placeholder="Drink Water, Read 10 pages...">
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Frequency</label>
                    <select name="frequency" class="form-input">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                        <input type="checkbox" name="is_global" id="is_global_habit" style="width: 18px; height: 18px;">
                        <label for="is_global_habit" style="margin-bottom: 0; cursor: pointer; font-weight: 600; color: var(--secondary);">✨ Push as Global Habit (All Users)</label>
                    </div>
                <?php endif; ?>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start Tracking</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('habitModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('habitModal').classList.remove('active');
        }
        // Close on click outside
        document.getElementById('habitModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('habitModal')) closeModal();
        });
    </script>
</body>
</html>
