<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'habits';

// Handle AJAX Toggle
if (isset($_GET['ajax_toggle'])) {
    $habit_id = $_GET['habit_id'];
    $check_date = $_GET['date'];
    
    $checkInfo = $pdo->prepare("SELECT id FROM habit_logs WHERE habit_id = ? AND check_in_date = ?");
    $checkInfo->execute([$habit_id, $check_date]);
    $log = $checkInfo->fetch();
    
    if ($log) {
        $pdo->prepare("DELETE FROM habit_logs WHERE id = ?")->execute([$log['id']]);
        echo json_encode(['status' => 'removed']);
    } else {
        $pdo->prepare("INSERT INTO habit_logs (habit_id, check_in_date, status) VALUES (?, ?, 'completed')")->execute([$habit_id, $check_date]);
        echo json_encode(['status' => 'added']);
    }
    exit;
}

// Handle Add Habit (Bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $names = $_POST['name'];
    $frequencies = $_POST['frequency'];
    $emojis = $_POST['emoji'];
    $is_global = isset($_POST['is_global']) && ($_SESSION['role'] ?? '') === 'admin';
    
    foreach ($names as $index => $name) {
        if (empty(trim($name))) continue;
        
        $frequency = 'daily'; // Default
        $emoji = ''; // Default
        
        if ($is_global) {
            $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
            $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, frequency, emoji, is_admin_pushed) VALUES (?, ?, ?, ?, 1)");
            foreach ($users as $u_id) {
                $stmt->execute([$u_id, $name, $frequency, $emoji]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, frequency, emoji) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $frequency, $emoji]);
        }
    }
    header("Location: habits.php"); exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM habits WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
    header("Location: habits.php"); exit;
}

// Date Navigation
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$currentMonthName = date("F Y", mktime(0, 0, 0, $month, 1, $year));

// Fetch Habits
$stmt = $pdo->prepare("SELECT * FROM habits WHERE user_id = ?");
$stmt->execute([$user_id]);
$habits = $stmt->fetchAll();

