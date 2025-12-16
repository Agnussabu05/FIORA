<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'tasks';

// Handle Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $deadline = $_POST['deadline'];
    $priority = $_POST['priority'];
    // Default user_id = $user_id
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, deadline, priority) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $desc, $deadline, $priority]);
    header("Location: tasks.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: tasks.php");
    exit;
}

// Handle Mark Complete
if (isset($_GET['complete'])) {
    $id = $_GET['complete'];
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'completed' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: tasks.php");
    exit;
}

// Fetch Tasks
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY deadline ASC");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Tasks</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .task-list {
            margin-top: 20px;
        }
        .task-item {
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid var(--glass-border);
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.2s;
        }
        .task-item:first-child { border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .task-item:last-child { border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; border-bottom: none; }
        .task-item:hover { background: rgba(255,255,255,0.1); }
        
        .priority-dot {
            height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 8px;
        }
        .p-high { background: var(--danger); }
        .p-medium { background: var(--warning); }
        .p-low { background: var(--success); }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal.active { display: flex; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Tasks & Deadlines 📝</h1>
                    <p style="color: var(--text-muted);">Stay on top of your workload.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ New Task</button>
            </header>

            <div class="glass-card">
                <h3>Your Tasks</h3>
                <div class="task-list">
                    <?php if (count($tasks) > 0): ?>
                        <?php foreach($tasks as $task): ?>
                            <div class="task-item" style="<?php echo $task['status'] === 'completed' ? 'opacity: 0.5; text-decoration: line-through;' : ''; ?>">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <?php if ($task['status'] !== 'completed'): ?>
                                        <a href="?complete=<?php echo $task['id']; ?>" style="border: 2px solid var(--text-muted); width: 20px; height: 20px; border-radius: 50%; display: block;"></a>
                                    <?php else: ?>
                                        <span style="color: var(--success);">✔</span>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <div style="font-weight: 600;">
                                            <?php echo htmlspecialchars($task['title']); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            <span class="priority-dot p-<?php echo $task['priority']; ?>"></span>
                                            <?php echo ucfirst($task['priority']); ?> • Due: <?php echo date('M d, H:i', strtotime($task['deadline'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <a href="?delete=<?php echo $task['id']; ?>" style="color: var(--danger); text-decoration: none;">✕</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="padding: 20px; text-align: center; color: var(--text-muted);">No tasks found. Add one to get started!</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Task Modal -->
    <div class="modal" id="taskModal">
        <div class="glass-card" style="width: 400px;">
            <h3 style="margin-bottom: 20px;">Add New Task</h3>
            <form action="tasks.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Title</label>
                    <input type="text" name="title" class="form-input" required placeholder="Project presentation...">
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Description</label>
                    <textarea name="description" class="form-input" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Deadline</label>
                    <input type="datetime-local" name="deadline" class="form-input" required>
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Priority</label>
                    <select name="priority" class="form-input">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('taskModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('taskModal').classList.remove('active');
        }
        // Close on click outside
        document.getElementById('taskModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('taskModal')) closeModal();
        });
    </script>
</body>
</html>
