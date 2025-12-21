<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch AI Insights (Mocked)
$insights = [
    ['id' => 1, 'type' => 'Activity', 'content' => 'User activity peaked on Wednesday.', 'accuracy' => '94%'],
    ['id' => 2, 'type' => 'Productivity', 'content' => 'Users who track habits are 30% more likely to complete tasks.', 'accuracy' => '89%'],
    ['id' => 3, 'type' => 'Mood', 'content' => 'A slight decline in mood was observed during weekends.', 'accuracy' => '91%']
];

$page = 'ai';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Management - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .ai-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        .insight-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .insight-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .insight-badge {
            background: var(--accent);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>AI Assistant Management</h1>
                    <p>Monitor system insights and AI performance.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="ai-grid">
                    <div class="glass-card">
                        <h3>Recent System Insights</h3>
                        <ul class="insight-list" style="margin-top: 20px;">
                            <?php foreach ($insights as $insight): ?>
                            <li class="insight-item">
                                <div>
                                    <span class="insight-badge"><?php echo $insight['type']; ?></span>
                                    <p style="margin: 8px 0 0 0; font-size: 0.95rem;"><?php echo $insight['content']; ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">Accuracy</div>
                                    <div style="font-weight: 700; color: #10B981;"><?php echo $insight['accuracy']; ?></div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="glass-card">
                        <h3>AI Performance</h3>
                        <div style="padding: 20px; text-align: center;">
                            <canvas id="accuracyChart"></canvas>
                            <p style="margin-top: 15px; font-weight: 600;">Avg Accuracy: 91.3%</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        const ctx = document.getElementById('accuracyChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Accurate', 'Ineffective'],
                datasets: [{
                    data: [91, 9],
                    backgroundColor: ['#10B981', 'rgba(255,255,255,0.1)'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '80%',
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