// Fetch Logs for this month
$startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$endDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-$daysInMonth";
$logStmt = $pdo->prepare("SELECT habit_id, check_in_date FROM habit_logs WHERE check_in_date BETWEEN ? AND ?");
$logStmt->execute([$startDate, $endDate]);
$allLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$logsByHabit = [];
foreach ($allLogs as $l) {
    if (!isset($logsByHabit[$l['habit_id']])) $logsByHabit[$l['habit_id']] = [];
    $logsByHabit[$l['habit_id']][] = $l['check_in_date'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Habit Grid</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .habit-grid-container {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            overflow-x: auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.05);
        }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th, td { padding: 12px 8px; text-align: center; border-bottom: 1px solid var(--glass-border); }
        
        .habit-info-col { text-align: left; position: sticky; left: 0; background: #faf8f5; z-index: 10; min-width: 200px; padding-left: 0; }
        .day-header { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; width: 35px; }
        .day-header.today { color: var(--primary); background: rgba(105, 126, 80, 0.1); border-radius: 8px; }
        
        .check-dot {
            width: 22px; height: 22px; border-radius: 6px; border: 2px solid var(--glass-border);
            background: white; cursor: pointer; display: inline-block; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .check-dot:hover { transform: scale(1.2); border-color: var(--primary); }
        .check-dot.completed { background: var(--primary); border-color: var(--primary); box-shadow: 0 0 10px rgba(105, 126, 80, 0.4); }
        
        .nav-btn {
            background: white; border: 1px solid var(--glass-border); padding: 8px 15px;
            border-radius: 10px; cursor: pointer; text-decoration: none; color: var(--text-main);
            font-weight: 600; font-size: 0.9rem; transition: all 0.3s;
        }
        .nav-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4); backdrop-filter: blur(8px);
            align-items: center; justify-content: center; z-index: 1000;
        }
        .modal.active { display: flex; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div>
                    <h1>Habit Consistency 🔥</h1>
                    <p style="color: var(--text-muted);">Visualize your progress across the month.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ Add New Habit</button>
            </header>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 style="font-size: 1.5rem; color: var(--primary);"><?php echo $currentMonthName; ?></h2>
                <div style="display: flex; gap: 10px;">
                    <?php 
                    $prevMonth = $month - 1; $prevYear = $year; if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
                    $nextMonth = $month + 1; $nextYear = $year; if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
                    ?>
                    <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="nav-btn">← Prev</a>
                    <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" class="nav-btn">Current</a>
                    <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="nav-btn">Next →</a>
                </div>
            </div>

            <div class="habit-grid-container">
                <table>
                    <thead>
                        <tr>
                            <th class="habit-info-col" style="background: transparent;">Habits</th>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                $isToday = ($d == (int)date('d') && $month == (int)date('m') && $year == (int)date('Y'));
                            ?>
                                <th class="day-header <?php echo $isToday ? 'today' : ''; ?>">
                                    <?php echo $d; ?>
                                </th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($habits) > 0): ?>
                            <?php foreach($habits as $habit): ?>
                                <tr>
                                    <td class="habit-info-col">
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding-right: 15px;">
                                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                                <?php echo htmlspecialchars($habit['name']); ?>
                                            </div>
                                            <a href="?delete=<?php echo $habit['id']; ?>" style="color: var(--danger); text-decoration: none; font-size: 0.9rem;" onclick="return confirm('Remove habit?')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                    <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                        $cellDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                                        $isDone = isset($logsByHabit[$habit['id']]) && in_array($cellDate, $logsByHabit[$habit['id']]);
                                    ?>
                                        <td>
                                            <div class="check-dot <?php echo $isDone ? 'completed' : ''; ?>" 
                                                 onclick="toggleHabit(this, <?php echo $habit['id']; ?>, '<?php echo $cellDate; ?>')">
                                            </div>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $daysInMonth + 1; ?>" style="padding: 60px; text-align: center; color: var(--text-muted);">
                                    <div style="font-size: 3rem; margin-bottom: 15px;">🌱</div>
                                    <div style="font-size: 1.1rem; font-weight: 500;">Your habit garden is empty!</div>
                                    <div style="font-size: 0.95rem; margin-top: 10px;">Start building consistency by adding your first habit. Small steps lead to big changes.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="habitModal">
        <div class="glass-card" style="width: 500px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Add New Habits</h3>
                <button type="button" class="btn btn-primary" onclick="addHabitRow()" style="padding: 5px 12px; font-size: 0.8rem;">+ Add Row</button>
            </div>
            
            <form action="habits.php" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div id="habit-rows-container">
                    <!-- First Row (Mandatory) -->
                    <div class="habit-form-row" style="background: rgba(255,255,255,0.4); padding: 15px; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 15px; position: relative;">
                        <div class="form-group">
                            <label>Habit Name</label>
                            <input type="text" name="name[]" class="form-input" required placeholder="Daily Exercise, Meditation, etc.">
                        </div>

                    </div>
                </div>

                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 5px; background: rgba(105, 126, 80, 0.1); padding: 10px; border-radius: 10px;">
                        <input type="checkbox" name="is_global" id="is_global_habit" style="width: 18px; height: 18px;">
                        <label for="is_global_habit" style="margin-bottom: 0; cursor: pointer; font-weight: 600; color: var(--secondary);">✨ Global Habit (Push to all Users)</label>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="color: #000 !important; font-weight: 800;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start Tracking</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('habitModal').classList.add('active'); }
        function closeModal() { document.getElementById('habitModal').classList.remove('active'); }

        function addHabitRow() {
            const container = document.getElementById('habit-rows-container');
            const row = document.createElement('div');
            row.className = 'habit-form-row';
            row.style = "background: rgba(255,255,255,0.4); padding: 15px; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 15px; position: relative; animation: fadeIn 0.3s ease;";
            row.innerHTML = `
                <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 10px; right: 10px; border: none; background: none; color: var(--danger); cursor: pointer; font-weight: bold;">✕</button>
                <div class="form-group">
                    <label>Habit Name</label>
                    <input type="text" name="name[]" class="form-input" required placeholder="Next habit...">
                </div>

            `;
            container.appendChild(row);
        }

        async function toggleHabit(el, habitId, date) {
            try {
                const response = await fetch(`habits.php?ajax_toggle=1&habit_id=${habitId}&date=${date}`);
                const data = await response.json();
                if (data.status === 'added') {
                    el.classList.add('completed');
                } else {
                    el.classList.remove('completed');
                }
            } catch (e) { console.error("Toggle failed", e); }
        }

        document.getElementById('habitModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('habitModal')) closeModal();
        });
    </script>
</body>
</html>
