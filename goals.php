<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page = 'goals';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $target = $_POST['target_date'];
        $cat = $_POST['category'];
        
        $stmt = $pdo->prepare("INSERT INTO goals (user_id, title, description, target_date, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $desc, $target, $cat]);
        header("Location: goals.php");
        exit;
    }
    
    if ($action === 'toggle_status') {
        $id = $_POST['goal_id'];
        $stmt = $pdo->prepare("SELECT status FROM goals WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $current = $stmt->fetchColumn();
        $new_status = ($current === 'active') ? 'completed' : 'active';
        $new_progress = ($new_status === 'completed') ? 100 : 0;
        
        $stmt = $pdo->prepare("UPDATE goals SET status = ?, progress = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $new_progress, $id, $user_id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM goals WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: goals.php");
    exit;
}

// Fetch Goals
$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ? ORDER BY status ASC, target_date ASC");
$stmt->execute([$user_id]);
$goals = $stmt->fetchAll();

// Stats
$activeCount = 0;
$completedCount = 0;
foreach ($goals as $g) {
    if ($g['status'] === 'active') $activeCount++;
    else $completedCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goals & Ambitions - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .goals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .goal-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .goal-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }
        
        .cat-tag {
            font-size: 0.7rem; padding: 4px 10px; border-radius: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; background: rgba(0,0,0,0.04); color: #555;
        }
        .goal-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); }
        .goal-desc { font-size: 0.9rem; color: #666; font-weight: 500; line-height: 1.4; }
        
        .goal-meta { display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 600; color: #888; }
        .completed { opacity: 0.6; grayscale: 50%; border-color: var(--success); }
        
        .toggle-btn {
            background: rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.1); padding: 8px 15px; border-radius: 10px;
            font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
        }
        .toggle-btn:hover { background: rgba(0,0,0,0.1); }
        .toggle-btn.is-completed { background: var(--success); color: white; border-color: var(--success); }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Goals & Ambitions 🎯</h1>
                    <p style="color: #444; font-weight: 500;">Design your future, one milestone at a time.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ New Goal</button>
            </header>

            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="glass-card" style="padding: 20px; text-align: center; border: 1px solid var(--glass-border);">
                    <div style="font-size: 2.2rem; font-weight: 800; color: #000;"><?php echo $activeCount; ?></div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: #333;">ACTIVE GOALS</div>
                </div>
                <div class="glass-card" style="padding: 20px; text-align: center; border: 1px solid var(--glass-border);">
                    <div style="font-size: 2.2rem; font-weight: 800; color: var(--success);"><?php echo $completedCount; ?></div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: #333;">ACCOMPLISHED</div>
                </div>
            </div>

            <div class="goals-grid">
                <?php if (count($goals) > 0): ?>
                    <?php foreach ($goals as $goal): ?>
                        <div class="goal-card <?php echo $goal['status'] === 'completed' ? 'completed' : ''; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <span class="cat-tag" style="background: rgba(0,0,0,0.1); color: #000; font-weight: 800;"><?php echo htmlspecialchars($goal['category']); ?></span>
                                <div style="display: flex; gap: 8px;">
                                    <?php if ($goal['status'] === 'completed'): ?>
                                        <span style="font-size: 1.2rem;">🏆</span>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $goal['id']; ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 900; color: var(--danger); opacity: 0.6;" onclick="return confirm('Archive this goal?')">✕</a>
                                </div>
                            </div>
                            
                            <div>
                                <div class="goal-title" style="color: #000 !important;"><?php echo htmlspecialchars($goal['title']); ?></div>
                                <div class="goal-desc" style="color: #222 !important; font-weight: 600;"><?php echo htmlspecialchars($goal['description']); ?></div>
                            </div>

                            <div style="margin-top: 10px;">
                                <button class="toggle-btn <?php echo $goal['status'] === 'completed' ? 'is-completed' : ''; ?>" 
                                        onclick="toggleStatus(<?php echo $goal['id']; ?>)" style="font-weight: 800;">
                                    <?php echo $goal['status'] === 'completed' ? '✓ Completed' : 'Mark as Done'; ?>
                                </button>
                            </div>

                            <div class="goal-meta">
                                <span style="color: #333; font-weight: 700;">📅 <?php echo $goal['target_date'] ? date('M d, Y', strtotime($goal['target_date'])) : 'No Target'; ?></span>
                                <span style="font-weight: 900; color: <?php echo $goal['status'] === 'completed' ? 'var(--success)' : '#000'; ?>;">
                                    <?php echo ($goal['status'] === 'completed') ? 'Finished' : 'In Progress'; ?>
                                </span>
                            </div>
                        </div>
<? Houses the same block as before but with colors changed to be highly visible: #000, #222, #333, and better font weights. ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
                        <div style="font-size: 4rem; margin-bottom: 20px;">🎯</div>
                        <h3 style="color: #666;">Your journey begins with a single goal</h3>
                        <p style="color: #888;">What do you want to achieve? Set your first ambition and watch your dreams take shape.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Goal Modal -->
    <div class="modal" id="goalModal">
        <div class="glass-card" style="width: 420px; padding: 30px;">
            <h3 style="margin-bottom: 25px;">Set New Ambition</h3>
            <form action="goals.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>What do you want to achieve?</label>
                    <input type="text" name="title" class="form-input" required placeholder="Launch Website, Run 10km...">
                </div>
                <div class="form-group">
                    <label>A brief description (Why is this important?)</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="To improve my career prospects..."></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Target Date</label>
                        <input type="date" name="target_date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-input">
                            <option value="Personal">Personal</option>
                            <option value="Career">Career</option>
                            <option value="Health">Health</option>
                            <option value="Finance">Finance</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="color: #000 !important; font-weight: 800;">Maybe Later</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">Set Goal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('goalModal').classList.add('active'); }
        function closeModal() { document.getElementById('goalModal').classList.remove('active'); }
        
        async function toggleStatus(goalId) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('goal_id', goalId);

            try {
                const response = await fetch('goals.php', { method: 'POST', body: formData });
                if (response.ok) {
                    location.reload(); 
                }
            } catch (err) { console.error(err); }
        }
    </script>
</body>
</html>
