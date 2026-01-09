<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch Stats
$stats = [
    'users' => 0,
    'tasks' => 0,
    'habits' => 0,
    'expenses' => 0,
    'study_hours' => 0,
    'ai_usage' => 142, // Mock for now
    'active_today' => 0,
    'active_week' => 0
];

$charts = [
    'activity' => [],
    'modules' => [],
    'growth' => [],
    'mood' => []
];

try {
    // Basic Counts
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Expanded Stats
    $stats['tasks_total'] = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $stats['tasks_pending'] = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn();
    $stats['tasks_completed'] = $stats['tasks_total'] - $stats['tasks_pending'];

    $stats['habits_total'] = $pdo->query("SELECT COUNT(*) FROM habits")->fetchColumn();
    // Active habits: logged in last 7 days
    $active_habits_count = $pdo->query("SELECT COUNT(DISTINCT habit_id) FROM habit_logs WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    $stats['habits_active'] = $active_habits_count;
    $stats['habits_inactive'] = $stats['habits_total'] - $active_habits_count;

    $stats['expenses'] = $pdo->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
    
    // Study Hours
    $stmt = $pdo->query("SELECT SUM(duration_minutes) FROM study_sessions");
    $stats['study_hours'] = round(($stmt->fetchColumn() ?: 0) / 60, 1);

    // Active Users (Today) - Distinct users who did something today
    $today = date('Y-m-d');
    $sql_active_today = "
        SELECT COUNT(DISTINCT user_id) FROM (
            SELECT user_id FROM tasks WHERE DATE(created_at) = '$today'
            UNION ALL SELECT user_id FROM habits WHERE DATE(created_at) = '$today'
            UNION ALL SELECT user_id FROM expenses WHERE transaction_date = '$today'
            UNION ALL SELECT user_id FROM study_sessions WHERE DATE(session_date) = '$today'
            UNION ALL SELECT user_id FROM mood_logs WHERE DATE(log_date) = '$today'
        ) as activity
    ";
    $stats['active_today'] = $pdo->query($sql_active_today)->fetchColumn();
    

    // Active Users (This Week)
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $sql_active_week = "
        SELECT COUNT(DISTINCT user_id) FROM (
            SELECT user_id FROM tasks WHERE DATE(created_at) >= '$week_start'
            UNION ALL SELECT user_id FROM habits WHERE DATE(created_at) >= '$week_start'
            UNION ALL SELECT user_id FROM expenses WHERE transaction_date >= '$week_start'
            UNION ALL SELECT user_id FROM study_sessions WHERE DATE(session_date) >= '$week_start'
            UNION ALL SELECT user_id FROM mood_logs WHERE DATE(log_date) >= '$week_start'
        ) as activity
    ";
    $stats['active_week'] = $pdo->query($sql_active_week)->fetchColumn();

    // Chart 1: User Activity per Day (Last 7 Days)
    $activity_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $sql_activity = "
            SELECT COUNT(*) FROM (
                SELECT id FROM tasks WHERE DATE(created_at) = '$date'
                UNION ALL SELECT id FROM habits WHERE DATE(created_at) = '$date'
                UNION ALL SELECT id FROM expenses WHERE transaction_date = '$date'
            ) as daily_acts
        ";
        $count = $pdo->query($sql_activity)->fetchColumn();
        $activity_data['labels'][] = date('D', strtotime($date));
        $activity_data['data'][] = $count;
    }
    $charts['activity'] = $activity_data;

    // Chart 2: Most Used Modules
    $charts['modules'] = [
        'labels' => ['Tasks', 'Habits', 'Expenses', 'Study', 'Mood'],
        'data' => [
            $stats['tasks'],
            $stats['habits'],
            $stats['expenses'],
            $pdo->query("SELECT COUNT(*) FROM study_sessions")->fetchColumn(),
            $pdo->query("SELECT COUNT(*) FROM mood_logs")->fetchColumn()
        ]
    ];

    // Chart 3: Monthly User Growth (Last 6 Months)
    $growth_data = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_label = date('M', strtotime($month_start));
        
        $sql_growth = "SELECT COUNT(*) FROM users WHERE DATE(created_at) <= '$month_end'"; // Cumulative
        $count = $pdo->query($sql_growth)->fetchColumn();
        
        $growth_data['labels'][] = $month_label;
        $growth_data['data'][] = $count;
    }
    $charts['growth'] = $growth_data;

    // Chart 4: Mood Trends (Last 7 Days)
    $mood_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $sql_mood = "SELECT AVG(mood_score) FROM mood_logs WHERE DATE(log_date) = '$date'";
        $avg = $pdo->query($sql_mood)->fetchColumn();
        $mood_data['labels'][] = date('D', strtotime($date));
        $mood_data['data'][] = $avg ? round($avg, 1) : 0;
    }
    $charts['mood'] = $mood_data;

    // --- Users Report Logic (Consolidated) ---
    $search = $_GET['search'] ?? '';
    $params = [];
    $user_query = "
        SELECT 
            u.id, 
            u.username, 
            u.role, 
            u.created_at,
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM users u
        LEFT JOIN tasks t ON u.id = t.user_id
    ";
    if ($search) {
        $user_query .= " WHERE u.username LIKE ?";
        $params[] = "%$search%";
    }
    $user_query .= " GROUP BY u.id, u.username, u.role, u.created_at";
    $user_query .= " ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($user_query);
    $stmt->execute($params);
    $users_report = $stmt->fetchAll();

    // --- Users Report Logic (Consolidated) ---
    $search = $_GET['search'] ?? '';
    $params = [];
    $user_query = "
        SELECT 
            u.id, 
            u.username, 
            u.role, 
            u.created_at,
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM users u
        LEFT JOIN tasks t ON u.id = t.user_id
    ";
    if ($search) {
        $user_query .= " WHERE u.username LIKE ?";
        $params[] = "%$search%";
    }
    $user_query .= " GROUP BY u.id, u.username, u.role, u.created_at";
    $user_query .= " ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($user_query);
    $stmt->execute($params);
    $users_report = $stmt->fetchAll();

} catch (PDOException $e) {
    // Handle error gracefully
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard statistics.";
}

