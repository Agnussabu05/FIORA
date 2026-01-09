<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'tasks';

// Filter Params
$filter_status = $_GET['status'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Handle Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $deadline = $_POST['deadline'];
    $category = $_POST['category'] ?? 'Personal';
    
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, deadline, priority, category, status, estimated_time, recurrent_type, user_order) VALUES (?, ?, ?, ?, 'medium', ?, 'pending', 0, 'none', 0)");
    $stmt->execute([$user_id, $title, $desc, $deadline, $category]);
    
    header("Location: tasks.php?status=$filter_status&category=$filter_category");
    exit;
}

// Handle Complete/Uncomplete Toggle
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $new_status = $_GET['new_status']; // 'completed' or 'pending'
    $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?")->execute([$new_status, $id, $user_id]);
    header("Location: tasks.php?status=$filter_status&category=$filter_category");
    exit;
}

// Handle Delete Task
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
    header("Location: tasks.php?status=$filter_status&category=$filter_category");
    exit;
}

// Build Query
$query = "SELECT * FROM tasks WHERE user_id = ?";
$params = [$user_id];

if ($filter_status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter_status;
}
if ($filter_category !== 'all') {
    $query .= " AND category = ?";
    $params[] = $filter_category;
}

$stmt = $pdo->prepare("$query ORDER BY created_at DESC");
$stmt->execute($params);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Tasks</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-section {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.05);
        }
        
        /* Custom Premium Checkbox */
        .checkbox-wrapper {
            position: relative;
            width: 28px;
            height: 28px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .checkbox-wrapper input {
            position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0;
        }
        .checkmark {
            position: absolute; top: 0; left: 0; height: 28px; width: 28px;
            background-color: white; border: 2px solid var(--primary);
            border-radius: 8px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .checkbox-wrapper:hover input ~ .checkmark { background-color: #f0f0f0; transform: scale(1.1); }
        .checkbox-wrapper input:checked ~ .checkmark { background-color: var(--primary); transform: scale(1.05); }
        .checkmark:after {
            content: ""; position: absolute; display: none;
            left: 9px; top: 4px; width: 6px; height: 12px;
            border: solid white; border-width: 0 3px 3px 0; transform: rotate(45deg);
        }
        .checkbox-wrapper input:checked ~ .checkmark:after { display: block; }

        .task-item {
            background: var(--glass-bg);
            border-bottom: 1px solid var(--glass-border);
            padding: 18px 25px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        .task-item:hover { background: rgba(255,255,255,0.7); transform: translateX(8px); }
        .task-item.completed { opacity: 0.6; }
        .task-item.completed .task-title { text-decoration: line-through; }

        .modal {
            display: none; position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4); backdrop-filter: blur(8px);
            align-items: center; justify-content: center; z-index: 1000;
        }
        .modal.active { display: flex; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div>
                    <h1 style="color: #000 !important;">Tasks & Deadlines</h1>
                    <p style="color: #222; font-weight: 700;">Organize your life elegantly.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ New Task</button>
            </header>

            <div class="filter-section">
                <!-- Status Tabs -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div class="tab-group" style="background: rgba(255,255,255,0.6); padding: 5px; border-radius: 15px; display: inline-flex; gap: 5px; border: 1px solid var(--glass-border);">
                        <?php 
                        $statuses = ['all' => '📋 All', 'pending' => '⏳ Pending', 'completed' => '✅ Completed'];
                        foreach($statuses as $val => $label): ?>
                            <button onclick="window.location.href='tasks.php?status=<?php echo $val; ?>&category=<?php echo $filter_category; ?>'" 
                                    class="filter-pill <?php echo $filter_status == $val ? 'active' : ''; ?>"
                                    style="padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; font-weight: 700; transition: all 0.3s; <?php echo $filter_status == $val ? 'background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(105, 126, 80, 0.3);' : 'background: transparent; color: var(--primary);'; ?>">
                                <?php echo $label; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Category Pills -->
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <span style="font-weight: 800; font-size: 0.95rem; color: #000; margin-right: 5px;">Filter Category:</span>
                    <?php 
                    $categories = ['all' => '🏷️ All', 'Personal' => '👤 Personal', 'Work' => '💼 Work', 'Health' => '🏥 Health'];
                    foreach($categories as $val => $label): ?>
                        <button onclick="window.location.href='tasks.php?category=<?php echo $val; ?>&status=<?php echo $filter_status; ?>'" 
                                class="category-filter-pill <?php echo $filter_category == $val ? 'active' : ''; ?>"
                                style="padding: 10px 20px; border-radius: 20px; border: 2px solid var(--glass-border); cursor: pointer; font-weight: 800; transition: all 0.3s; <?php echo $filter_category == $val ? 'background: #222; color: white; border-color: #222; box-shadow: 0 4px 10px rgba(0,0,0,0.2);' : 'background: white; color: #333;'; ?>">
                            <?php echo $label; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="glass-card">
                <div class="task-list">
                    <?php if (count($tasks) > 0): ?>
                        <?php foreach($tasks as $task): ?>
                            <div class="task-item <?php echo $task['status']; ?>">
                                <div class="checkbox-wrapper">
                                    <input type="checkbox" <?php echo $task['status'] === 'completed' ? 'checked' : ''; ?> 
                                           onchange="window.location.href='?toggle_status=<?php echo $task['id']; ?>&new_status=' + (this.checked ? 'completed' : 'pending') + '&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>'">
                                    <span class="checkmark"></span>
                                </div>
                                <div style="flex: 1;">
                                    <div class="task-title" style="font-weight: 700; font-size: 1.15rem; color: #000;">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #222; font-weight: 600; margin-top: 4px;">
                                        📅 <?php echo date('M d, H:i', strtotime($task['deadline'])); ?> • 🏷️ <?php echo $task['category']; ?>
                                    </div>
                                </div>
                                <a href="?delete=<?php echo $task['id']; ?>&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" style="color: var(--danger); text-decoration: none; font-size: 1.3rem; font-weight: 900; margin-left: 15px;" onclick="return confirm('Remove task?')">✕</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 60px; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 20px;">🧘</div>
                            <p style="font-size: 1.1rem; font-weight: 500;">No tasks found in this view.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="taskModal">
        <div class="glass-card" style="width: 450px;">
            <h3 style="margin-bottom: 20px;">Add New Task</h3>
            <form action="tasks.php?status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Title <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="title" id="task-title" class="form-input" required placeholder="What needs to be done?">
                </div>

                <div class="form-group">
                    <label>Category <span style="color: #ef4444;">*</span></label>
                    <select name="category" id="task-category" class="form-input" required>
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Health">Health</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Deadline <span style="color: #ef4444;">*</span></label>
                    <input type="datetime-local" name="deadline" id="task-deadline" class="form-input" required>
                </div>

                <div class="form-group">
                    <label>Description (Optional)</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Additional details..."></textarea>
                </div>

                <div style="font-size: 0.85rem; color: #666; margin-top: 15px; margin-bottom: 10px;">
                    <span style="color: #ef4444;">*</span> These fields are compulsory
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="color: #000 !important; font-weight: 800;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
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

        // Form Validation
        const titleInput = document.querySelector('#task-title');
        const deadlineInput = document.querySelector('#task-deadline');
        const categoryInput = document.querySelector('#task-category');
        
        if (titleInput) {
            titleInput.addEventListener('blur', function() {
                validateField(this);
            });
            titleInput.addEventListener('input', function() {
                if(this.value.trim() !== '') {
                    clearError(this);
                }
            });
        }

        if (deadlineInput) {
            deadlineInput.addEventListener('blur', function() {
                validateField(this);
            });
            deadlineInput.addEventListener('change', function() {
                if(this.value !== '') {
                    clearError(this);
                }
            });
        }

        if (categoryInput) {
            categoryInput.addEventListener('blur', function() {
                validateField(this);
            });
            categoryInput.addEventListener('change', function() {
                if(this.value !== '') {
                    clearError(this);
                }
            });
        }

        function validateField(field) {
            const existingError = field.parentNode.querySelector('.error-message');
            
            if (field.value.trim() === '') {
                if (!existingError) {
                    const error = document.createElement('div');
                    error.className = 'error-message';
                    error.style.color = '#ef4444';
                    error.style.fontSize = '0.85rem';
                    error.style.marginTop = '5px';
                    error.style.fontWeight = '600';
                    error.innerText = 'This field is compulsory';
                    field.parentNode.appendChild(error);
                    field.style.borderColor = '#ef4444';
                }
            } else {
                clearError(field);
            }
        }

        function clearError(field) {
            const existingError = field.parentNode.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
                field.style.borderColor = '';
            }
        }
    </script>
</body>
</html>
