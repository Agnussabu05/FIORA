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
    $stats['tasks'] = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $stats['habits'] = $pdo->query("SELECT COUNT(*) FROM habits")->fetchColumn();
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

} catch (PDOException $e) {
    // Handle error gracefully
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            padding: 24px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
        }
        .stat-info h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
        }
        .stat-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .chart-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            padding: 24px;
            border-radius: 20px;
            /* Remove min-height to allow more compact layout */
        }
        .chart-container {
            position: relative;
            height: 220px; /* Reduced from default expansion */
            width: 100%;
        }
        .chart-header {
            margin-bottom: 20px;
        }
        .chart-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text-color);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>Admin Dashboard</h1>
                    <p>System health overview & user statistics.</p>
                </div>
            </header>

            <div class="content-wrapper">
                
                <!-- Stats Row 1 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['users']); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⚡</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['active_today']; ?> / <?php echo $stats['active_week']; ?></h3>
                            <p>Active (Today / Week)</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['tasks']); ?></h3>
                            <p>Tasks Created</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🔥</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['habits']); ?></h3>
                            <p>Habits Tracked</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Row 2 -->
                 <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">💸</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['expenses']); ?></h3>
                            <p>Expenses Logged</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['study_hours']; ?>h</h3>
                            <p>Study Hours Logged</p>
                        </div>
                    </div>
                     <div class="stat-card">
                        <div class="stat-icon">🤖</div>
                        <div class="stat-info">
                            <h3><?php echo $stats['ai_usage']; ?></h3>
                            <p>AI Assistant Usage</p>
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

            </div>
        </main>
    </div>

    <script>
        // Common Chart Defaults
        Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
        Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
        
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
