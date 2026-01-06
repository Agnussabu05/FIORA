<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'study';

// Handle Add Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $subject = $_POST['subject'];
    $duration = $_POST['duration']; // in minutes
    $date = $_POST['date'];
    $notes = $_POST['notes'];
    
    $stmt = $pdo->prepare("INSERT INTO study_sessions (user_id, subject, duration_minutes, session_date, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $subject, $duration, $date, $notes]);
    header("Location: study.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM study_sessions WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: study.php");
    exit;
}

// Fetch Sessions
$stmt = $pdo->prepare("SELECT * FROM study_sessions WHERE user_id = ? ORDER BY session_date ASC");
$stmt->execute([$user_id]);
$sessions = $stmt->fetchAll();

// Total Study Hours Calculation
$totalMins = 0;
foreach($sessions as $s) $totalMins += $s['duration_minutes'];
$totalHours = floor($totalMins / 60);
$remainingMins = $totalMins % 60;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Study Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .study-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .timer-display {
            font-family: 'JetBrains Mono', monospace;
            font-size: 5rem;
            font-weight: 700;
            margin: 20px 0;
            color: var(--text-main);
            text-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .timer-controls {
            display: flex; gap: 12px; justify-content: center;
            margin-bottom: 20px;
        }
        .session-item {
            background: rgba(255,255,255,0.4);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            padding: 18px; border-radius: 18px;
            margin-bottom: 15px;
            display: flex; justify-content: space-between; align-items: center;
            transition: transform 0.2s;
        }
        .session-item:hover { transform: scale(1.01); background: rgba(255,255,255,0.6); }
        
        .mode-btn {
            font-size: 0.85rem; padding: 10px 18px; border-radius: 12px;
            background: rgba(0,0,0,0.05); color: #222222 !important;
            border: 1px solid rgba(0,0,0,0.1); cursor: pointer;
            font-weight: 700; transition: all 0.3s;
        }
        .mode-btn:hover { background: rgba(0,0,0,0.1); }
        .mode-btn.active { background: #222222; color: #ffffff !important; border-color: #222222; }
        
        .timer-card {
            text-align: center; position: sticky; top: 20px;
            background: var(--glass-bg); padding: 35px; border-radius: 25px;
            border: 2px solid var(--glass-border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        /* Visibility Fixes for Control Buttons */
        #startBtn { background: #222222 !important; color: #ffffff !important; }
        #pauseBtn { background: #D9A066 !important; color: #ffffff !important; border: none; }
        .btn-restart { background: #9E9080 !important; color: #ffffff !important; border: none; }
        
        /* Visibility Improvements */
        h1, h3 { color: #222222 !important; font-weight: 800; }
        .text-high-contrast { color: #000000 !important; font-weight: 700; }
        .muted-better { color: #333333 !important; font-weight: 600; opacity: 0.8; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Study Planner 📚</h1>
                    <p class="muted-better">Focus, learn, and master your crafts.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ Schedule Session</button>
            </header>

            <div class="study-container">
                <div>
                    <!-- Upcoming Timeline -->
                    <div class="glass-card" style="padding: 25px; border-radius: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0;">Upcoming Sessions</h3>
                            <span style="font-size: 0.8rem; background: var(--secondary); color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                                <?php echo count($sessions); ?> Planned
                            </span>
                        </div>
                        <div style="margin-top: 15px;">
                            <?php if (count($sessions) > 0): ?>
                                <?php foreach($sessions as $session): ?>
                                    <div class="session-item">
                                        <div style="display: flex; gap: 20px; align-items: center;">
                                            <div style="width: 45px; height: 45px; background: rgba(0,0,0,0.04); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">📖</div>
                                            <div>
                                                <div class="text-high-contrast" style="font-size: 1.1rem;"><?php echo htmlspecialchars($session['subject']); ?></div>
                                                <div style="font-size: 0.85rem; color: #666; font-weight: 500;">
                                                    <span style="color: var(--primary);">●</span> <?php echo date('M d, H:i', strtotime($session['session_date'])); ?> 
                                                    <span style="margin: 0 5px; opacity: 0.5;">|</span> 
                                                    <strong><?php echo $session['duration_minutes']; ?> mins</strong>
                                                </div>
                                                <?php if($session['notes']): ?>
                                                    <div style="font-size: 0.8rem; color: #777; font-style: italic; margin-top: 6px; background: rgba(0,0,0,0.03); padding: 4px 8px; border-radius: 6px;">
                                                        "<?php echo htmlspecialchars($session['notes']); ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a href="?delete=<?php echo $session['id']; ?>" class="btn" style="background: rgba(192, 108, 108, 0.1); color: var(--danger); min-width: 40px; padding: 8px;" onclick="return confirm('Delete this session?')">✕</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <div style="font-size: 3rem; margin-bottom: 15px;">🌱</div>
                                    <p>Your study schedule is clear. Ready to start?</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Focus Timer Section -->
                <div>
                    <div class="timer-card">
                        <h3 style="margin-bottom: 25px; letter-spacing: 1px;">FOCUS TIMER</h3>
                        
                        <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 20px;">
                            <button class="mode-btn active" id="pomoMode" onclick="setMode(25, 'pomo')">Pomodoro</button>
                            <button class="mode-btn" id="deepMode" onclick="setMode(50, 'deep')">Deep Work</button>
                        </div>

                        <div class="timer-display" id="timer">25:00</div>
                        
                        <div class="timer-controls">
                            <button class="btn btn-primary" style="padding: 12px 30px; font-weight: 700; min-width: 120px;" onclick="startTimer()" id="startBtn">START</button>
                            <button class="btn btn-secondary" style="padding: 12px 30px; font-weight: 700; min-width: 120px; display:none;" onclick="pauseTimer()" id="pauseBtn">PAUSE</button>
                            <button class="btn btn-restart" style="padding: 12px 20px; font-weight: 700;" onclick="restartTimer()">RESTART</button>
                        </div>
                        
                        <p style="margin-top: 25px; font-size: 0.85rem; color: #666; line-height: 1.5;">
                            Stay concentrated and avoid distractions.<br>A chime will sound when the session ends.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="studyModal">
        <div class="glass-card" style="width: 420px; padding: 30px;">
            <h3 style="margin-bottom: 25px;">Schedule Session</h3>
            <form action="study.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Module / Subject</label>
                    <input type="text" name="subject" class="form-input" required placeholder="Calculus, Web Dev, etc.">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Duration (Mins)</label>
                        <input type="number" name="duration" class="form-input" required value="60">
                    </div>
                    <div class="form-group">
                        <label>Date & Time</label>
                        <input type="datetime-local" name="date" class="form-input" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Focus Goals (Optional)</label>
                    <textarea name="notes" class="form-input" rows="3" placeholder="What are we mastering today?"></textarea>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="color: #000 !important; font-weight: 800;">Dismiss</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('studyModal').classList.add('active'); }
        function closeModal() { document.getElementById('studyModal').classList.remove('active'); }
        document.getElementById('studyModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('studyModal')) closeModal();
        });

        // Timer Logic
        let currentModeMins = 25;
        let timeLeft = 25 * 60;
        let timerId = null;
        let isRunning = false;

        const timerEl = document.getElementById('timer');
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');

        function updateDisplay() {
            const m = Math.floor(timeLeft / 60).toString().padStart(2, '0');
            const s = (timeLeft % 60).toString().padStart(2, '0');
            timerEl.innerText = `${m}:${s}`;
        }

        function startTimer() {
            if (isRunning) return;
            isRunning = true;
            startBtn.style.display = 'none';
            pauseBtn.style.display = 'inline-block';
            pauseBtn.innerText = 'PAUSE';
            
            timerId = setInterval(() => {
                if (timeLeft > 0) {
                    timeLeft--;
                    updateDisplay();
                } else {
                    clearInterval(timerId);
                    isRunning = false;
                    new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3').play();
                    alert("Focus session complete! Take a break. 🎯");
                    restartTimer();
                }
            }, 1000);
        }

        function pauseTimer() {
            clearInterval(timerId);
            isRunning = false;
            startBtn.style.display = 'inline-block';
            startBtn.innerText = 'RESUME';
            pauseBtn.style.display = 'none';
        }

        function restartTimer() {
            clearInterval(timerId);
            isRunning = false;
            timeLeft = currentModeMins * 60;
            startBtn.style.display = 'inline-block';
            startBtn.innerText = 'START';
            pauseBtn.style.display = 'none';
            updateDisplay();
        }

        function setMode(mins, modeId) {
            currentModeMins = mins;
            document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(modeId + 'Mode').classList.add('active');
            restartTimer();
        }
    </script>
</body>
</html>
