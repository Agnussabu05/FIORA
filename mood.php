<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page = 'mood';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle Mood Log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_mood') {
    $score = (int)$_POST['mood_score'];
    $label = $_POST['mood_label'];
    $note = $_POST['note'];
    $date = date('Y-m-d');

    // Insert or Update today's mood
    $stmt = $pdo->prepare("INSERT INTO mood_logs (user_id, mood_score, mood_label, note, log_date) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE mood_score = VALUES(mood_score), mood_label = VALUES(mood_label), note = VALUES(note)");
    $stmt->execute([$user_id, $score, $label, $note, $date]);
    header("Location: mood.php");
    exit;
}

// Fetch Mood Trends (Last 7 Days)
$stmt = $pdo->prepare("SELECT log_date, mood_score FROM mood_logs WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) ORDER BY log_date ASC");
$stmt->execute([$user_id]);
$trendData = $stmt->fetchAll();

$labels = [];
$scores = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('D', strtotime($d));
    $found = false;
    foreach ($trendData as $row) {
        if ($row['log_date'] == $d) {
            $scores[] = (int)$row['mood_score'];
            $found = true;
            break;
        }
    }
    if (!$found) $scores[] = 0;
}

// Fetch Recent Logs
$stmt = $pdo->prepare("SELECT * FROM mood_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 5");
$stmt->execute([$user_id]);
$recentLogs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Tracker - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .mood-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
        }
        .mood-selector {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        .mood-btn {
            background: rgba(255,255,255,0.4);
            border: 2px solid var(--glass-border);
            border-radius: 15px;
            padding: 15px 5px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            font-size: 1.5rem;
        }
        .mood-btn:hover { transform: translateY(-3px); background: rgba(255,255,255,0.6); }
        .mood-btn.active { border-color: var(--primary); background: rgba(0,0,0,0.05); }
        .mood-btn span { display: block; font-size: 0.7rem; font-weight: 700; margin-top: 5px; text-transform: uppercase; color: #666; }

        .mood-chart-container {
            height: 300px;
            margin-top: 20px;
        }
        .log-item {
            background: rgba(255,255,255,0.4);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .mood-emoji-small {
            width: 45px; height: 45px;
            background: rgba(255,255,255,0.6);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Mood Tracker 🌈</h1>
                    <p style="color: #444; font-weight: 500;">Check in with yourself, <?php echo htmlspecialchars($username); ?>.</p>
                </div>
            </header>

            <div class="mood-grid">
                <div>
                    <!-- Logging Card -->
                    <div class="glass-card" style="padding: 30px;">
                        <h3 style="margin-bottom: 10px; color: #000 !important;">How are you feeling?</h3>
                        <p style="color: #222; font-size: 0.95rem; font-weight: 600;">Select the emoji that best matches your vibe.</p>
                        
                        <form action="mood.php" method="POST" id="moodForm">
                            <input type="hidden" name="action" value="log_mood">
                            <input type="hidden" name="mood_score" id="selectedScore" value="3">
                            <input type="hidden" name="mood_label" id="selectedLabel" value="Neutral">
                            
                            <div class="mood-selector">
                                <div class="mood-btn" onclick="selectMood(1, 'Stressed', this)">😫<span style="color: #222 !important;">Stress</span></div>
                                <div class="mood-btn" onclick="selectMood(2, 'Sad', this)">😔<span style="color: #222 !important;">Low</span></div>
                                <div class="mood-btn" onclick="selectMood(3, 'Neutral', this)" class="active">😐<span style="color: #222 !important;">Okay</span></div>
                                <div class="mood-btn" onclick="selectMood(4, 'Good', this)">😊<span style="color: #222 !important;">Good</span></div>
                                <div class="mood-btn" onclick="selectMood(5, 'Incredible', this)">🚀<span style="color: #222 !important;">Great</span></div>
                            </div>
                            
                            <div class="form-group" style="margin-top: 20px;">
                                <label style="font-weight: 700; color: #111; margin-bottom: 8px; display: block;">What's on your mind? (Optional)</label>
                                <textarea name="note" class="form-input" rows="3" placeholder="I had a productive day..." style="color: #000; font-weight: 500;"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; margin-top: 10px; font-weight: 800;">Log Today's Mood</button>
                        </form>
                    </div>

                    <!-- History -->
                    <div style="margin-top: 30px;">
                        <h3 style="margin-bottom: 20px; color: #000 !important;">Recent Check-ins</h3>
                        <?php if (empty($recentLogs)): ?>
                            <div style="text-align: center; padding: 40px; background: rgba(255,255,255,0.4); border-radius: 15px; border: 1px solid var(--glass-border);">
                                <div style="font-size: 2.5rem; margin-bottom: 15px;">🌈</div>
                                <div style="font-size: 1rem; font-weight: 500; color: #000; margin-bottom: 8px;">No mood entries yet</div>
                                <div style="font-size: 0.9rem; color: #222; font-weight: 500;">Start tracking your emotional journey today and discover patterns in your well-being.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): 
                                $emoji = ['😫','😔','😐','😊','🚀'][$log['mood_score']-1];
                            ?>
                                <div class="log-item">
                                    <div class="mood-emoji-small"><?php echo $emoji; ?></div>
                                    <div>
                                        <div style="font-weight: 800; color: #000;"><?php echo $log['mood_label']; ?></div>
                                        <div style="font-size: 0.85rem; color: #222; font-weight: 600;"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></div>
                                        <?php if ($log['note']): ?>
                                            <div style="font-size: 0.9rem; color: #111; font-style: italic; margin-top: 5px; font-weight: 500;">"<?php echo htmlspecialchars($log['note']); ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Trends -->
                <div class="glass-card" style="padding: 30px;">
                    <h3 style="margin-bottom: 10px; color: #000 !important;">Emotional Trends</h3>
                    <p style="color: #222; font-size: 0.95rem; font-weight: 600;">Last 7 days of your mindset cycle.</p>
                    
                    <div class="mood-chart-container">
                        <canvas id="moodChart"></canvas>
                    </div>
                    
                    <div style="margin-top: 40px; border-top: 2px solid var(--glass-border); padding-top: 30px;">
                        <h4 style="color: #000 !important; font-weight: 800;">Today's Reflection</h4>
                        <div style="background: rgba(255,255,255,0.4); padding: 20px; border-radius: 12px; margin-top: 15px; line-height: 1.6; color: #000; font-weight: 600; border: 1px solid var(--glass-border);">
                            Recognizing patterns in your mood is the first step toward better mental clarity. Keep tracking to see your growth!
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function selectMood(score, label, el) {
            document.querySelectorAll('.mood-btn').forEach(btn => btn.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('selectedScore').value = score;
            document.getElementById('selectedLabel').value = label;
        }

        // Initialize Chart
        const ctx = document.getElementById('moodChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Vibe Level',
                    data: <?php echo json_encode($scores); ?>,
                    borderColor: '#222222',
                    backgroundColor: 'rgba(34, 34, 34, 0.05)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#222222',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        grid: { display: false },
                        ticks: { stepSize: 1, color: '#666' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#666' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</body>
</html>
