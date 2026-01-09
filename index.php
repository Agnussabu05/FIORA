<?php
require_once 'includes/db.php';
require_once 'includes/auth.php'; // Enforce login

$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'];

// Admin Redirect: If user is admin, go straight to Admin Dashboard
if (($role ?? $_SESSION['role'] ?? '') === 'admin') {
    header("Location: admin/index.php");
    exit;
}

$page = 'dashboard';

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Fetch Task Stats
$total_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE user_id = $user_id")->fetchColumn();
$completed_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE user_id = $user_id AND status = 'completed'")->fetchColumn();
$task_progress = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
$remaining_tasks = $total_tasks - $completed_tasks;
$stmt = $pdo->prepare("SELECT id, title, priority, deadline, is_admin_pushed FROM tasks WHERE user_id = ? AND status = 'pending' ORDER BY deadline ASC LIMIT 3");
$stmt->execute([$user_id]);
$upcoming_tasks = $stmt->fetchAll();

// Fetch Habits
$stmt = $pdo->prepare("SELECT id, name, is_admin_pushed FROM habits WHERE user_id = ? LIMIT 3");
$stmt->execute([$user_id]);
$user_habits = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Color Palette - Professional Earthy Clay */
            --primary: #1a1a1a;
            --primary-hover: #000000;
            --secondary: #8D8173; /* Refined Taupe */
            --bg-dark: #E3DAC9;
            --bg-gradient: linear-gradient(135deg, #E3DAC9 0%, #D6CDBF 100%);
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: rgba(255, 255, 255, 0.6);
            --text-main: #1a1a1a;
            --text-muted: #666666;
            --success: #5F7A65;
            --warning: #C78D55;
            --danger: #B05D5D;
            --spacing-md: 1.5rem;
            --radius-md: 16px;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            --shadow-card: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
        }
        * { box-sizing: border-box; }
        body {
            background-color: var(--bg-dark);
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            margin: 0;
            font-family: 'Inter', -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Layout Structure */
        .app-container {
            display: flex;
            width: 100%;
        }
        
        /* Sidebar Styling */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            background: rgba(255, 255, 255, 0.4) !important;
            backdrop-filter: blur(25px);
            border-right: 1px solid rgba(255,255,255,0.4);
            padding: 30px;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        .brand {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 40px;
            display: flex; align-items: center; gap: 10px;
            color: #1a1a1a;
            letter-spacing: -0.5px;
        }
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }
        .nav-item { margin-bottom: 5px; }
        .nav-link { 
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #555 !important; 
            font-weight: 500;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: #1a1a1a !important;
            color: #fff !important;
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        /* Main Content Styling */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 40px;
        }
        .header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;
        }
        
        /* Components */
        .glass-card {
            background: var(--glass-bg) !important;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-card);
            border-radius: var(--radius-md);
            backdrop-filter: blur(15px);
            padding: 24px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08);
        }
        h1, h2, h3, h4 { 
            color: #1a1a1a; 
            letter-spacing: -0.5px;
            font-weight: 700;
            margin: 0 0 15px 0;
        }
        .btn-primary {
            background: #1a1a1a;
            color: white; border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        .btn-primary:active { transform: scale(0.98); }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }
        
        /* Profile Helper */
        .user-profile .avatar {
            width: 40px; height: 40px; background: #8D8173; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;
        }
        .user-profile {
            display: flex; gap: 12px; align-items: center;
        }

        /* Notification Styling */
        .notification-wrapper {
            position: relative;
            margin-right: 15px;
        }
        .notification-bell {
            font-size: 1.5rem;
            cursor: pointer;
            position: relative;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-bell:hover {
            background: rgba(0,0,0,0.05);
        }
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--danger);
            color: white;
            font-size: 0.65rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .notification-dropdown {
            position: absolute;
            top: 50px;
            right: 0;
            width: 320px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--shadow-card);
            display: none;
            flex-direction: column;
            z-index: 1001;
            overflow: hidden;
            animation: fadeInDown 0.3s ease;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 700;
            font-size: 0.95rem;
            background: rgba(255,255,255,0.2);
        }
        .notification-body {
            max-height: 350px;
            overflow-y: auto;
        }
        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            display: flex;
            gap: 12px;
            transition: background 0.2s;
            cursor: default;
        }
        .notification-item:hover {
            background: rgba(255,255,255,0.4);
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <?php
                    $hour = (int)date('H');
                    if ($hour >= 5 && $hour < 12) {
                        $greeting = "Good Morning";
                        $emoji = "☀️";
                    } elseif ($hour >= 12 && $hour < 18) {
                        $greeting = "Good Afternoon";
                        $emoji = "🌤️";
                    } else {
                        $greeting = "Good Evening";
                        $emoji = "🌙";
                    }
                    ?>
                    <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($username); ?> <?php echo $emoji; ?></h1>
                    <p style="color: var(--text-muted);">Let's make today productive.</p>
                </div>
                <div style="display: flex; align-items: center;">
                    <!-- Notification Bell -->
                    <div class="notification-wrapper">
                        <div class="notification-bell" id="bellIcon">
                            <span>🔔</span>
                            <?php if (count($notifications) > 0): ?>
                                <span class="notification-badge"><?php echo count($notifications); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-dropdown" id="notifDropdown">
                            <div class="notification-header">Notifications</div>
                            <div class="notification-body">
                                <?php if (empty($notifications)): ?>
                                    <div class="notification-empty">No new notifications</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $note): ?>
                                        <div class="notification-item">
                                            <div style="font-size: 1.2rem;">📩</div>
                                            <div style="flex: 1;">
                                                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); line-height: 1.4;">
                                                    <?php echo htmlspecialchars($note['message']); ?>
                                                </div>
                                                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">
                                                    <?php echo date('M d, H:i', strtotime($note['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" onclick="alert('Quick add feature coming soon!')">+ Quick Add</button>
                </div>
            </header>

            <!-- Static Notifications Section removed for interactive dropdown -->

            <!-- AI Insight Widget -->
            <section class="glass-card" style="margin-bottom: var(--spacing-md); border-left: 4px solid var(--secondary); display: flex; align-items: center; gap: 20px;">
                <div style="font-size: 2.5rem;">🤖</div>
                <div>
                    <h3 style="margin-bottom: 5px;">Fiora Assistant Insight</h3>
                    <p id="ai-message" style="margin: 0; color: var(--text-muted);">
                        <?php 
                        if ($total_tasks > 0) {
                            echo "You have $remaining_tasks pending tasks. Keep going!";
                        } else {
                            echo "Analyzing your day... You have 0 pending tasks and your mood seems stable. Consider setting a goal for today!";
                        }
                        ?>
                    </p>
                </div>
            </section>

            <div class="dashboard-grid">
                <!-- Task Summary -->
                <div class="glass-card">
                    <h3>Tasks Overview</h3>
                    <div style="margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 500;">Progress</span>
                            <span style="font-weight: 600;"><?php echo $task_progress; ?>%</span>
                        </div>
                        <div style="width: 100%; background: rgba(0,0,0,0.05); border-radius: 10px; height: 10px; overflow: hidden;">
                            <div style="width: <?php echo $task_progress; ?>%; background: var(--primary); height: 100%; border-radius: 10px;"></div>
                        </div>
                        <p style="margin-top: 15px; font-size: 0.9rem; color: var(--text-muted);"><?php echo $remaining_tasks; ?> tasks remaining</p>
                        <div style="margin-top: 15px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 10px;">
                            <?php if (empty($upcoming_tasks)): ?>
                                <p style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">No pending tasks</p>
                            <?php else: ?>
                                <?php foreach ($upcoming_tasks as $ut): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                        <div style="font-size: 0.85rem; display: flex; align-items: center; gap: 8px;">
                                            <span>📝</span>
                                            <span><?php echo htmlspecialchars($ut['title']); ?></span>
                                            <?php if ($ut['is_admin_pushed']): ?>
                                                <span style="font-size: 0.6rem; background: var(--secondary); color: white; padding: 1px 4px; border-radius: 3px;">🛡️</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Habits -->
                <div class="glass-card">
                    <h3>Today's Habits</h3>
                    <div style="margin-top: 20px;">
                        <?php if (empty($user_habits)): ?>
                            <div style="text-align: center; color: var(--text-muted); font-style: italic; padding: 10px;">
                                No habits tracked yet.
                            </div>
                        <?php else: ?>
                            <?php foreach($user_habits as $h): ?>
                                <div style="display: flex; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 8px 0;">
                                    <span>🔥</span>
                                    <span style="margin-left: 10px; font-size: 0.9rem; flex: 1;"><?php echo htmlspecialchars($h['name']); ?></span>
                                    <?php if ($h['is_admin_pushed']): ?>
                                        <span title="Pushed by Admin" style="font-size: 0.7rem; background: var(--secondary); color: white; padding: 2px 6px; border-radius: 4px; opacity: 0.8;">🛡️ Admin</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Finance -->
                <div class="glass-card">
                    <h3>Monthly Spend</h3>
                    <div style="display: flex; align-items: center; gap: 15px; margin-top: 20px;">
                        <div style="width: 120px; height: 120px; position: relative;">
                            <canvas id="financeChart"></canvas>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                            <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                                <span style="width: 10px; height: 10px; background: #1a1a1a; border-radius: 2px;"></span> Food
                            </div>
                            <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                                <span style="width: 10px; height: 10px; background: #8D8173; border-radius: 2px;"></span> Transport
                            </div>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <span style="width: 10px; height: 10px; background: #5F7A65; border-radius: 2px;"></span> Bills
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity / Mood Graph Row -->
            <div class="dashboard-grid" style="margin-top: var(--spacing-md); grid-template-columns: 2fr 1fr;">
                <div class="glass-card">
                    <h3>Mood Analytics (Weekly)</h3>
                    <div style="position: relative; height: 200px; width: 100%; margin-top: 15px;">
                        <canvas id="moodChart"></canvas>
                    </div>
                </div>
                <div class="glass-card" style="display: flex; flex-direction: column; justify-content: center; align-items: center;">
                    <h3 style="align-self: flex-start;">Focus Timer</h3>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 3.5rem; font-weight: 800; margin-bottom: 20px; letter-spacing: -1px;">25:00</div>
                        <button class="btn btn-primary" style="padding: 12px 30px; border-radius: 12px;">Start Focus</button>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Charts - Consistent with the professional Earthy Clay theme
        const moodCtx = document.getElementById('moodChart').getContext('2d');
        new Chart(moodCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Mood Score',
                    data: [3, 4, 3, 5, 4, 2, 4],
                    borderColor: '#1a1a1a',
                    borderWidth: 2,
                    pointBackgroundColor: '#1a1a1a',
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(26, 26, 26, 0.05)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        max: 5,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { stepSize: 1, color: '#888' }
                    }, 
                    x: { 
                        grid: { display: false },
                        ticks: { color: '#888' }
                    } 
                }
            }
        });

        const financeCtx = document.getElementById('financeChart').getContext('2d');
        new Chart(financeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Food', 'Transport', 'Bills'],
                datasets: [{
                    data: [300, 150, 100],
                    backgroundColor: ['#1a1a1a', '#8D8173', '#5F7A65'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: { 
                cutout: '70%', 
                plugins: { legend: { display: false } } 
            }
        });
        // Toggle Notifications
        const bell = document.getElementById('bellIcon');
        const dropdown = document.getElementById('notifDropdown');
        
        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
        });

        document.addEventListener('click', () => {
            dropdown.style.display = 'none';
        });

        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    </script>
</body>
</html>
