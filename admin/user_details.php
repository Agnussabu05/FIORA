<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId === 0) {
    header("Location: users.php");
    exit;
}

// 1. Fetch User Info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

// 2. Fetch Tasks
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$tasks = $stmt->fetchAll();

$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
$taskProgress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

// 3. Fetch Habits
$stmt = $pdo->prepare("SELECT * FROM habits WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$habits = $stmt->fetchAll();

$totalHabits = count($habits);
$activeHabitsCount = $pdo->query("SELECT COUNT(DISTINCT habit_id) FROM habit_logs WHERE habit_id IN (SELECT id FROM habits WHERE user_id = $userId) AND check_in_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$inactiveHabitsCount = $totalHabits - $activeHabitsCount;

// 4. Fetch Expenses Stats (Income vs Expense)
$stmt = $pdo->prepare("SELECT type, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY type");
$stmt->execute([$userId]);
$financeData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalIncome = $financeData['income'] ?? 0;
$totalExpense = $financeData['expense'] ?? 0;

// 5. Fetch Study Stats (By Subject)
$stmt = $pdo->prepare("SELECT subject, SUM(duration_minutes) as total_minutes FROM study_sessions WHERE user_id = ? GROUP BY subject");
$stmt->execute([$userId]);
$studyBySubject = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalStudyHours = array_sum($studyBySubject) / 60; // Recalculate total from this if needed, or keep existing

// 6. Fetch Books Stats (By Status)
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM books WHERE user_id = ? GROUP BY status");
$stmt->execute([$userId]);
$bookStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$booksRead = $bookStats['completed'] ?? 0;
$booksReading = $bookStats['reading'] ?? 0;
$booksToRead = $bookStats['to-read'] ?? 0; // Assuming 'to-read' is the status value

// 7. Fetch Mood Logs
$stmt = $pdo->prepare("SELECT AVG(mood_score) as avg_mood, COUNT(*) as total_logs FROM mood_logs WHERE user_id = ?");
$stmt->execute([$userId]);
$moodStats = $stmt->fetch();
$avgMood = $moodStats['avg_mood'] ? round($moodStats['avg_mood'], 1) : 0;

$stmt = $pdo->prepare("SELECT * FROM mood_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 7"); // Increased to 7 for better trend
$stmt->execute([$userId]);
$moodLogs = $stmt->fetchAll();

// 8. Fetch Goals
$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ?");
$stmt->execute([$userId]);
$goals = $stmt->fetchAll();

$page = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Variables - Consistent with Admin Dashboard */
        :root {
            --glass-card-bg: rgba(255, 255, 255, 0.85);
            --glass-border: 1px solid rgba(255, 255, 255, 0.9);
            --glass-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
            --text-heading: #1e293b;
            --text-body: #64748b;
        }

        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
        }

        .profile-header {
            background: var(--glass-card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: var(--glass-border);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
            animation: fadeInUp 0.6s ease-out forwards;
            box-shadow: var(--glass-shadow);
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 30px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .profile-info h2 { 
            margin: 0 0 5px 0; 
            font-size: 2.2rem; 
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -1px;
        }
        .profile-info p { 
            margin: 0; 
            color: var(--text-body); 
            font-size: 1rem;
            font-weight: 500;
        }
        
        .section-title { 
            font-size: 1.25rem; 
            font-weight: 800; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            color: var(--text-heading);
        }
        .section-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 30px; }
        
        .list-card {
            background: var(--glass-card-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            padding: 30px;
            border-radius: 24px;
            max-height: 400px;
            overflow-y: auto;
            animation: fadeInUp 0.6s ease-out forwards 0.2s;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: var(--glass-shadow);
            display: flex; 
            flex-direction: column; 
            height: 380px;
        }
        .list-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            border-color: white;
        }
        
        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .mini-stat {
            background: rgba(255,255,255,0.7);
            padding: 24px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
            animation: fadeInUp 0.5s ease-out forwards;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .mini-stat:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.95);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .mini-stat h4 { 
            margin: 0; 
            font-size: 2.2rem; 
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -1px;
            line-height: 1.1;
        }
        .mini-stat span { 
            font-size: 0.8rem; 
            color: var(--text-body); 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-top: 5px;
            display: block;
        }

        /* Specific text gradients */
        .text-blue { background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .text-orange { background: linear-gradient(135deg, #fb923c 0%, #f97316 100%) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .text-green { background: linear-gradient(135deg, #34d399 0%, #10b981 100%) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .text-pink { background: linear-gradient(135deg, #f472b6 0%, #ec4899 100%) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .text-purple { background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        
        @media (max-width: 900px) {
            .section-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-section">
                    <h1>User Details</h1>
                    <p>Viewing profile and activity for <?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                <div>
                     <a href="index.php" class="btn btn-primary" style="text-decoration: none; padding: 10px 20px;">Back to Dashboard</a>
                </div>
            </header>

            <div class="content-wrapper">
                
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                        <p>Joined: <?php echo date('F j, Y', strtotime($user['created_at'])); ?> • Role: <?php echo ucfirst($user['role'] ?? 'user'); ?></p>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="grid-stats">
                    <div class="mini-stat">
                        <h4 class="text-blue"><?php echo $totalTasks; ?></h4>
                        <span>Total Tasks</span>
                    </div>
                    <div class="mini-stat">
                        <h4 class="text-orange"><?php echo $totalTasks - $completedTasks; ?></h4>
                        <span>Pending Tasks</span>
                    </div>
                    <div class="mini-stat">
                        <h4 class="text-green"><?php echo $completedTasks; ?></h4>
                        <span>Completed Tasks</span>
                    </div>
                    <div class="mini-stat">
                        <h4 class="text-blue"><?php echo $taskProgress; ?>%</h4>
                        <span>Completion Rate</span>
                        <div style="background: rgba(0,0,0,0.05); border-radius: 10px; height: 6px; width: 100%; margin-top: 8px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #43e97b, #38f9d7); height: 100%; width: <?php echo $taskProgress; ?>%;"></div>
                        </div>
                    </div>
                    <div class="mini-stat">
                         <h4 class="text-pink"><?php echo $activeHabitsCount; ?></h4>
                        <span>Active Habits</span>
                    </div>
                    <div class="mini-stat">
                         <h4 style="color: #cbd5e1; -webkit-text-fill-color: #cbd5e1;"><?php echo $inactiveHabitsCount; ?></h4>
                        <span>Inactive Habits</span>
                    </div>
                    <div class="mini-stat">
                         <h4 class="text-purple"><?php echo $totalStudyHours; ?>h</h4>
                        <span>Study Time</span>
                    </div>
                    <div class="mini-stat">
                         <h4 class="text-orange"><?php echo $avgMood; ?></h4>
                        <span>Avg Mood</span>
                    </div>
                </div>

                <!-- Module-Specific Reports -->
                <div class="section-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); padding-bottom: 50px;">
                    
                    <!-- 1. Task Report -->
                    <div class="list-card" style="display: flex; flex-direction: column; height: 350px;">
                        <div class="section-title">✅ Task Activity</div>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="taskChart"></canvas>
                        </div>
                    </div>

                    <!-- 2. Habit Report -->
                    <div class="list-card" style="display: flex; flex-direction: column; height: 350px;">
                        <div class="section-title">🔥 Habit Tracking</div>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="habitChart"></canvas>
                        </div>
                    </div>

                    <!-- 3. Finance Report -->
                    <div class="list-card" style="display: flex; flex-direction: column; height: 350px;">
                        <div class="section-title">💰 Financial Overview</div>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="financeChart"></canvas>
                        </div>
                    </div>

                    <!-- 4. Reading Report -->
                    <div class="list-card" style="display: flex; flex-direction: column; height: 350px;">
                        <div class="section-title">📖 Reading Progress</div>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="readingChart"></canvas>
                        </div>
                    </div>

                    <!-- 5. Study Report -->
                    <div class="list-card" style="display: flex; flex-direction: column; height: 350px;">
                        <div class="section-title">📚 Study Focus</div>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="studyChart"></canvas>
                        </div>
                    </div>

                    <!-- 6. Mood Report -->
                    <div class="list-card" style="display: flex; flex-direction: column; height: 350px;">
                        <div class="section-title">🙂 Mood Trend</div>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="moodChart"></canvas>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Chart Config -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.color = 'rgba(0, 0, 0, 0.7)';
        Chart.defaults.borderColor = 'rgba(0, 0, 0, 0.1)';

        // 1. Tasks (Pie)
        new Chart(document.getElementById('taskChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending'],
                datasets: [{
                    data: [<?php echo $completedTasks; ?>, <?php echo $totalTasks - $completedTasks; ?>],
                    backgroundColor: ['#10B981', '#F59E0B'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // 2. Habits (Pie)
        new Chart(document.getElementById('habitChart'), {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Inactive'],
                datasets: [{
                    data: [<?php echo $activeHabitsCount; ?>, <?php echo $inactiveHabitsCount; ?>],
                    backgroundColor: ['#EC4899', '#cbd5e1'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // 3. Finance (Bar)
        new Chart(document.getElementById('financeChart'), {
            type: 'bar',
            data: {
                labels: ['Income', 'Expense'],
                datasets: [{
                    label: 'Amount (₹)',
                    data: [<?php echo $totalIncome; ?>, <?php echo $totalExpense; ?>],
                    backgroundColor: ['#10B981', '#EF4444'],
                    borderRadius: 6
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });

        // 4. Reading (Doughnut)
        new Chart(document.getElementById('readingChart'), {
            type: 'pie', // Pie chart for variety
            data: {
                labels: ['Read', 'Reading', 'To Read'],
                datasets: [{
                    data: [<?php echo $booksRead; ?>, <?php echo $booksReading; ?>, <?php echo $booksToRead; ?>],
                    backgroundColor: ['#10B981', '#4F46E5', '#6366F1'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // 5. Study (Bar - Horizontal?)
        new Chart(document.getElementById('studyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($studyBySubject)); ?>,
                datasets: [{
                    label: 'Minutes',
                    data: <?php echo json_encode(array_values($studyBySubject)); ?>,
                    backgroundColor: '#6366F1',
                    borderRadius: 6
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                indexAxis: 'y', // Horizontal bar for subjects
                plugins: { legend: { display: false } } 
            }
        });

        // 6. Mood (Line)
        new Chart(document.getElementById('moodChart'), {
            type: 'line',
            data: {
                labels: [<?php foreach ($moodLogs as $log) echo "'" . date('M j', strtotime($log['log_date'])) . "',"; ?>],
                datasets: [{
                    label: 'Score',
                    data: [<?php foreach ($moodLogs as $log) echo $log['mood_score'] . ","; ?>],
                    borderColor: '#f093fb',
                    backgroundColor: 'rgba(240, 147, 251, 0.2)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                scales: { y: { min: 0, max: 5, ticks: { stepSize: 1 } } } 
            }
        });
    </script>

            </div>
        </main>
    </div>
</body>
</html>
