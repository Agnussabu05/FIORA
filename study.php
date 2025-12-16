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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .study-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .timer-display {
            font-size: 4rem;
            font-weight: 700;
            margin: 20px 0;
            font-variant-numeric: tabular-nums;
        }
        .timer-controls {
            display: flex; gap: 10px; justify-content: center;
        }
        .session-item {
            background: rgba(255,255,255,0.05);
            padding: 15px; border-radius: var(--radius-md);
            margin-bottom: 10px;
            display: flex; justify-content: space-between; align-items: center;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Study Planner 📚</h1>
                    <p style="color: var(--text-muted);">Focus, learn, and grow.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ Schedule Session</button>
            </header>

            <div class="study-container">
                <div>
                    <!-- Stats -->
                    <div class="glass-card" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0;">Total Study Time</h3>
                            <p style="color: var(--text-muted);">All time tracked sessions</p>
                        </div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary);">
                            <?php echo $totalHours; ?>h <?php echo $remainingMins; ?>m
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="glass-card">
                        <h3>Upcoming Sessions</h3>
                        <div style="margin-top: 15px;">
                            <?php if (count($sessions) > 0): ?>
                                <?php foreach($sessions as $session): ?>
                                    <div class="session-item">
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($session['subject']); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                <?php echo date('M d, H:i', strtotime($session['session_date'])); ?> • <?php echo $session['duration_minutes']; ?> mins
                                            </div>
                                            <?php if($session['notes']): ?>
                                                <div style="font-size: 0.8rem; color: var(--text-muted); font-style: italic; margin-top: 4px;">"<?php echo htmlspecialchars($session['notes']); ?>"</div>
                                            <?php endif; ?>
                                        </div>
                                        <a href="?delete=<?php echo $session['id']; ?>" style="color: var(--danger); text-decoration: none;">✕</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: var(--text-muted);">No study sessions scheduled.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Focus Timer -->
                <div>
                    <div class="glass-card" style="text-align: center; position: sticky; top: 20px;">
                        <h3>Focus Timer ⏳</h3>
                        <div class="timer-display" id="timer">25:00</div>
                        
                        <div class="timer-controls">
                            <button class="btn btn-primary" onclick="startTimer()" id="startBtn">Start</button>
                            <button class="btn btn-secondary" onclick="pauseTimer()" id="pauseBtn" style="display:none;">Pause</button>
                            <button class="btn btn-secondary" onclick="resetTimer()">Reset</button>
                        </div>

                        <div style="margin-top: 20px; display: flex; gap: 5px; justify-content: center;">
                            <button class="btn" style="font-size: 0.8rem; background: rgba(255,255,255,0.1); color: white;" onclick="setMode(25)">Pomodoro (25)</button>
                            <button class="btn" style="font-size: 0.8rem; background: rgba(255,255,255,0.1); color: white;" onclick="setMode(50)">Deep (50)</button>
                        </div>
                        
                        <p style="margin-top: 15px; font-size: 0.8rem; color: var(--text-muted);">
                            Use this timer to stay focused. We'll play a sound when time is up!
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="studyModal">
        <div class="glass-card" style="width: 400px;">
            <h3 style="margin-bottom: 20px;">Schedule Study Session</h3>
            <form action="study.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Subject</label>
                    <input type="text" name="subject" class="form-input" required placeholder="Calculus, History...">
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Duration (Minutes)</label>
                    <input type="number" name="duration" class="form-input" required value="60">
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Date & Time</label>
                    <input type="datetime-local" name="date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Notes (Goals)</label>
                    <textarea name="notes" class="form-input" rows="2" placeholder="Read chapter 4..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('studyModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('studyModal').classList.remove('active');
        }
        document.getElementById('studyModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('studyModal')) closeModal();
        });

        // Timer Logic
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
            
            timerId = setInterval(() => {
                if (timeLeft > 0) {
                    timeLeft--;
                    updateDisplay();
                } else {
                    clearInterval(timerId);
                    isRunning = false;
                    alert("Time's up! Great work! 🎯");
                    resetTimer();
                }
            }, 1000);
        }

        function pauseTimer() {
            clearInterval(timerId);
            isRunning = false;
            startBtn.style.display = 'inline-block';
            pauseBtn.style.display = 'none';
        }

        function resetTimer() {
            pauseTimer();
            timeLeft = 25 * 60; 
            updateDisplay();
        }

        function setMode(mins) {
            pauseTimer();
            timeLeft = mins * 60;
            updateDisplay();
        }
    </script>
</body>
</html>
