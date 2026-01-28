<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page = 'goals';
$user_id = $_SESSION['user_id'];

// 1. Fetch User Points & Rank
$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$totalPoints = $stmt->fetchColumn() ?: 0;

// 2. Fetch Goal Stats
$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ?");
$stmt->execute([$user_id]);
$allGoals = $stmt->fetchAll();

$totalGoals = count($allGoals);
$completedGoals = 0;
$activeGoals = 0;
$pointsHistory = [];
$categoryBreakdown = [];

foreach ($allGoals as $g) {
    if ($g['status'] === 'completed') {
        $completedGoals++;
        if ($g['completion_date']) {
            $month = date('M Y', strtotime($g['completion_date']));
            if (!isset($pointsHistory[$month])) $pointsHistory[$month] = 0;
            $pointsHistory[$month] += $g['points'];
        }
    } else {
        $activeGoals++;
    }
    
    // Category Stats
    $cat = $g['category'] ?: 'Uncategorized';
    if (!isset($categoryBreakdown[$cat])) $categoryBreakdown[$cat] = 0;
    $categoryBreakdown[$cat]++;
}

$completionRate = $totalGoals > 0 ? round(($completedGoals / $totalGoals) * 100) : 0;

// Milestone Logic
$milestones = [
    ['label' => 'First Step', 'desc' => 'Complete your first goal', 'achieved' => $completedGoals >= 1, 'icon' => '🌱'],
    ['label' => 'High Five', 'desc' => 'Complete 5 goals', 'achieved' => $completedGoals >= 5, 'icon' => '🖐️'],
    ['label' => 'Achiever', 'desc' => 'Complete 10 goals', 'achieved' => $completedGoals >= 10, 'icon' => '🏆'],
    ['label' => 'Master', 'desc' => 'Earn 1000+ points', 'achieved' => $totalPoints >= 1000, 'icon' => '👑'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Analytics - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 15px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .stat-val { font-size: 2rem; font-weight: 800; color: #333; }
        .stat-label { font-size: 0.8rem; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 1px; }
        
        .milestone-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; }
        .milestone-card { 
            background: white; padding: 20px; border-radius: 15px; text-align: center; border: 2px solid #eee; opacity: 0.5; filter: grayscale(1); transition: all 0.3s;
        }
        .milestone-card.achieved { opacity: 1; filter: grayscale(0); border-color: #ffd700; background: #fffbe6; transform: translateY(-5px); box-shadow: 0 5px 15px rgba(255, 215, 0, 0.2); }
        .milestone-icon { font-size: 2.5rem; margin-bottom: 10px; display: block; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header" style="margin-bottom: 30px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <a href="goals.php" class="btn-secondary" style="text-decoration: none; padding: 8px 15px; border-radius: 10px;">&larr; Back</a>
                    <h1 style="margin: 0;">Goal Analytics 📊</h1>
                </div>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-val" style="color: #6C5DD3;"><?php echo number_format($totalPoints); ?></div>
                    <div class="stat-label">Total Points</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val" style="color: #22c55e;"><?php echo $completionRate; ?>%</div>
                    <div class="stat-label">Completion Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo $completedGoals; ?></div>
                    <div class="stat-label">Goals Crushed</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px;">
                <!-- Points Chart -->
                <div class="glass-card" style="padding: 25px;">
                    <h3 style="margin-bottom: 20px;">Points History</h3>
                    <div style="height: 300px;">
                        <canvas id="pointsChart"></canvas>
                    </div>
                </div>

                <!-- Category Chart -->
                <div class="glass-card" style="padding: 25px;">
                    <h3 style="margin-bottom: 20px;">Focus Areas</h3>
                    <div style="height: 300px;">
                        <canvas id="catChart"></canvas>
                    </div>
                </div>
            </div>

            <h2 style="margin-bottom: 20px;">Trophy Room 🏆</h2>
            <div class="milestone-grid">
                <?php foreach ($milestones as $m): ?>
                    <div class="milestone-card <?php echo $m['achieved'] ? 'achieved' : ''; ?>">
                        <span class="milestone-icon"><?php echo $m['icon']; ?></span>
                        <div style="font-weight: 700; margin-bottom: 5px;"><?php echo $m['label']; ?></div>
                        <div style="font-size: 0.8rem; color: #666;"><?php echo $m['desc']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

        </main>
    </div>

    <script>
        // Points Chart
        new Chart(document.getElementById('pointsChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($pointsHistory)); ?>,
                datasets: [{
                    label: 'Points Earned',
                    data: <?php echo json_encode(array_values($pointsHistory)); ?>,
                    borderColor: '#6C5DD3',
                    backgroundColor: 'rgba(108, 93, 211, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        // Category Chart
        new Chart(document.getElementById('catChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($categoryBreakdown)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($categoryBreakdown)); ?>,
                    backgroundColor: ['#6C5DD3', '#FF9966', '#22c55e', '#3b82f6', '#f43f5e']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
</body>
</html>
