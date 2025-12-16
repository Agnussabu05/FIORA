<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Good Morning, <?php echo htmlspecialchars($username); ?> ☀️</h1>
                    <p style="color: var(--text-muted);">Let's make today productive.</p>
                </div>
                <!-- Simple helper action -->
                <button class="btn btn-primary" onclick="alert('Quick add feature coming soon!')">+ Quick Add</button>
            </header>

            <!-- AI Insight Widget -->
            <section class="glass-card" style="margin-bottom: var(--spacing-md); border-left: 4px solid var(--secondary);">
                <h3 style="margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                    🤖 Fiora Assistant Insight
                </h3>
                <p id="ai-message">Analyzing your day... You have 0 pending tasks and your mood seems stable. Consider setting a goal for today!</p>
            </section>

            <div class="dashboard-grid">
                <!-- Task Summary -->
                <div class="glass-card">
                    <h3>Tasks Overview</h3>
                    <div style="margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Progress</span>
                            <span>0%</span>
                        </div>
                        <div style="width: 100%; background: rgba(255,255,255,0.1); border-radius: 10px; height: 8px;">
                            <div style="width: 0%; background: var(--primary); height: 100%; border-radius: 10px;"></div>
                        </div>
                        <p style="margin-top: 10px; font-size: 0.9rem; color: var(--text-muted);">0 tasks remaining</p>
                    </div>
                </div>

                <!-- Habits -->
                <div class="glass-card">
                    <h3>Today's Habits</h3>
                    <div style="margin-top: 15px; text-align: center; color: var(--text-muted);">
                        No habits tracked yet.
                    </div>
                </div>

                <!-- Finance -->
                <div class="glass-card">
                    <h3>Monthly Spend</h3>
                    <canvas id="financeChart" style="max-height: 150px;"></canvas>
                </div>
            </div>

            <!-- Recent Activity / Mood Graph Row -->
            <div class="dashboard-grid" style="margin-top: var(--spacing-md); grid-template-columns: 2fr 1fr;">
                <div class="glass-card">
                    <h3>Mood Analytics (Weekly)</h3>
                    <canvas id="moodChart" style="width: 100%; height: 200px;"></canvas>
                </div>
                <div class="glass-card">
                    <h3>Focus Timer</h3>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 3rem; font-weight: bold; margin-bottom: 10px;">25:00</div>
                        <button class="btn btn-primary">Start Focus</button>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Placeholder Charts
        const moodCtx = document.getElementById('moodChart').getContext('2d');
        new Chart(moodCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Mood Score',
                    data: [3, 4, 3, 5, 4, 2, 4],
                    borderColor: '#FF6584',
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(255, 101, 132, 0.1)'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: 5 }, x: { grid: { display: false } } }
            }
        });

        const financeCtx = document.getElementById('financeChart').getContext('2d');
        new Chart(financeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Food', 'Transport', 'Bills'],
                datasets: [{
                    data: [300, 50, 100],
                    backgroundColor: ['#6C63FF', '#FF6584', '#00b894'],
                    borderWidth: 0
                }]
            },
            options: { cutout: '70%', plugins: { legend: { position: 'right' } } }
        });
    </script>
</body>
</html>
