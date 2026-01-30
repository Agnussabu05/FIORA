
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .modal.active { display: flex !important; }
    </style>
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
                <div style="display: flex; align-items: center; gap: 10px;">
                     <!-- Share Button -->
                    <button class="btn" onclick="openSnapshotModal()" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);">
                        📸 Share Progress
                    </button>

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
                    
                    <a href="generate_report.php" target="_blank" class="btn" style="background: white; border: 1px solid #cbd5e1; color: var(--text-main); font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        📄 Report
                    </a>
                    <button class="btn btn-primary" onclick="document.getElementById('quickAddModal').classList.add('active')">+ Quick Add</button>
                    
                    <!-- Profile Button (Requested) -->
                    <a href="profile.php" class="btn" style="background: white; border: 1px solid #e2e8f0; color: var(--text-main); text-decoration: none; display: flex; align-items: center; gap: 5px;">
                        <span>👤</span> Profile
                    </a>
                </div>
            </header>

    <!-- Snapshot Modal -->
    <div class="modal" id="snapshotModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="glass-card" style="width: 400px; padding: 0; overflow: hidden; position: relative;">
            <div style="padding: 20px; border-bottom: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">Weekly Snapshot</h3>
                <button onclick="closeSnapshotModal()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer;">✕</button>
            </div>
            
            <div style="padding: 20px; display: flex; flex-direction: column; align-items: center; background: #f8fafc;" id="snapshotPreviewContainer">
                <!-- The Card to Capture -->
                <div id="weeklySnapshotCard" style="width: 340px; background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%); border-radius: 20px; padding: 25px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); border: 1px solid white;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">Week of <?php echo date('M d'); ?></div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #1e293b;">Fiora Summary</div>
                        </div>
                        <div style="font-size: 2rem;">🚀</div>
                    </div>

                    <!-- Task Progress -->
                    <div style="background: white; padding: 15px; border-radius: 15px; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.03);">
                        <div style="font-size: 0.9rem; font-weight: 600; color: #475569; margin-bottom: 8px;">Task Completion</div>
                        <div style="display: flex; align-items: flex-end; gap: 5px; margin-bottom: 8px;">
                            <span style="font-size: 2rem; font-weight: 800; color: #0f3460; line-height: 1;"><?php echo $task_progress; ?>%</span>
                            <span style="font-size: 0.9rem; color: #94a3b8; margin-bottom: 3px;">done</span>
                        </div>
                        <div style="width: 100%; background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo $task_progress; ?>%; background: #0f3460; height: 100%; border-radius: 4px;"></div>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <div style="flex: 1; background: #dcfce7; padding: 15px; border-radius: 15px; text-align: center;">
                            <div style="font-size: 1.5rem;">✅</div>
                            <div style="font-size: 1.2rem; font-weight: 800; color: #166534;"><?php echo $completed_tasks; ?></div>
                            <div style="font-size: 0.7rem; font-weight: 700; color: #166534; text-transform: uppercase;">Tasks Done</div>
                        </div>
                        <div style="flex: 1; background: #fee2e2; padding: 15px; border-radius: 15px; text-align: center;">
                            <div style="font-size: 1.5rem;">⏳</div>
                            <div style="font-size: 1.2rem; font-weight: 800; color: #991b1b;"><?php echo $remaining_tasks; ?></div>
                            <div style="font-size: 0.7rem; font-weight: 700; color: #991b1b; text-transform: uppercase;">Pending</div>
                        </div>
                    </div>
                    
                    <!-- Mini Mood Chart -->
                    <div style="background: white; padding: 15px; border-radius: 15px;">
                        <div style="font-size: 0.9rem; font-weight: 600; color: #475569; margin-bottom: 10px;">Mood Trend</div>
                        <div style="height: 100px; width: 100%;">
                            <canvas id="snapshotMoodChart"></canvas>
                        </div>
                    </div>

                    <div style="margin-top: 20px; text-align: center; font-size: 0.8rem; color: #94a3b8; font-weight: 500;">
                        Generated by Fiora
                    </div>
                </div>
            </div>

            <div style="padding: 20px; border-top: 1px solid rgba(0,0,0,0.05); text-align: center;">
                <button onclick="downloadSnapshot()" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    📥 Download Image
                </button>
            </div>
        </div>
    </div>

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

    <!-- Quick Add Modal -->
    <div class="modal" id="quickAddModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;" onclick="if(event.target==this)this.classList.remove('active')">
        <div class="glass-card" style="width: 400px; padding: 30px; text-align: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0;">⚡ Quick Add</h3>
                <button onclick="document.getElementById('quickAddModal').classList.remove('active')" style="background: none; border: none; font-size: 1.2rem; cursor: pointer;">✕</button>
            </div>
            
            <p style="color: var(--text-muted); margin-bottom: 25px; font-size: 0.9rem;">What would you like to add?</p>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <!-- Add Task -->
                <a href="tasks.php?action=add" style="text-decoration: none;">
                    <div style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; border-radius: 12px; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateX(5px)'" onmouseout="this.style.transform='translateX(0)'">
                        <div style="width: 45px; height: 45px; background: #22c55e; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white;">✅</div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; color: #166534; font-size: 1rem;">New Task</div>
                            <div style="font-size: 0.8rem; color: #15803d;">Add a task to your to-do list</div>
                        </div>
                    </div>
                </a>
                
                <!-- Add Habit -->
                <a href="habits.php" style="text-decoration: none;">
                    <div style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #fcd34d; border-radius: 12px; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateX(5px)'" onmouseout="this.style.transform='translateX(0)'">
                        <div style="width: 45px; height: 45px; background: #f59e0b; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white;">🔥</div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; color: #92400e; font-size: 1rem;">New Habit</div>
                            <div style="font-size: 0.8rem; color: #b45309;">Track a new daily habit</div>
                        </div>
                    </div>
                </a>
                
                <!-- Add Transaction -->
                <a href="finance.php" style="text-decoration: none;">
                    <div style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; background: linear-gradient(135deg, #ede9fe, #ddd6fe); border: 1px solid #c4b5fd; border-radius: 12px; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateX(5px)'" onmouseout="this.style.transform='translateX(0)'">
                        <div style="width: 45px; height: 45px; background: #8b5cf6; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white;">💰</div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; color: #5b21b6; font-size: 1rem;">New Transaction</div>
                            <div style="font-size: 0.8rem; color: #6d28d9;">Log income or expense</div>
                        </div>
                    </div>
                </a>
                
                <!-- Add Goal -->
                <a href="goals.php" style="text-decoration: none;">
                    <div style="display: flex; align-items: center; gap: 15px; padding: 15px 20px; background: linear-gradient(135deg, #fce7f3, #fbcfe8); border: 1px solid #f9a8d4; border-radius: 12px; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateX(5px)'" onmouseout="this.style.transform='translateX(0)'">
                        <div style="width: 45px; height: 45px; background: #ec4899; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white;">🎯</div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; color: #9d174d; font-size: 1rem;">New Goal</div>
                            <div style="font-size: 0.8rem; color: #be185d;">Set a new ambition</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
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

        // --- SNAPSHOT FUNCTIONALITY ---
        let snapshotChartInstance = null;

        function openSnapshotModal() {
            const modal = document.getElementById('snapshotModal');
            modal.style.display = 'flex';
            
            // Render the specific chart for the snapshot (Mini version)
            if (snapshotChartInstance) {
                snapshotChartInstance.destroy();
            }
            
            const ctx = document.getElementById('snapshotMoodChart').getContext('2d');
            snapshotChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['M', 'T', 'W', 'T', 'F', 'S', 'S'], // Brief labels
                    datasets: [{
                        label: 'Mood',
                        data: [3, 4, 3, 5, 4, 2, 4], // Using same data for demo
                        borderColor: '#6366f1',
                        borderWidth: 2,
                        pointBackgroundColor: '#6366f1',
                        tension: 0.4,
                        fill: true,
                        backgroundColor: 'rgba(99, 102, 241, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { display: false, min: 1, max: 5 }, 
                        x: { grid: { display: false }, ticks: { fontSize: 10 } } 
                    }
                }
            });
        }

        function closeSnapshotModal() {
            document.getElementById('snapshotModal').style.display = 'none';
        }

        function downloadSnapshot() {
            const element = document.getElementById('weeklySnapshotCard');
            
            html2canvas(element, {
                scale: 2, // High resolution
                backgroundColor: null, // Transparent background support
                logging: false,
                useCORS: true // Try to capture external images if any
            }).then(canvas => {
                // Convert to image
                const link = document.createElement('a');
                link.download = 'Fiora_Weekly_Snapshot.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
</body>
</html>