$page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.6); /* Slightly more opaque for better contrast */
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            padding: 24px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.6s ease-out forwards;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); /* Soft shadow */
        }
        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            background: rgba(255, 255, 255, 0.8);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); /* Subtle metallic gradient default */
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .stat-info h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 800; /* Bolder */
            background: linear-gradient(45deg, #2d3748, #4a5568);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-info p {
            margin: 4px 0 0;
            color: #718096;
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .chart-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            padding: 24px;
            border-radius: 20px;
            animation: fadeInUp 0.6s ease-out forwards 0.2s; /* Delayed animation */
            transition: transform 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .chart-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.08);
        }
        .chart-container {
            position: relative;
            height: 250px; /* Slightly taller */
            width: 100%;
        }
        .chart-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chart-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #2d3748;
            font-weight: 700;
        }
        /* Icon Gradients */
        .icon-blue { background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%); color: #fff; }
        .icon-orange { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); color: #fff; }
        .icon-green { background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); color: #fff; } /* actually tealish */
        .icon-pink { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%); color: #fff; }
        .icon-purple { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); color: #fff; }
        .icon-gold { background: linear-gradient(135deg, #fccb90 0%, #d57eeb 100%); color: #fff; }
        .icon-cyan { background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%); color: #fff; }

    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1 style="background: linear-gradient(to right, #222, #555); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Admin Dashboard</h1>
                    <p>System health overview & user statistics.</p>
                </div>
            </header>

            <div class="content-wrapper">
                
                <!-- User Metrics Row -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon icon-blue">👥</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['users']); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-orange">⚡</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['active_today']; ?> <span style="font-size:0.6em; color:var(--text-muted)">Today</span> / <?php echo $stats['active_week']; ?> <span style="font-size:0.6em; color:var(--text-muted)">Week</span></h3>
                            <p>Active Users</p>
                        </div>
                    </div>
                </div>

                <!-- Feature Usage Row -->
                 <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon icon-green">✅</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['tasks_pending']); ?> <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">/ <?php echo number_format($stats['tasks_completed']); ?></span></h3>
                            <p>Tasks (Pending / Completed)</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-pink">🔥</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['habits_active']); ?> <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">/ <?php echo number_format($stats['habits_inactive']); ?></span></h3>
                            <p>Habits (Active / Inactive)</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-gold">💸</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['expenses']); ?></h3>
                            <p>Total Expense Transactions</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-purple">📚</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['study_hours']; ?>h</h3>
                            <p>Total Study Hours</p>
                        </div>
                    </div>
                </div>

                <!-- System Row -->
                <div class="stats-grid">
                     <div class="stat-card">
                        <div class="stat-icon icon-cyan">🤖</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['ai_usage']; ?></h3>
                            <p>AI Queries Made</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="charts-grid">
                    <!-- Activity Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>User Activity (Last 7 Days)</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>

                    <!-- Modules Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Most Used Modules</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="modulesChart"></canvas>
                        </div>
                    </div>

                    <!-- Growth Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Monthly User Growth</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="growthChart"></canvas>
                        </div>
                    </div>

                    <!-- Mood Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Mood Trends (Avg)</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="moodChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- User Report Section -->
                <div class="glass-card" style="margin-bottom: 40px; overflow-x: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>User Reports</h3>
                        <form method="GET" style="display: flex; gap: 10px;">
                            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" 
                                   style="padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.1); background: rgba(255,255,255,0.2);">
                            <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">Search</button>
                        </form>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(0,0,0,0.1);">
                                <th style="text-align: left; padding: 12px; color: var(--text-muted);">User</th>
                                <th style="text-align: left; padding: 12px; color: var(--text-muted);">Role</th>
                                <th style="text-align: left; padding: 12px; color: var(--text-muted);">Progress</th>
                                <th style="text-align: left; padding: 12px; color: var(--text-muted);">Joined</th>
                                <th style="text-align: left; padding: 12px; color: var(--text-muted);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_report as $u): 
                                $total = $u['total_tasks'] ?: 0;
                                $completed = $u['completed_tasks'] ?: 0;
                                $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
                            ?>
                            <tr style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <td style="padding: 12px; font-weight: 500;">
                                    <?php echo htmlspecialchars($u['username']); ?>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="background: <?php echo $u['role'] === 'admin' ? 'rgba(79, 70, 229, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>; 
                                                 color: <?php echo $u['role'] === 'admin' ? '#4F46E5' : '#10B981'; ?>; 
                                                 padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                        <?php echo ucfirst($u['role'] ?? 'user'); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; width: 200px;">
                                    <div style="background: rgba(0,0,0,0.1); border-radius: 10px; height: 8px; width: 100%; overflow: hidden;">
                                        <div style="background: #4facfe; height: 100%; width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;"><?php echo $percent; ?>%</div>
                                </td>
                                <td style="padding: 12px; color: var(--text-muted);"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                <td style="padding: 12px;">
                                    <a href="user_details.php?id=<?php echo $u['id']; ?>" class="btn btn-primary" style="text-decoration: none; padding: 6px 12px; font-size: 0.85rem; border-radius: 8px;">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Common Chart Defaults
        Chart.defaults.color = 'rgba(0, 0, 0, 0.7)';
        Chart.defaults.borderColor = 'rgba(0, 0, 0, 0.1)';
        
        // 1. User Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($charts['activity']['labels']); ?>,
                datasets: [{
                    label: 'Actions Performed',
                    data: <?php echo json_encode($charts['activity']['data']); ?>,
                    borderColor: '#4facfe',
                    backgroundColor: 'rgba(79, 172, 254, 0.2)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // 2. Modules Chart
        const modulesCtx = document.getElementById('modulesChart').getContext('2d');
        new Chart(modulesCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($charts['modules']['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($charts['modules']['data']); ?>,
                    backgroundColor: [
                        '#4facfe', '#00f2fe', '#f093fb', '#f5576c', '#43e97b'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // 3. Growth Chart
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($charts['growth']['labels']); ?>,
                datasets: [{
                    label: 'Total Users',
                    data: <?php echo json_encode($charts['growth']['data']); ?>,
                    backgroundColor: '#43e97b',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // 4. Mood Chart
        const moodCtx = document.getElementById('moodChart').getContext('2d');
        new Chart(moodCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($charts['mood']['labels']); ?>,
                datasets: [{
                    label: 'Avg Mood',
                    data: <?php echo json_encode($charts['mood']['data']); ?>,
                    borderColor: '#f093fb',
                    borderDash: [5, 5],
                    tension: 0.4,
                    pointBackgroundColor: '#f5576c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { 
                        min: 0, 
                        max: 5,
                        grid: { color: 'rgba(255,255,255,0.05)' }
                    } 
                }
            }
        });
    </script>
</body>
</html>
