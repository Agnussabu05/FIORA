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
    header("Location: mood.php");
    exit;
}

// Handle Gratitude Log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_gratitude') {
    $entry_text = $_POST['gratitude_entry'];
    if (!empty($entry_text)) {
        $stmt = $pdo->prepare("INSERT INTO gratitude_entries (user_id, entry_text) VALUES (?, ?)");
        $stmt->execute([$user_id, $entry_text]);
    }
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

// --- INSIGHTS ENGINE ---
// 1. Burnout Detection: Low Avg Mood + High Tasks (Last 3 Days)
$burnout_alert = false;
$stmt = $pdo->prepare("SELECT AVG(mood_score) as avg_mood, COUNT(*) as log_count FROM mood_logs WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)");
$stmt->execute([$user_id]);
$mood_stats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'completed' AND deadline >= DATE_SUB(NOW(), INTERVAL 3 DAY)"); // Using deadline as proxy for recent activity
$stmt->execute([$user_id]);
$recent_tasks = $stmt->fetchColumn();

if ($mood_stats['log_count'] > 0 && $mood_stats['avg_mood'] < 3 && $recent_tasks > 5) {
    $burnout_alert = true;
}

// 2. Mood Triggers: Spending vs Mood
// Check for days with High Spend (>500) AND Low Mood (<3)
$trigger_insight = null;
$stmt = $pdo->prepare("
    SELECT m.log_date 
    FROM mood_logs m
    JOIN expenses e ON m.log_date = e.transaction_date -- Assuming distinct date column in expenses
    WHERE m.user_id = ? 
    AND m.mood_score < 3 
    AND e.type = 'expense'
    GROUP BY m.log_date
    HAVING SUM(e.amount) > 500
    LIMIT 1
");
// Note: Expenses table might need adjustments, simplified for now
try {
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) {
        $trigger_insight = "We noticed a pattern: You tend to report lower mood on days with high spending. Financial stress might be a trigger.";
    }
} catch (Exception $e) {
    // Ignore if expense table structure differs slightly
}

// Fetch Recent Logs
$stmt = $pdo->prepare("SELECT * FROM mood_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 5");
$stmt->execute([$user_id]);
$recentLogs = $stmt->fetchAll();

