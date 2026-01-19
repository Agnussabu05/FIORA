<?php
require_once 'includes/db.php';
require_once 'includes/auth.php'; // Enforce login

if (!$pdo) {
    die('<div style="font-family: sans-serif; text-align: center; padding: 50px;">
            <h1>System Unavailable</h1>
            <p>The database connection failed. Please ensure MySQL is running.</p>
            <p style="color: #666; font-size: 0.9em;">' . ($db_connection_error ?? 'Unknown error') . '</p>
         </div>');
}

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
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
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
                                <p style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">✅ All clear! No pending tasks. You're on top of things!</p>
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
                                🔥 Start building better habits today. Track your daily routines and watch your consistency grow!
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
                    borderColor: '#0f3460',
                    borderWidth: 2,
                    pointBackgroundColor: '#0f3460',
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(15, 52, 96, 0.05)'
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
                    backgroundColor: ['#0f3460', '#64748b', '#2ecc71'],
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
