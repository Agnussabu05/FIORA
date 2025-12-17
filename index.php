<?php
require_once 'includes/db.php';
require_once 'includes/auth.php'; // Enforce login

// If DB is down, $pdo is null.
// Auth.php handles redirection to login.php if no session.
// If valid session but no DB, we stay here.

$username = $_SESSION['username'] ?? 'User';
$page = 'dashboard';
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
    </style>
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
        // Placeholder Charts - Modern Clay Theme
        const moodCtx = document.getElementById('moodChart').getContext('2d');
        new Chart(moodCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Mood Score',
                    data: [3, 4, 3, 5, 4, 2, 4],
                    borderColor: '#9E9080', // Deep Taupe
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(158, 144, 128, 0.2)'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        max: 5,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { color: '#555555' }
                    }, 
                    x: { 
                        grid: { display: false },
                        ticks: { color: '#555555' }
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
                    data: [300, 50, 100],
                    backgroundColor: ['#222222', '#9E9080', '#6B8C73'], // Black, Taupe, Sage
                    borderWidth: 0
                }]
            },
            options: { 
                cutout: '70%', 
                plugins: { 
                    legend: { 
                        position: 'right',
                        labels: { color: '#2D2D2D' }
                    } 
                } 
            }
        });
    </script>
</body>
</html>