// Fetch Recent Gratitude Entries (for widget)
$stmt = $pdo->prepare("SELECT * FROM gratitude_entries WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$gratitudeLogs = $stmt->fetchAll();

// Fetch All Gratitude Entries (for History Modal)
$stmt = $pdo->prepare("SELECT * FROM gratitude_entries WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$allGratitudeLogs = $stmt->fetchAll();

// Group by Date for Timeline
$gratitudeByDate = [];
foreach ($allGratitudeLogs as $entry) {
    $date = date('Y-m-d', strtotime($entry['created_at']));
    $gratitudeByDate[$date][] = $entry;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Tracker - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.5);
            --primary-color: #6366f1;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
        }
        
        body { font-family: 'Inter', sans-serif; }

        .mood-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 25px;
            align-items: start;
        }

        .mood-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .mood-selector {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin: 25px 0;
        }

        .mood-btn {
            background: rgba(255,255,255,0.5);
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 15px 5px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .mood-btn:hover { transform: translateY(-4px); background: rgba(255,255,255,0.8); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .mood-btn.active { border-color: var(--primary-color); background: #e0e7ff; }
        .mood-btn-emoji { font-size: 2rem; display: block; margin-bottom: 5px; }
        .mood-btn-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }

        /* Range Input Styling */
        input[type=range] {
            -webkit-appearance: none; width: 100%; background: transparent;
        }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none; height: 20px; width: 20px; border-radius: 50%; 
            background: var(--primary-color); cursor: pointer; margin-top: -8px; 
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.4);
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%; height: 4px; cursor: pointer; background: #e5e7eb; border-radius: 2px;
        }

        /* Tags */
        .tag-checkbox input { display: none; }
        .tag-checkbox span {
            display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 20px;
            background: rgba(255,255,255,0.6); border: 1px solid rgba(0,0,0,0.05);
            cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-dark);
            transition: all 0.2s; user-select: none;
        }
        .tag-checkbox input:checked + span {
            background: var(--primary-color); color: white; border-color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3); transform: scale(1.05);
        }

        /* Modal styling */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.3); backdrop-filter: blur(4px);
            z-index: 1000; display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s;
        }
        .modal-overlay.open { display: flex; opacity: 1; }
        .modal-content {
            background: white; width: 90%; max-width: 500px; max-height: 80vh;
            border-radius: 24px; padding: 30px; overflow-y: auto;
            transform: translateY(20px); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .modal-overlay.open .modal-content { transform: translateY(0); }

        .log-item {
            background: #f9fafb; border-radius: 16px; padding: 16px; margin-bottom: 12px;
            display: flex; align-items: center; gap: 16px; border: 1px solid #f3f4f6;
        }
        
    </style>
</head>
<body style="background-color: #f3f4f6;"> <!-- Ensuring a base background if needed -->
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div class="welcome-text">
                    <h1 style="font-size: 2rem; margin-bottom: 5px;">Mood Tracker 🌈</h1>
                    <p style="color: var(--text-muted); font-weight: 500;">Check in with yourself, <?php echo htmlspecialchars($username); ?>.</p>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button onclick="openHistory()" class="btn-secondary" style="background: white; border: 1px solid #e5e7eb; padding: 10px 20px; border-radius: 12px; font-weight: 600; color: var(--text-dark); cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
                        <span>📜</span> History
                    </button>
                    <a href="api/report_mood.php" target="_blank" style="text-decoration: none; padding: 10px 20px; border-radius: 12px; font-weight: 600; background: var(--primary-color); color: white; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.25); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                         <!-- Simple Icon -->
                         <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        Full Report
                    </a>
                </div>
            </header>

            <div class="mood-grid">
                <!-- COL 1: Input & Companion -->
                <div style="display: flex; flex-direction: column; gap: 25px;">
                    
                    <!-- 1. Mood Input -->
                    <div class="mood-card">
                        <h3 style="margin-bottom: 8px; font-weight: 800; color: var(--text-dark);">How are you feeling?</h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500;">Select the emoji that best matches your vibe.</p>
                        
                        <form action="mood.php" method="POST" id="moodForm">
                            <input type="hidden" name="action" value="log_mood">
                            <input type="hidden" name="mood_score" id="selectedScore" value="3">
                            <input type="hidden" name="mood_label" id="selectedLabel" value="Neutral">
                            
                            <div class="mood-selector">
                                <div class="mood-btn" onclick="selectMood(1, 'Stressed', this)">
                                    <span class="mood-btn-emoji">😫</span><span class="mood-btn-label">Stress</span>
                                </div>
                                <div class="mood-btn" onclick="selectMood(2, 'Sad', this)">
                                    <span class="mood-btn-emoji">😔</span><span class="mood-btn-label">Low</span>
                                </div>
                                <div class="mood-btn active" onclick="selectMood(3, 'Neutral', this)">
                                    <span class="mood-btn-emoji">😐</span><span class="mood-btn-label">Okay</span>
                                </div>
                                <div class="mood-btn" onclick="selectMood(4, 'Good', this)">
                                    <span class="mood-btn-emoji">😊</span><span class="mood-btn-label">Good</span>
                                </div>
                                <div class="mood-btn" onclick="selectMood(5, 'Incredible', this)">
                                    <span class="mood-btn-emoji">🚀</span><span class="mood-btn-label">Great</span>
                                </div>
                            </div>
                            
                            <div style="margin-top: 25px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <label style="font-weight: 700; color: var(--text-dark);">Sleep Duration</label>
                                    <span id="sleepVal" style="font-weight: 700; color: var(--primary-color);">7h</span>
                                </div>
                                <input type="range" name="sleep_hours" min="0" max="12" value="7" step="0.5" oninput="document.getElementById('sleepVal').textContent = this.value + 'h'">
                            </div>

                            <div style="margin-top: 25px;">
                                <label style="font-weight: 700; color: var(--text-dark); margin-bottom: 12px; display: block;">What impacted your mood?</label>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Work"><span>💼 Work</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Study"><span>📚 Study</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Social"><span>👥 Social</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Exercise"><span>💪 Exercise</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Sleep"><span>😴 Sleep</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Health"><span>⚕️ Health</span></label>
                                    <label class="tag-checkbox"><input type="checkbox" name="activities[]" value="Hobbies"><span>🎨 Hobby</span></label>
                                </div>
                            </div>

                            <div class="form-group" style="margin-top: 25px;">
                                <label style="font-weight: 700; color: var(--text-dark); margin-bottom: 10px; display: block;">Notes (Optional)</label>
                                <textarea name="note" class="form-input" rows="3" placeholder="Identify your triggers..." style="width: 100%; border-radius: 12px; padding: 12px; border: 1px solid #e5e7eb; font-family: inherit;"></textarea>
                            </div>
                            
                            <button type="submit" style="width: 100%; padding: 16px; margin-top: 20px; font-weight: 700; background: #1f2937; color: white; border: none; border-radius: 12px; cursor: pointer; transition: background 0.2s;">Log Entry</button>
                        </form>
                    </div>

                </div>
                    
                    <!-- 2. Mood Companion -->
                     <div class="mood-card" style="border-top: 4px solid var(--primary-color);">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <div style="background: #e0e7ff; color: var(--primary-color); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">🟣</div>
                            <h3 style="font-weight: 800; color: var(--text-dark); margin: 0;">Mood Companion</h3>
                        </div>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 15px;">Vent, reflect, or just chat. Based on your current vibe.</p>
                        
                        <div id="chat-box" style="height: 250px; overflow-y: auto; background: #f9fafb; border-radius: 16px; padding: 15px; display: flex; flex-direction: column; gap: 10px;">
                            <div style="align-self: flex-start; background: white; padding: 12px 18px; border-radius: 18px 18px 18px 4px; max-width: 85%; box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-size: 0.95rem; color: var(--text-dark); border: 1px solid #f3f4f6;">
                                Hi <?php echo htmlspecialchars($username); ?>! I'm here to listen. How are you feeling right now?
                            </div>
                        </div>

                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <input type="text" id="chat-input" placeholder="Type a message..." style="flex: 1; padding: 12px 16px; border-radius: 12px; border: 1px solid #e5e7eb; outline: none; transition: border 0.2s;" onfocus="this.style.borderColor = 'var(--primary-color)'" onblur="this.style.borderColor = '#e5e7eb'">
                            <button onclick="sendMessage()" id="chat-send-btn" style="background: var(--primary-color); color: white; border: none; width: 46px; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center;">→</button>
                        </div>
                    </div>
                </div>

                <!-- COL 2: Gratitude & Insights (New Column) -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <!-- Mental Health Insights -->
                    <div class="mood-card">
                        <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 800; color: var(--text-dark);">
                            🧠 AI Insights
                        </h3>

                        <?php if ($burnout_alert): ?>
                            <div style="background: #fee2e2; border: 1px solid #fecaca; padding: 15px; border-radius: 12px; margin-bottom: 15px;">
                                <div style="font-weight: 800; color: #991b1b; display: flex; align-items: center; gap: 8px;">
                                    <span>⚠️</span> Potential Burnout Detected
                                </div>
                                <p style="font-size: 0.9rem; color: #7f1d1d; margin: 5px 0 0 0;">
                                    You've been completing a lot of tasks but your mood has been low lately. Consider taking a break.
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if ($trigger_insight): ?>
                            <div style="background: #e0f2fe; border: 1px solid #bae6fd; padding: 15px; border-radius: 12px; margin-bottom: 15px;">
                                <div style="font-weight: 800; color: #075985; display: flex; align-items: center; gap: 8px;">
                                    <span>📉</span> Mood Trigger Identification
                                </div>
                                <p style="font-size: 0.9rem; color: #0c4a6e; margin: 5px 0 0 0;">
                                    <?php echo $trigger_insight; ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if (!$burnout_alert && !$trigger_insight): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 10px;">
                                <div style="font-size: 2rem;">✨</div>
                                <p>No critical alerts. Keep maintaining a healthy balance!</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Gratitude Journal -->
                    <div class="mood-card">
                        <h3 style="margin-bottom: 15px; font-weight: 800; color: var(--text-dark);">🌸 Gratitude Journal</h3>
                        <form method="POST" action="mood.php">
                            <input type="hidden" name="action" value="log_gratitude">
                            <div style="display: flex; gap: 10px;">
                                <input type="text" name="gratitude_entry" class="form-input" placeholder="I am grateful for..." required style="border-radius: 20px; flex: 1;">
                                <button type="submit" class="btn btn-primary" style="padding: 10px 15px; border-radius: 50%; font-weight: 800;">➜</button>
                            </div>
                        </form>

                        <div style="margin-top: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <h4 style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin: 0;">Recent Wins</h4>
                                <button onclick="openGratitudeHistory()" style="background: none; border: none; color: var(--primary-color); font-size: 0.8rem; font-weight: 600; cursor: pointer;">View All</button>
                            </div>
                            <?php if (empty($gratitudeLogs)): ?>
                                <p style="color: #9ca3af; font-style: italic; font-size: 0.9rem;">Start your journey of gratitude today.</p>
                            <?php else: ?>
                                <ul style="list-style: none; padding: 0; margin-top: 10px;">
                                    <?php foreach($gratitudeLogs as $gbox): ?>
                                        <li style="background: white; padding: 12px 15px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #f3f4f6; display: flex; align-items: flex-start; gap: 10px;">
                                            <span style="color: #ec4899;">🌺</span>
                                            <div>
                                                <div style="font-weight: 600; color: #374151; font-size: 0.95rem;"><?php echo htmlspecialchars($gbox['entry_text']); ?></div>
                                                <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 2px;"><?php echo date('M d, H:i', strtotime($gbox['created_at'])); ?></div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    


                </div>

                <!-- COL 2: Trends -->
                <div>
                     <div class="mood-card" style="min-height: 100%;">
                        <h3 style="margin-bottom: 8px; font-weight: 800; color: var(--text-dark);">Emotional Trends</h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500; margin-bottom: 25px;">Last 7 days of your mindset cycle.</p>
                        
                        <div class="mood-chart-container" style="height: 250px;">
                            <canvas id="moodChart"></canvas>
                        </div>
                        
                        <div style="margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 30px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
                                <h4 style="color: var(--text-dark); font-weight: 800; font-size: 1.1rem; margin:0;">Monthly Overview</h4>
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); padding: 4px 10px; background: #f3f4f6; border-radius: 8px;"><?php echo date('Y'); ?></span>
                            </div>
                            
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

                            <div class="calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; background: #f9fafb; padding: 10px; border-radius: 12px;">
                                <a href="?m=<?php echo $prevM; ?>&y=<?php echo $prevY; ?>" style="color: var(--text-dark); text-decoration:none; padding: 5px 10px; font-weight: bold;">&lsaquo;</a>
                                <span style="font-weight: 700; color: var(--text-dark);"><?php echo date('F', mktime(0, 0, 0, $month, 1, $year)); ?></span>
                                <a href="?m=<?php echo $nextM; ?>&y=<?php echo $nextY; ?>" style="color: var(--text-dark); text-decoration:none; padding: 5px 10px; font-weight: bold;">&rsaquo;</a>
                            </div>

                            <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center;">
                                <div class="cal-head">Su</div><div class="cal-head">Mo</div><div class="cal-head">Tu</div>
                                <div class="cal-head">We</div><div class="cal-head">Th</div><div class="cal-head">Fr</div><div class="cal-head">Sa</div>
                                
                                <?php
                                for ($i = 0; $i < $firstDayOfWeek; $i++) echo "<div></div>";
                                
                                for ($day = 1; $day <= $daysInMonth; $day++) {
                                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $hasLog = isset($monthLogs[$dateStr]);
                                    
                                    $bg = 'var(--glass-border)';
                                    $emoji = '';
                                    $isToday = ($dateStr == date('Y-m-d'));
                                    
                                    if ($hasLog) {
                                        $score = $monthLogs[$dateStr]['mood_score'];
                                        $colors = ['', '#fecaca', '#fed7aa', '#fef08a', '#bbf7d0', '#e9d5ff']; // lighter pastels
                                        $emojis = ['', '😫', '😔', '😐', '😊', '🚀'];
                                        $bg = $colors[$score]; 
                                        $emoji = $emojis[$score];
                                    }
                                    
                                    $border = $isToday ? 'border: 2px solid var(--primary-color);' : '';
                                    
                                    echo "<div style='background: $bg; $border border-radius: 10px; padding: 6px 2px; font-size: 0.8rem; color: var(--text-dark); min-height: 45px; display: flex; flex-direction: column; justify-content: center; align-items: center;'>
                                            <div style='font-weight:700; font-size: 0.75rem; opacity: 0.7;'>$day</div>
                                            <div style='font-size:1rem; line-height: 1;'>$emoji</div>
                                          </div>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="gratitude-modal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px; background: #fffaf0;"> <!-- Warm paper-like bg -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div>
                     <h2 style="margin: 0; font-weight: 800; color: #78350f; font-family: 'Georgia', serif;">My Gratitude Journal 🌺</h2>
                     <p style="margin: 5px 0 0 0; color: #92400e; font-size: 0.9rem;">Reflecting on the good moments.</p>
                </div>
                <button onclick="closeGratitudeHistory()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #92400e;">&times;</button>
            </div>
            
            <div style="max-height: 60vh; overflow-y: auto; padding-right: 10px;">
                <?php if (empty($gratitudeByDate)): ?>
                    <div style="text-align: center; padding: 40px; color: #b45309;">
                         <div style="font-size: 3rem; margin-bottom: 10px;">📔</div>
                         <p>Your journal is empty. Write your first entry today!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($gratitudeByDate as $date => $entries): 
                        // Explicit Date Format: Wednesday, January 28, 2026
                        $dateLabel = date('l, F j, Y', strtotime($date));
                    ?>
                        <div style="margin-bottom: 30px; position: relative; padding-left: 20px; border-left: 2px solid #fed7aa;">
                            <div style="position: absolute; left: -6px; top: 0; width: 10px; height: 10px; background: #f97316; border-radius: 50%;"></div>
                            <h3 style="margin: 0 0 15px 0; font-size: 1rem; color: #9a3412; font-weight: 700;"><?php echo $dateLabel; ?></h3>
                            
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <?php foreach ($entries as $entry): ?>
                                    <div style="background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); font-family: 'Georgia', serif; font-size: 1.05rem; color: #4a4a4a; line-height: 1.5;">
                                        <?php echo htmlspecialchars($entry['entry_text']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div id="history-modal" class="modal-overlay">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; font-weight: 800; color: var(--text-dark);">Recent Check-ins</h2>
                <button onclick="closeHistory()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
            
            <?php if (empty($recentLogs)): ?>
                <div style="text-align: center; padding: 40px; background: #f9fafb; border-radius: 16px;">
                    <div style="font-size: 3rem; margin-bottom: 10px;">🍃</div>
                    <p style="color: var(--text-dark); font-weight: 600;">No entries yet</p>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Your journey starts with a single mood.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentLogs as $log): 
                    $emoji = ['😫','😔','😐','😊','🚀'][$log['mood_score']-1];
                ?>
                    <div class="log-item">
                        <div style="background: white; border-radius: 12px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <?php echo $emoji; ?>
                        </div>
                        <div>
                            <div style="font-weight: 700; color: var(--text-dark);"><?php echo $log['mood_label']; ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;"><?php echo date('l, M jS', strtotime($log['log_date'])); ?></div>
                            <?php if ($log['note']): ?>
                                <div style="font-size: 0.9rem; color: #4b5563; margin-top: 4px;">"<?php echo htmlspecialchars($log['note']); ?>"</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function selectMood(score, label, el) {
            document.querySelectorAll('.mood-btn').forEach(btn => btn.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('selectedScore').value = score;
            document.getElementById('selectedLabel').value = label;
        }

        // Modal Logic
        const modal = document.getElementById('history-modal');
        function openHistory() {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('open'), 10);
        }
        function closeHistory() {
            modal.classList.remove('open');
            setTimeout(() => modal.style.display = 'none', 300);
        }
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeHistory();
        });

        // Gratitude Modal Logic
        const gratModal = document.getElementById('gratitude-modal');
        function openGratitudeHistory() {
            gratModal.style.display = 'flex';
            setTimeout(() => gratModal.classList.add('open'), 10);
        }
        function closeGratitudeHistory() {
            gratModal.classList.remove('open');
            setTimeout(() => gratModal.style.display = 'none', 300);
        }
        gratModal.addEventListener('click', function(e) {
            if (e.target === gratModal) closeGratitudeHistory();
        });

        // Initialize Chart
        const ctx = document.getElementById('moodChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Vibe Level',
                    data: <?php echo json_encode($scores); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#6366f1',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true, max: 5, grid: { display: false }, ticks: { display: false }
                    },
                    x: {
                        grid: { display: false }, ticks: { color: '#9ca3af', font: { weight: '600', size: 10 } }
                    }
                },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1f2937', padding: 10, cornerRadius: 8 } }
            }
        });

        // Chatbot Logic
        const chatBox = document.getElementById('chat-box');
        const chatInput = document.getElementById('chat-input');
        const chatBtn = document.getElementById('chat-send-btn');
        const currentMoodLabel = "<?php echo !empty($recentLogs) ? $recentLogs[0]['mood_label'] : 'Neutral'; ?>";
        
        chatInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') sendMessage();
        });

        function sendMessage() {
            const text = chatInput.value.trim();
            if (!text) return;

            addMessage(text, 'user');
            chatInput.value = '';
            chatInput.disabled = true;
            
            const typingId = addMessage('...', 'bot', true);

            fetch('api/chat_mood.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text, mood_context: currentMoodLabel })
            })
            .then(res => res.json())
            .then(data => {
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
            
            if (sender === 'user') {
                div.style.cssText = "align-self: flex-end; background: #6366f1; color: white; padding: 12px 18px; border-radius: 18px 18px 4px 18px; max-width: 85%; font-size: 0.95rem; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);";
            } else {
                div.style.cssText = "align-self: flex-start; background: white; color: #1f2937; padding: 12px 18px; border-radius: 18px 18px 18px 4px; max-width: 85%; font-size: 0.95rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #f3f4f6;";
                if(isTyping) { div.style.color = "#9ca3af"; div.style.fontStyle = "italic"; }
            }
            
            if (isTyping) div.id = 'typing-' + Date.now();
            
            div.textContent = text;
            chatBox.appendChild(div);
            chatBox.scrollTop = chatBox.scrollHeight;
            return div.id;
        }
    </script>
</body>
</html>
