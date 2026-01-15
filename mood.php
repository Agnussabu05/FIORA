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

    $sleep = isset($_POST['sleep_hours']) ? (float)$_POST['sleep_hours'] : 0;
    $activities = isset($_POST['activities']) ? json_encode($_POST['activities']) : null;

    // Insert or Update today's mood
    $stmt = $pdo->prepare("INSERT INTO mood_logs (user_id, mood_score, mood_label, note, log_date, sleep_hours, activities) 
                            VALUES (?, ?, ?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                                mood_score = VALUES(mood_score), 
                                mood_label = VALUES(mood_label), 
                                note = VALUES(note),
                                sleep_hours = VALUES(sleep_hours),
                                activities = VALUES(activities)");
    $stmt->execute([$user_id, $score, $label, $note, $date, $sleep, $activities]);
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
                            
                            <!-- New Detailed Inputs -->
                            <div style="margin-top: 20px;">
                                <label style="font-weight: 700; color: #111; margin-bottom: 8px; display: block;">Sleep Last Night: <span id="sleepVal" style="color: #6366f1;">7h</span></label>
                                <input type="range" name="sleep_hours" min="0" max="12" value="7" step="0.5" style="width: 100%; accent-color: #6366f1;" oninput="document.getElementById('sleepVal').textContent = this.value + 'h'">
                            </div>

                            <div style="margin-top: 20px;">
                                <label style="font-weight: 700; color: #111; margin-bottom: 8px; display: block;">What impacted your mood?</label>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Work"><span>💼 Work</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Study"><span>📚 Study</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Social"><span>👥 Social</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Exercise"><span>💪 Exercise</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Sleep"><span>😴 Sleep</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Health"><span>⚕️ Health</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Hobbies"><span>🎨 Hobby</span></label>
                                </div>
                                <style>
                                    .tag-checkbox input { display: none; }
                                    .tag-checkbox span {
                                        display: inline-block; padding: 8px 12px; border-radius: 20px;
                                        background: rgba(255,255,255,0.5); border: 1px solid #ccc;
                                        cursor: pointer; font-size: 0.8rem; font-weight: 600; color: #444;
                                        transition: all 0.2s;
                                    }
                                    .tag-checkbox input:checked + span {
                                        background: #6366f1; color: white; border-color: #6366f1;
                                    }
                                </style>
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
                        <h4 style="color: #000 !important; font-weight: 800; margin-bottom: 20px;">Monthly Report 📅</h4>
                        
                        <?php
                        $month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
                        $year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
                        
                        // Navigation Links
                        $prevM = $month - 1; $prevY = $year;
                        if ($prevM < 1) { $prevM = 12; $prevY--; }
                        $nextM = $month + 1; $nextY = $year;
                        if ($nextM > 12) { $nextM = 1; $nextY++; }
                        
                        // Fetch logs for this month
                        $stmt = $pdo->prepare("SELECT log_date, mood_score, mood_label FROM mood_logs WHERE user_id = ? AND MONTH(log_date) = ? AND YEAR(log_date) = ?");
                        $stmt->execute([$user_id, $month, $year]);
                        $monthLogs = [];
                        foreach ($stmt->fetchAll() as $row) {
                            $monthLogs[$row['log_date']] = $row;
                        }
                        
                        // Calendar Vars
                        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                        $firstDayOfWeek = date('w', strtotime("$year-$month-01")); // 0 (Sun) - 6 (Sat)
                        ?>

                        <div class="calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <a href="?m=<?php echo $prevM; ?>&y=<?php echo $prevY; ?>" class="btn-sm" style="background:#ddd; padding:5px 10px; border-radius:8px; text-decoration:none;">←</a>
                            <span style="font-weight: 700; color: #333;"><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></span>
                            <a href="?m=<?php echo $nextM; ?>&y=<?php echo $nextY; ?>" class="btn-sm" style="background:#ddd; padding:5px 10px; border-radius:8px; text-decoration:none;">→</a>
                        </div>

                        <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center;">
                            <!-- Weekdays -->
                            <div class="cal-head">Sun</div><div class="cal-head">Mon</div><div class="cal-head">Tue</div>
                            <div class="cal-head">Wed</div><div class="cal-head">Thu</div><div class="cal-head">Fri</div><div class="cal-head">Sat</div>
                            
                            <?php
                            // Empty cells for days before the 1st
                            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                                echo "<div></div>";
                            }
                            
                            // Days
                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                $hasLog = isset($monthLogs[$dateStr]);
                                
                                $bg = 'rgba(255,255,255,0.3)';
                                $emoji = '';
                                if ($hasLog) {
                                    $score = $monthLogs[$dateStr]['mood_score'];
                                    $colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e', '#a855f7'];
                                    $emojis = ['', '😫', '😔', '😐', '😊', '🚀'];
                                    $bg = $colors[$score] . '40'; // low opacity
                                    $emoji = $emojis[$score];
                                }
                                
                                echo "<div style='background: $bg; border-radius: 8px; padding: 10px 2px; font-size: 0.8rem; color: #444; min-height: 50px;'>
                                        <div style='font-weight:700;'>$day</div>
                                        <div style='font-size:1rem; margin-top:2px;'>$emoji</div>
                                      </div>";
                            }
                            ?>
                        </div>
                        <style>
                            .cal-head { font-size: 0.7rem; color: #888; font-weight: 600; padding-bottom: 5px; }
                        </style>
                    </div>
                </div>

                <!-- Mood Companion Chat -->
                <div class="glass-card" style="padding: 30px; margin-top: 30px; border-top: 4px solid #8b5cf6;">
                    <h3 style="margin-bottom: 10px; color: #000 !important; display: flex; align-items: center; gap: 10px;">
                        <span>🟣</span> Mood Companion
                    </h3>
                    <p style="color: #444; font-size: 0.9rem;">Vent, reflect, or just chat. Based on your current vibe.</p>
                    
                    <div id="chat-box" style="height: 300px; overflow-y: auto; background: rgba(255,255,255,0.5); border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 15px; margin-top: 15px; display: flex; flex-direction: column; gap: 10px;">
                        <!-- Messages will appear here -->
                        <div style="align-self: flex-start; background: white; padding: 10px 15px; border-radius: 15px 15px 15px 0; max-width: 80%; box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 0.9rem; color: #333;">
                            Hi <?php echo htmlspecialchars($username); ?>! I'm here to listen. How are you feeling right now?
                        </div>
                    </div>

                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <input type="text" id="chat-input" placeholder="Type a message..." style="flex: 1; padding: 12px; border-radius: 12px; border: 1px solid #ccc; outline: none;">
                        <button onclick="sendMessage()" id="chat-send-btn" style="background: #8b5cf6; color: white; border: none; padding: 0 20px; border-radius: 12px; font-weight: 700; cursor: pointer;">→</button>
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
    <script>
        // Chatbot Logic
        const chatBox = document.getElementById('chat-box');
        const chatInput = document.getElementById('chat-input');
        const chatBtn = document.getElementById('chat-send-btn');
        
        // Get latest mood context from PHP
        const currentMoodLabel = "<?php echo !empty($recentLogs) ? $recentLogs[0]['mood_label'] : 'Neutral'; ?>";
        
        chatInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') sendMessage();
        });

        function sendMessage() {
            const text = chatInput.value.trim();
            if (!text) return;

            // Add User Message
            addMessage(text, 'user');
            chatInput.value = '';
            chatInput.disabled = true;
            
            // Show Typing Indicator
            const typingId = addMessage('Thinking...', 'bot', true);

            // Send to Backend
            fetch('api/chat_mood.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message: text,
                    mood_context: currentMoodLabel
                })
            })
            .then(res => res.json())
            .then(data => {
                // Remove Typing Indicator
                const typingEl = document.getElementById(typingId);
                if(typingEl) typingEl.remove();

                if (data.success) {
                    addMessage(data.reply, 'bot');
                } else {
                    addMessage("Error: " + (data.error || "Unknown error"), 'bot');
                }
                chatInput.disabled = false;
                chatInput.focus();
            })
            .catch(err => {
                console.error(err);
                chatInput.disabled = false;
            });
        }

        function addMessage(text, sender, isTyping = false) {
            const div = document.createElement('div');
            div.style.maxWidth = '80%';
            div.style.padding = '10px 15px';
            div.style.fontSize = '0.9rem';
            div.style.marginBottom = '5px';
            div.style.lineHeight = '1.5';
            
            if (sender === 'user') {
                div.style.alignSelf = 'flex-end';
                div.style.background = '#8b5cf6';
                div.style.color = 'white';
                div.style.borderRadius = '15px 15px 0 15px';
            } else {
                div.style.alignSelf = 'flex-start';
                div.style.background = 'white';
                div.style.color = '#333';
                div.style.borderRadius = '15px 15px 15px 0';
                div.style.boxShadow = '0 2px 5px rgba(0,0,0,0.05)';
            }

            if (isTyping) {
                div.id = 'typing-' + Date.now();
                div.style.fontStyle = 'italic';
                div.style.color = '#888';
            }

            div.textContent = text;
            chatBox.appendChild(div);
            chatBox.scrollTop = chatBox.scrollHeight;
            
            return div.id;
        }
    </script>
</body>
</html>
