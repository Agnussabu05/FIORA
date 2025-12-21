<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$page = 'mood';
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Tracker - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Mood Tracker 😊</h1>
                    <p style="color: var(--text-muted);">How are you feeling today, <?php echo htmlspecialchars($username); ?>?</p>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="dashboard-grid" style="grid-template-columns: 1fr 2fr;">
                    <div class="glass-card">
                        <h3>Log Today's Mood</h3>
                        <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
                            <button class="btn btn-secondary" style="background: #FFF9C4; color: #FBC02D;">☀️ Incredible</button>
                            <button class="btn btn-secondary" style="background: #E8F5E9; color: #43A047;">🌿 Peaceful</button>
                            <button class="btn btn-secondary" style="background: #F3E5F5; color: #8E24AA;">☂️ Gloomy</button>
                            <button class="btn btn-secondary" style="background: #FFEBEE; color: #E53935;">🔥 Stressed</button>
                        </div>
                    </div>
                    <div class="glass-card">
                        <h3>Emotional Trends</h3>
                        <div style="position: relative; height: 250px; width: 100%; margin-top: 15px;">
                            <canvas id="moodDetailChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        const ctx = document.getElementById('moodDetailChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Mood Level',
                    data: [3, 4, 3, 5, 4, 3, 4],
                    borderColor: '#1a1a1a',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(0,0,0,0.03)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
