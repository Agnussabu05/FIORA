<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'tasks';

// Filter Params
$filter_status = $_GET['status'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Handle AJAX Search Suggestions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_suggestions') {
    $query = $_POST['query'] ?? '';
    $status = $_POST['status'] ?? 'all';
    $category = $_POST['category'] ?? 'all';
    
    $sql = "SELECT id, title, category, status, deadline FROM tasks WHERE user_id = ? AND title LIKE ?";
    $params = [$user_id, "%$query%"];
    
    if ($status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    if ($category !== 'all') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Handle Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $deadline = $_POST['deadline'];
    $category = $_POST['category'] ?? 'Personal';
    $priority = $_POST['priority'] ?? 'medium';
    
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, deadline, priority, category, status, estimated_time, recurrent_type, user_order) VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, 'none', 0)");
    $stmt->execute([$user_id, $title, $desc, $deadline, $priority, $category]);
    
    $_SESSION['task_msg'] = "Task added! 🎯 Another step toward your goals. You've got this!";
    
    header("Location: tasks.php?status=$filter_status&category=$filter_category");
    exit;
}

// Handle Update Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = $_POST['task_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $deadline = $_POST['deadline'];
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    
    // Verify ownership before updating
    $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, deadline = ?, category = ?, priority = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$title, $desc, $deadline, $category, $priority, $id, $user_id]);
    
    $_SESSION['task_msg'] = "Task updated successfully! 📝";
    
    header("Location: tasks.php?status=$filter_status&category=$filter_category");
    exit;
}

// Handle Complete/Uncomplete Toggle
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $new_status = $_GET['new_status']; // 'completed' or 'pending'
    $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?")->execute([$new_status, $id, $user_id]);
    
    if ($new_status === 'completed') {
        $_SESSION['task_msg'] = "Excellent! 🎉 Task completed. Progress feels amazing, doesn't it?";
    }
    
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

$current_time = date('Y-m-d H:i:s');

if ($filter_status === 'overdue') {
    $query .= " AND status = 'pending' AND deadline < ?";
    $params[] = $current_time;
} elseif ($filter_status === 'upcoming') {
    $query .= " AND status = 'pending' AND deadline >= ? AND deadline <= DATE_ADD(?, INTERVAL 7 DAY)";
    $params[] = $current_time;
    $params[] = $current_time;
} elseif ($filter_status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter_status;
}
if ($filter_category !== 'all') {
    $query .= " AND category = ?";
    $params[] = $filter_category;
}
if (!empty($search_query)) {
    $query .= " AND title LIKE ?";
    $params[] = "%$search_query%";
}

$stmt = $pdo->prepare("$query ORDER BY created_at DESC");
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch as associative array for sorting

// Handle Smart Sort
$smart_sort_active = isset($_GET['sort']) && $_GET['sort'] === 'smart';
if ($smart_sort_active) {
    // Calculate scores
    foreach ($tasks as &$t) {
        $score = 0;
        $deadline = strtotime($t['deadline']);
        $now = time();
        $diff_hours = ($deadline - $now) / 3600;

        // 1. Urgency
        if ($diff_hours < 0) $score += 2000;      // Overdue
        elseif ($diff_hours <= 24) $score += 800; // Today
        elseif ($diff_hours <= 72) $score += 400; // 3 Days
        elseif ($diff_hours <= 168) $score += 200;// Week

        // 2. Priority
        $prio = $t['priority'] ?? 'medium';
        if ($prio === 'high') $score += 600;
        elseif ($prio === 'medium') $score += 300;
        elseif ($prio === 'low') $score += 100;

        // 3. Completeness (push completed to bottom)
        if ($t['status'] === 'completed') $score -= 5000;

        $t['smart_score'] = $score;
    }
    unset($t);

    // Sort by score DESC
    usort($tasks, function($a, $b) {
        return $b['smart_score'] <=> $a['smart_score'];
    });
}

// Fetch Overdue Tasks (pending tasks past deadline)
$overdue_query = "SELECT * FROM tasks WHERE user_id = ? AND status = 'pending' AND deadline < NOW() ORDER BY deadline ASC";
$overdue_stmt = $pdo->prepare($overdue_query);
$overdue_stmt->execute([$user_id]);
$overdue_tasks = $overdue_stmt->fetchAll();

// Fetch Upcoming Tasks (pending tasks due in next 7 days)
$upcoming_query = "SELECT * FROM tasks WHERE user_id = ? AND status = 'pending' AND deadline >= NOW() AND deadline <= DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY deadline ASC";
$upcoming_stmt = $pdo->prepare($upcoming_query);
$upcoming_stmt->execute([$user_id]);
$upcoming_tasks = $upcoming_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Tasks</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
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
        
        /* Custom Premium Checkbox Link Style */
        .checkbox-btn {
            position: relative;
            width: 28px;
            height: 28px;
            margin-right: 15px;
            flex-shrink: 0;
            cursor: pointer;
            display: block; /* Important for anchor */
        }
        .checkbox-mark {
            position: absolute; top: 0; left: 0; height: 28px; width: 28px;
            background-color: white; border: 2px solid var(--primary);
            border-radius: 8px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .checkbox-btn:hover .checkbox-mark { background-color: #f0f0f0; transform: scale(1.1); }
        
        /* Checked State */
        .checkbox-btn.checked .checkbox-mark { background-color: var(--primary); transform: scale(1.05); }
        .checkbox-mark:after {
            content: ""; position: absolute; display: none;
            left: 9px; top: 4px; width: 6px; height: 12px;
            border: solid white; border-width: 0 3px 3px 0; transform: rotate(45deg);
        }
        .checkbox-btn.checked .checkbox-mark:after { display: block; }

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

        /* Main Content Layout */
        .tasks-main-col {
            flex: 1;
            min-width: 0;
            padding-right: 20px;
        }

        /* Urgent Toggle Buttons */
        .urgent-toggle-btn {
            padding: 12px 24px;
            border-radius: 15px;
            border: 2px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            font-weight: 800;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
        }
        .urgent-toggle-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            background: white;
        }
        .urgent-toggle-btn.overdue { color: #dc2626; border-color: rgba(239, 68, 68, 0.2); }
        .urgent-toggle-btn.upcoming { color: #059669; border-color: rgba(16, 185, 129, 0.2); }
        .urgent-toggle-btn.active {
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="color: #000 !important; margin: 0;">Tasks & Deadlines</h1>
                    <p style="color: #4b5563; font-weight: 600; margin: 5px 0 0 0;">Organize your life elegantly.</p>
                </div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <!-- Notification Bell -->
                    <?php
                        // Fetch Notifications (Mocking/Simulating Overdue Alerts)
                        $notif_count = 0;
                        $notifs = [];
                        
                        // Check overdue tasks for notifications
                        foreach ($tasks as $t) { /* Use fetched tasks */
                            if ($t['status'] === 'pending' && strtotime($t['deadline']) < time()) {
                                $notifs[] = ['type' => 'overdue', 'message' => 'Task "' . $t['title'] . '" is overdue!', 'time' => $t['deadline']];
                            }
                        }
                        $notif_count = count($notifs);
                    ?>
                    <div style="position: relative;">
                        <button onclick="openNotificationModal()" style="background: white; border: 1px solid var(--glass-border); width: 45px; height: 45px; border-radius: 12px; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; position: relative;">
                            🔔
                            <?php if ($notif_count > 0): ?>
                                <span style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.7rem; font-weight: 800; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #f3f4f6;"><?php echo $notif_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <button class="btn btn-primary" onclick="openModal()" style="padding: 12px 25px; font-size: 1rem; border-radius: 12px; font-weight: 800;">+ New Task</button>
                </div>
            </header>

            <?php if (isset($_SESSION['task_msg'])): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: #065f46; padding: 15px 20px; border-radius: 15px; border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; animation: fadeInUp 0.5s ease-out;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 1.2rem;">✨</span>
                        <span style="font-weight: 600;"><?php echo $_SESSION['task_msg']; unset($_SESSION['task_msg']); ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 1.1rem; opacity: 0.6;">✕</button>
                </div>
            <?php endif; ?>

            <!-- Smart Recommendation Engine -->
            <?php
            // Logic: Weighted Scoring System (PHP) to balance Priority vs Urgency
            // Fetch all pending tasks to analyze
            $recStmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? AND status = 'pending'");
            $recStmt->execute([$user_id]);
            $pending_tasks = $recStmt->fetchAll(PDO::FETCH_ASSOC);

            $recommended_task = null;
            $best_score = -1;
            
            foreach ($pending_tasks as $t) {
                $score = 0;
                $deadline = strtotime($t['deadline']);
                $now = time();
                $diff_hours = ($deadline - $now) / 3600;

                // 1. Urgency Score
                if ($diff_hours < 0) {
                    $score += 2000; // Overdue is critical
                } elseif ($diff_hours <= 24) {
                    $score += 800; // Due today
                } elseif ($diff_hours <= 72) {
                    $score += 400; // Due in 3 days
                } elseif ($diff_hours <= 168) {
                    $score += 200; // Due within week
                }

                // 2. Priority Score
                $prio = $t['priority'] ?? 'medium';
                if ($prio === 'high') $score += 600;
                elseif ($prio === 'medium') $score += 300;
                elseif ($prio === 'low') $score += 100;

                // 3. Tie-breaker: Closer deadline gets slight boost (inverse of hours left)
                // Avoid division by zero
                if ($diff_hours > 0) {
                    $score += (1000 / ($diff_hours + 1)); 
                }

                if ($score > $best_score) {
                    $best_score = $score;
                    $recommended_task = $t;
                }
            }
            ?>

            <?php if ($recommended_task):
                // Reason Logic
                $rec_deadline = new DateTime($recommended_task['deadline']);
                $rec_now = new DateTime();
                $rec_diff = $rec_now->diff($rec_deadline);
                $is_overdue = $rec_now > $rec_deadline;
                
                $reason = "Smart Recommendation";
                $icon = "⚡";
                $bg_style = "background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);"; // Indigo/Purple gradient
                
                if ($is_overdue) {
                    $reason = "Overdue - Action Required!";
                    $icon = "⚠️";
                    $bg_style = "background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);"; // Red
                } elseif ($rec_diff->days < 1 && $recommended_task['priority'] === 'high') {
                    $reason = "🔥 High Priority & Due Today!";
                    $icon = "🚨";
                    $bg_style = "background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);"; // Orange/Red
                } elseif ($recommended_task['priority'] === 'high') {
                    $reason = "High Priority Focus";
                    $icon = "🔥";
                    $bg_style = "background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);"; // Amber
                } elseif ($rec_diff->days < 1) {
                     $reason = "Due Today - Stay on Track";
                     $icon = "📅";
                     $bg_style = "background: linear-gradient(135deg, #10b981 0%, #059669 100%);"; // Green
                }
            ?>
            <div style="<?php echo $bg_style; ?> border-radius: 20px; padding: 2px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); position: relative; overflow: hidden; animation: slideDown 0.5s ease-out;">
                <div style="background: rgba(255, 255, 255, 0.95); border-radius: 18px; padding: 25px; position: relative; z-index: 2;">
                    <div style="position: absolute; top: 0; right: 0; padding: 15px 20px; background: rgba(0,0,0,0.05); border-bottom-left-radius: 20px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #555;">
                        AI Recommended
                    </div>
                    
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        <div style="background: rgba(0,0,0,0.03); width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0;">
                            <?php echo $icon; ?>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 5px 0; color: #666; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo $reason; ?></h4>
                            <h2 style="margin: 0 0 10px 0; color: #111; font-size: 1.5rem; font-weight: 800; line-height: 1.3;">
                                <?php echo htmlspecialchars($recommended_task['title']); ?>
                            </h2>
                            <div style="display: flex; align-items: center; gap: 15px; font-size: 0.9rem; color: #444; font-weight: 500;">
                                <span style="display: flex; align-items: center; gap: 6px;">
                                    ClockIcon <?php echo date('M d, g:i A', strtotime($recommended_task['deadline'])); ?>
                                </span>
                                <span style="display: flex; align-items: center; gap: 6px;">
                                    TagIcon <?php echo htmlspecialchars($recommended_task['category']); ?>
                                </span>
                            </div>
                        </div>
                        <div style="align-self: center; display: flex; flex-direction: column; gap: 10px;">
                            <a href="?toggle_status=<?php echo $recommended_task['id']; ?>&new_status=completed&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" 
                               style="background: var(--primary); color: white; padding: 12px 24px; border-radius: 12px; text-decoration: none; font-weight: 700; text-align: center; box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3); transition: transform 0.2s;">
                                Complete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="tasks-layout">
                <div class="tasks-main-col">
                    <div class="filter-section">
                <!-- Search Bar -->
                <div style="margin-bottom: 20px;">
                    <form method="GET" action="tasks.php" id="searchForm" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="status" value="<?php echo $filter_status; ?>">
                        <input type="hidden" name="category" value="<?php echo $filter_category; ?>">
                        <div style="flex: 1; position: relative;">
                            <input 
                                type="text" 
                                name="search" 
                                id="searchInput"
                                value="<?php echo htmlspecialchars($search_query); ?>" 
                                placeholder="🔍 Search tasks by name..." 
                                autocomplete="off"
                                style="width: 100%; padding: 12px 45px 12px 15px; border: 2px solid var(--glass-border); border-radius: 12px; font-size: 0.95rem; font-weight: 600; background: rgba(255,255,255,0.6); transition: all 0.3s;"
                                onfocus="this.style.borderColor='var(--primary)'; this.style.background='white';"
                                onblur="setTimeout(() => { this.style.borderColor='var(--glass-border)'; this.style.background='rgba(255,255,255,0.6)'; }, 200);">
                            <?php if (!empty($search_query)): ?>
                                <a href="tasks.php?status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" 
                                   style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666; text-decoration: none; font-size: 1.2rem; font-weight: bold; cursor: pointer; z-index: 10;"
                                   title="Clear search">✕</a>
                            <?php endif; ?>
                            
                            <!-- Live Search Suggestions Dropdown -->
                            <div id="searchSuggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 2px solid var(--primary); border-top: none; border-radius: 0 0 12px 12px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 8px 16px rgba(0,0,0,0.1); margin-top: -2px;">
                                <!-- Suggestions will be populated here -->
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 12px 24px; white-space: nowrap;">Search</button>
                    </form>
                    <?php if (!empty($search_query)): ?>
                        <div style="margin-top: 10px; font-size: 0.9rem; color: #666; font-weight: 600;">
                            🔍 Searching for: "<span style="color: var(--primary); font-weight: 800;"><?php echo htmlspecialchars($search_query); ?></span>"
                            <span style="margin-left: 10px; color: #888;">(<?php echo count($tasks); ?> result<?php echo count($tasks) !== 1 ? 's' : ''; ?>)</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Status Tabs -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <!-- Left: Standard Filters -->
                    <div class="tab-group" style="background: rgba(255,255,255,0.6); padding: 5px; border-radius: 15px; display: inline-flex; gap: 5px; flex-wrap: wrap; border: 1px solid var(--glass-border);">
                        <?php 
                        $main_statuses = [
                            'all' => ['label' => '📋 All', 'color' => 'var(--primary)'],
                            'pending' => ['label' => '⏳ Pending', 'color' => '#f59e0b'], 
                            'completed' => ['label' => '✅ Completed', 'color' => '#10b981']
                        ];
                        
                        foreach($main_statuses as $val => $style): 
                            $isActive = $filter_status == $val;
                            $btnBg = $isActive ? $style['color'] : 'transparent';
                            $btnColor = $isActive ? 'white' : '#555';
                            $shadow = $isActive ? '0 4px 12px rgba(0,0,0,0.15)' : 'none';
                        ?>
                            <button onclick="window.location.href='tasks.php?status=<?php echo $val; ?>&category=<?php echo $filter_category; ?>&search=<?php echo urlencode($search_query); ?>'" 
                                    class="filter-pill <?php echo $isActive ? 'active' : ''; ?>"
                                    style="padding: 10px 18px; border-radius: 12px; border: none; cursor: pointer; font-weight: 700; transition: all 0.3s; background: <?php echo $btnBg; ?>; color: <?php echo $btnColor; ?>; box-shadow: <?php echo $shadow; ?>;">
                                <?php echo $style['label']; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>


                </div>

                <!-- Category Pills -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <span style="font-weight: 800; font-size: 0.95rem; color: #000; margin-right: 5px;">Filter Category:</span>
                        <?php 
                        $categories = ['all' => '🏷️ All', 'Personal' => '👤 Personal', 'Work' => '💼 Work', 'Health' => '🏥 Health'];
                        foreach($categories as $val => $label): ?>
                            <button onclick="window.location.href='tasks.php?category=<?php echo $val; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_query); ?>'" 
                                    class="category-filter-pill <?php echo $filter_category == $val ? 'active' : ''; ?>"
                                    style="padding: 10px 20px; border-radius: 20px; border: 2px solid var(--glass-border); cursor: pointer; font-weight: 800; transition: all 0.3s; <?php echo $filter_category == $val ? 'background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 10px rgba(0,0,0,0.2);' : 'background: white; color: var(--primary);'; ?>">
                                <?php echo $label; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Right: Smart Sort -->
                    <button onclick="window.location.href='tasks.php?status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>&search=<?php echo urlencode($search_query); ?>&sort=<?php echo $smart_sort_active ? 'default' : 'smart'; ?>'" 
                            style="padding: 10px 20px; border-radius: 15px; border: none; cursor: pointer; font-weight: 800; transition: all 0.3s; display: flex; align-items: center; gap: 8px; <?php echo $smart_sort_active ? 'background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);' : 'background: white; color: 666; border: 1px solid var(--glass-border);'; ?>">
                        <span>✨</span> Smart Sort <?php echo $smart_sort_active ? 'On' : 'Off'; ?>
                    </button>
                </div>
            </div>

            <div class="glass-card">
                <div class="task-list">
                    <?php if (count($tasks) > 0): ?>
                        <?php foreach($tasks as $task): ?>
                            <div class="task-item <?php echo $task['status']; ?>">
                                <?php 
                                    $next_status = $task['status'] === 'completed' ? 'pending' : 'completed';
                                    $is_checked = $task['status'] === 'completed' ? 'checked' : '';
                                ?>
                                <a href="?toggle_status=<?php echo $task['id']; ?>&new_status=<?php echo $next_status; ?>&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>&search=<?php echo urlencode($search_query); ?>" 
                                   class="checkbox-btn <?php echo $is_checked; ?>" title="Mark as <?php echo ucfirst($next_status); ?>">
                                    <span class="checkbox-mark"></span>
                                </a>
                                <div style="flex: 1;">
                                    <div class="task-title" style="font-weight: 700; font-size: 1.15rem; color: #000;">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #222; font-weight: 600; margin-top: 4px; display: flex; align-items: center; gap: 10px;">
                                        <span>📅 <?php echo date('M d, H:i', strtotime($task['deadline'])); ?></span>
                                        <span>🏷️ <?php echo htmlspecialchars($task['category'] ?? 'Uncategorized'); ?></span>
                                        
                                        <?php 
                                        $prio = $task['priority'] ?? 'medium';
                                        $prioIcon = $prio === 'high' ? '🔥' : ($prio === 'low' ? '☁️' : '🔹');
                                        if($prio === 'high'): ?>
                                            <span style="color: #dc2626;" title="High Priority"><?php echo $prioIcon; ?> High</span>
                                        <?php elseif($prio === 'low'): ?>
                                            <span style="color: #64748b;" title="Low Priority"><?php echo $prioIcon; ?> Low</span>
                                        <?php else: ?>
                                            <span style="color: #3b82f6;" title="Medium Priority"><?php echo $prioIcon; ?> Med</span>
                                        <?php endif; ?>

                                        <?php if ($task['status'] === 'pending'): ?>
                                            <?php if (strtotime($task['deadline']) < time()): ?>
                                                <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;">⚠️ Overdue</span>
                                            <?php else: ?>
                                                <span style="background: #fee2e2; color: #ef4444; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;">⏳ Pending</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;">✅ Completed</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button onclick='openEditModal(<?php echo json_encode($task, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="background: none; border: none; font-size: 1.2rem; cursor: pointer; margin-left: 10px; margin-right: 5px;">✏️</button>
                                <a href="?delete=<?php echo $task['id']; ?>&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" style="color: var(--danger); text-decoration: none; font-size: 1.3rem; font-weight: 900; margin-left: 10px;" onclick="return confirm('Remove task?')">✕</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 60px; text-align: center; color: var(--text-muted);">
                            <?php
                            // Contextual empty state messages
                            $emoji = '🧘';
                            $message = 'No tasks found in this view.';
                            
                            if ($filter_status === 'completed') {
                                if ($filter_category === 'all') {
                                    $emoji = '🎉';
                                    $message = 'All caught up! No completed tasks yet. Start checking off your to-dos!';
                                } elseif ($filter_category === 'Personal') {
                                    $emoji = '✨';
                                    $message = 'No personal tasks completed yet. Keep working on your personal goals!';
                                } elseif ($filter_category === 'Work') {
                                    $emoji = '💼';
                                    $message = 'No work tasks completed yet. Keep pushing forward!';
                                } elseif ($filter_category === 'Health') {
                                    $emoji = '🏥';
                                    $message = 'No health tasks completed yet. Your wellness journey starts here!';
                                }
                            } elseif ($filter_status === 'pending') {
                                if ($filter_category === 'all') {
                                    $emoji = '✅';
                                    $message = 'All clear! No pending tasks. You\'re on top of things!';
                                } elseif ($filter_category === 'Personal') {
                                    $emoji = '✨';
                                    $message = 'No personal tasks pending. Time to plan your personal goals!';
                                } elseif ($filter_category === 'Work') {
                                    $emoji = '💼';
                                    $message = 'No work tasks pending. Ready to tackle new challenges?';
                                } elseif ($filter_category === 'Health') {
                                    $emoji = '🏥';
                                    $message = 'No health tasks pending. Consider adding wellness activities!';
                                }
                            } else { // all
                                if ($filter_category === 'Personal') {
                                    $emoji = '✨';
                                    $message = 'No personal tasks yet. Start organizing your personal life!';
                                } elseif ($filter_category === 'Work') {
                                    $emoji = '💼';
                                    $message = 'No work tasks yet. Ready to plan your workday?';
                                } elseif ($filter_category === 'Health') {
                                    $emoji = '🏥';
                                    $message = 'No health tasks yet. Your wellness journey begins now!';
                                } else {
                                    $emoji = '🌟';
                                    $message = 'Your task list is empty. Start by adding your first task!';
                                }
                            }
                            ?>
                            <div style="font-size: 3rem; margin-bottom: 20px;"><?php echo $emoji; ?></div>
                            <p style="font-size: 1.1rem; font-weight: 500;"><?php echo $message; ?></p>
                        </div>
                    <?php endif; ?>
            </div> <!-- Closes glass-card -->

        </div> <!-- Closes tasks-main-col -->
    </div> <!-- Closes tasks-layout -->
</main> <!-- Closes main-content -->
</div> <!-- Closes app-container -->

    <!-- Add Task Modal -->
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
                    <label>Priority <span style="color: #ef4444;">*</span></label>
                    <select name="priority" id="task-priority" class="form-input" required>
                        <option value="medium">🔹 Medium</option>
                        <option value="high">🔥 High</option>
                        <option value="low">☁️ Low</option>
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

    <!-- Edit Task Modal -->
    <div class="modal" id="editTaskModal">
        <div class="glass-card" style="width: 450px;">
            <h3 style="margin-bottom: 20px;">Edit Task</h3>
            <form action="tasks.php?status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="task_id" id="edit-task-id">
                
                <div class="form-group">
                    <label>Title <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="title" id="edit-task-title" class="form-input" required>
                </div>

                <div class="form-group">
                    <label>Category <span style="color: #ef4444;">*</span></label>
                    <select name="category" id="edit-task-category" class="form-input" required>
                        <option value="Personal">Personal</option>
                        <option value="Work">Work</option>
                        <option value="Health">Health</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Priority <span style="color: #ef4444;">*</span></label>
                    <select name="priority" id="edit-task-priority" class="form-input" required>
                        <option value="medium">🔹 Medium</option>
                        <option value="high">🔥 High</option>
                        <option value="low">☁️ Low</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Deadline <span style="color: #ef4444;">*</span></label>
                    <input type="datetime-local" name="deadline" id="edit-task-deadline" class="form-input" required>
                </div>

                <div class="form-group">
                    <label>Description (Optional)</label>
                    <textarea name="description" id="edit-task-desc" class="form-input" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="color: #000 !important; font-weight: 800;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Live Search Functionality
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        let searchTimeout;

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                if (query.length === 0) {
                    searchSuggestions.style.display = 'none';
                    return;
                }
                
                // Debounce search - wait 300ms after user stops typing
                searchTimeout = setTimeout(() => {
                    fetchSearchSuggestions(query);
                }, 300);
            });
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                    searchSuggestions.style.display = 'none';
                }
            });
        }

        function fetchSearchSuggestions(query) {
            // Create form data
            const formData = new FormData();
            formData.append('action', 'search_suggestions');
            formData.append('query', query);
            formData.append('status', '<?php echo $filter_status; ?>');
            formData.append('category', '<?php echo $filter_category; ?>');

            fetch('tasks.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                displaySuggestions(data, query);
            })
            .catch(error => {
                console.error('Search error:', error);
            });
        }

        function displaySuggestions(tasks, query) {
            if (tasks.length === 0) {
                searchSuggestions.innerHTML = '<div style="padding: 15px; text-align: center; color: #888; font-size: 0.9rem;">No matching tasks found</div>';
                searchSuggestions.style.display = 'block';
                return;
            }

            let html = '';
            tasks.forEach(task => {
                // Highlight matching text
                const titleHighlighted = highlightMatch(task.title, query);
                const categoryIcon = getCategoryIcon(task.category);
                const statusIcon = task.status === 'completed' ? '✅' : '⏳';
                
                html += `
                    <div class="suggestion-item" 
                         onclick="selectSuggestion('${escapeHtml(task.title)}')"
                         style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s;"
                         onmouseover="this.style.background='#f8f8f8'"
                         onmouseout="this.style.background='white'">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 1.1rem;">${statusIcon}</span>
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: #000; font-size: 0.95rem;">${titleHighlighted}</div>
                                <div style="font-size: 0.8rem; color: #666; margin-top: 2px;">
                                    ${categoryIcon} ${task.category} • ${formatDate(task.deadline)}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            searchSuggestions.innerHTML = html;
            searchSuggestions.style.display = 'block';
        }

        function selectSuggestion(title) {
            searchInput.value = title;
            searchSuggestions.style.display = 'none';
            document.getElementById('searchForm').submit();
        }

        function highlightMatch(text, query) {
            const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
            return escapeHtml(text).replace(regex, '<span style="background: #fff3cd; font-weight: 800;">$1</span>');
        }

        function getCategoryIcon(category) {
            const icons = {
                'Personal': '👤',
                'Work': '💼',
                'Health': '🏥'
            };
            return icons[category] || '🏷️';
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const month = date.toLocaleString('default', { month: 'short' });
            const day = date.getDate();
            return `${month} ${day}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function escapeRegex(text) {
            return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

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
        
        // Track field order for sequential validation
        const requiredFields = [titleInput, categoryInput, deadlineInput];
        
        if (titleInput) {
            // Validate on blur (leaving field)
            titleInput.addEventListener('blur', function() {
                validateField(this);
            });
            
            // Clear error on input
            titleInput.addEventListener('input', function() {
                if(this.value.trim() !== '') {
                    clearError(this);
                }
            });
        }

        if (categoryInput) {
            // When focusing on category, validate title
            categoryInput.addEventListener('focus', function() {
                if (titleInput && titleInput.value.trim() === '') {
                    validateField(titleInput);
                }
            });
            
            categoryInput.addEventListener('blur', function() {
                validateField(this);
            });
            
            categoryInput.addEventListener('change', function() {
                if(this.value !== '') {
                    clearError(this);
                }
            });
        }

        if (deadlineInput) {
            // When focusing on deadline, validate previous fields
            deadlineInput.addEventListener('focus', function() {
                if (titleInput && titleInput.value.trim() === '') {
                    validateField(titleInput);
                }
                if (categoryInput && categoryInput.value === '') {
                    validateField(categoryInput);
                }
            });
            
            deadlineInput.addEventListener('blur', function() {
                validateField(this);
            });
            
            deadlineInput.addEventListener('change', function() {
                if(this.value !== '') {
                    clearError(this);
                }
            });
        }
        
        // Also validate when focusing on description (optional field)
        const descriptionInput = document.querySelector('textarea[name="description"]');
        if (descriptionInput) {
            descriptionInput.addEventListener('focus', function() {
                // Validate all required fields when user moves to optional field
                requiredFields.forEach(field => {
                    if (field) {
                        if (field.type === 'text' && field.value.trim() === '') {
                            validateField(field);
                        } else if ((field.tagName === 'SELECT' || field.type === 'datetime-local') && field.value === '') {
                            validateField(field);
                        }
                    }
                });
            });
        }

        function validateField(field) {
            const existingError = field.parentNode.querySelector('.error-message');
            
            let isEmpty = false;
            if (field.type === 'text') {
                isEmpty = field.value.trim() === '';
            } else {
                isEmpty = field.value === '';
            }
            
            if (isEmpty) {
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

        // Urgent Tasks Modal Functions
        function openUrgentModal(type) {
            const modal = document.getElementById('urgentModal');
            const overdue = document.getElementById('modal-overdue-content');
            const upcoming = document.getElementById('modal-upcoming-content');
            
            if (type === 'overdue') {
                overdue.style.display = 'block';
                upcoming.style.display = 'none';
            } else {
                overdue.style.display = 'none';
                upcoming.style.display = 'block';
            }
            
            modal.classList.add('active');
        }

        function closeUrgentModal() {
            document.getElementById('urgentModal').classList.remove('active');
        }

        // Edit Modal Functions
        function openEditModal(task) {
            document.getElementById('edit-task-id').value = task.id || '';
            document.getElementById('edit-task-title').value = task.title || '';
            document.getElementById('edit-task-category').value = task.category || 'Personal';
            document.getElementById('edit-task-priority').value = task.priority || 'medium';
            
            // Safe deadline handling
            if (task.deadline) {
                document.getElementById('edit-task-deadline').value = task.deadline.replace(' ', 'T'); 
            } else {
                document.getElementById('edit-task-deadline').value = '';
            }
            
            document.getElementById('edit-task-desc').value = task.description || '';
            
            document.getElementById('editTaskModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editTaskModal').classList.remove('active');
        }

        function toggleTaskStatus(taskId, isChecked) {
            const newStatus = isChecked ? 'completed' : 'pending';
            // Use current URL params to maintain filters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('toggle_status', taskId);
            urlParams.set('new_status', newStatus);
            
            window.location.href = 'tasks.php?' + urlParams.toString();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const urgentModal = document.getElementById('urgentModal');
            const taskModal = document.getElementById('taskModal');
            const editModal = document.getElementById('editTaskModal');
            if (event.target == urgentModal) closeUrgentModal();
            if (event.target == taskModal) closeModal();
            if (event.target == editModal) closeEditModal();
        }
    </script>

    <!-- Urgent Tasks Modal -->
    <div class="modal" id="urgentModal">
        <div class="glass-card" style="width: 90%; max-width: 800px; max-height: 85vh; overflow-y: auto; padding: 35px; position: relative;">
            <button onclick="closeUrgentModal()" style="position: absolute; right: 25px; top: 25px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; font-weight: bold;">✕</button>
            
            <div id="modal-overdue-content" style="display: none;">
                <div style="margin-bottom: 25px;">
                    <h2 style="margin: 0; color: #dc2626; font-size: 1.8rem; font-weight: 800; display: flex; align-items: center; gap: 15px;">
                        <span>⚠️</span> Overdue Tasks
                    </h2>
                    <p style="margin: 8px 0 0 0; color: #475569; font-size: 1rem; font-weight: 600;">Immediate attention required for these items.</p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach($overdue_tasks as $task): 
                        $deadline = new DateTime($task['deadline']);
                        $now = new DateTime();
                        $diff = $now->diff($deadline);
                    ?>
                        <div style="background: rgba(255,255,255,0.7); border-radius: 20px; padding: 20px; border: 1px solid var(--glass-border); box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                            <div style="font-weight: 800; font-size: 1.1rem; color: #000; margin-bottom: 12px; line-height: 1.4;">
                                <?php echo htmlspecialchars($task['title']); ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; font-size: 0.85rem; font-weight: 700;">
                                <span style="color: #64748b;">🏷️ <?php echo $task['category']; ?></span>
                                <span style="color: #ef4444; background: #fee2e2; padding: 5px 12px; border-radius: 12px;">⏰ <?php echo $diff->days; ?>d overdue</span>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <a href="?toggle_status=<?php echo $task['id']; ?>&new_status=completed&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" 
                                   style="flex: 1; background: #22c55e; color: white; padding: 12px; border-radius: 12px; text-decoration: none; font-size: 0.9rem; font-weight: 800; text-align: center; transition: all 0.2s;">✓ Done</a>
                                <a href="?delete=<?php echo $task['id']; ?>&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" 
                                   style="background: #fee2e2; color: #dc2626; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px; text-decoration: none; font-weight: 900;" onclick="return confirm('Delete?')">✕</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="modal-upcoming-content" style="display: none;">
                <div style="margin-bottom: 25px;">
                    <h2 style="margin: 0; color: #059669; font-size: 1.8rem; font-weight: 800; display: flex; align-items: center; gap: 15px;">
                        <span>📅</span> Upcoming Tasks
                    </h2>
                    <p style="margin: 8px 0 0 0; color: #475569; font-size: 1rem; font-weight: 600;">Tasks due in the next 7 days.</p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach($upcoming_tasks as $task): 
                        $deadline = new DateTime($task['deadline']);
                        $now = new DateTime();
                        $interval = $now->diff($deadline);
                        $days_left = $interval->days;
                        $time_text = $interval->invert === 0 ? ($days_left > 0 ? $days_left . "d left" : $interval->h . "h left") : "Due soon";
                        $badge_color = $days_left < 2 ? "#d97706" : "#059669";
                        $badge_bg = $days_left < 2 ? "#fef3c7" : "#d1fae5";
                    ?>
                        <div style="background: rgba(255,255,255,0.7); border-radius: 20px; padding: 20px; border: 1px solid var(--glass-border); box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                            <div style="font-weight: 800; font-size: 1.1rem; color: #000; margin-bottom: 12px; line-height: 1.4;">
                                <?php echo htmlspecialchars($task['title']); ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; font-size: 0.85rem; font-weight: 700;">
                                <span style="color: #64748b;">🏷️ <?php echo $task['category']; ?></span>
                                <span style="color: <?php echo $badge_color; ?>; background: <?php echo $badge_bg; ?>; padding: 5px 12px; border-radius: 12px;">⏳ <?php echo $time_text; ?></span>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <a href="?toggle_status=<?php echo $task['id']; ?>&new_status=completed&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" 
                                   style="flex: 1; background: #22c55e; color: white; padding: 12px; border-radius: 12px; text-decoration: none; font-size: 0.9rem; font-weight: 800; text-align: center; transition: all 0.2s;">✓ Done</a>
                                <a href="?delete=<?php echo $task['id']; ?>&status=<?php echo $filter_status; ?>&category=<?php echo $filter_category; ?>" 
                                   style="background: #f8fafc; color: #64748b; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px; text-decoration: none; font-weight: 900;" onclick="return confirm('Delete?')">✕</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <!-- Notification Modal -->
    <div class="modal" id="notificationModal">
        <div class="glass-card" style="width: 450px; max-height: 80vh; display: flex; flex-direction: column; padding: 0;">
            <div style="padding: 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">Notifications</h3>
                <button onclick="closeNotificationModal()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer;">✕</button>
            </div>
            
            <div style="padding: 20px; overflow-y: auto; flex: 1;">
                <?php if (empty($notifs)): ?>
                    <div style="text-align: center; color: #9ca3af; padding: 20px;">
                        <div style="font-size: 2rem; margin-bottom: 10px;">💤</div>
                        No new notifications.
                    </div>
                <?php else: ?>
                    <?php foreach ($notifs as $n): ?>
                        <div style="padding: 15px; background: #fff0f0; border-radius: 12px; margin-bottom: 10px; border-left: 4px solid #ef4444;">
                            <div style="font-weight: 700; color: #7f1d1d; font-size: 0.95rem; margin-bottom: 4px;">⚠️ Overdue Alert</div>
                            <div style="color: #4b5563; font-size: 0.9rem;"><?php echo htmlspecialchars($n['message']); ?></div>
                            <div style="font-size: 0.8rem; color: #9ca3af; margin-top: 5px;"><?php echo date('M d, H:i', strtotime($n['time'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="padding: 15px; border-top: 1px solid #f0f0f0; text-align: center;">
                <button onclick="alert('View All History feature coming soon!')" style="background: none; border: none; color: var(--primary-color); font-weight: 700; cursor: pointer;">View All History</button>
            </div>
        </div>
    </div>

    <script>
        function openNotificationModal() {
            document.getElementById('notificationModal').classList.add('active');
        }
        function closeNotificationModal() {
            document.getElementById('notificationModal').classList.remove('active');
        }
        document.getElementById('notificationModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('notificationModal')) closeNotificationModal();
        });
    </script>
    </div> <!-- Close app-container -->
</body>
</html>
