<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$page = 'modules';

// Fetch User Task Stats
$sql = "SELECT 
            u.id, 
            u.username, 
            u.email,
            (SELECT COUNT(*) FROM tasks t WHERE t.user_id = u.id) as total_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.user_id = u.id AND t.status = 'completed') as completed_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.user_id = u.id AND t.status = 'pending') as pending_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.user_id = u.id AND t.priority = 'high') as high_priority,
            (SELECT MAX(created_at) FROM tasks t WHERE t.user_id = u.id) as last_activity
        FROM users u 
        WHERE u.role != 'admin'
        ORDER BY total_tasks DESC";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();

// View Details
$details_user = null;
if (isset($_GET['view_user'])) {
    $uid = $_GET['view_user'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $details_user = $stmt->fetch();
    
    if ($details_user) {
        // Task completion rate
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
            FROM tasks WHERE user_id = ?");
        $stmt->execute([$uid]);
        $task_stats = $stmt->fetch();
        
        // Priority breakdown
        $stmt = $pdo->prepare("SELECT priority, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY priority");
        $stmt->execute([$uid]);
        $priority_stats = $stmt->fetchAll();
        
        // Weekly activity (last 4 weeks)
        $stmt = $pdo->prepare("SELECT 
            YEARWEEK(created_at) as week, COUNT(*) as count
            FROM tasks WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
            GROUP BY YEARWEEK(created_at) ORDER BY week DESC");
        $stmt->execute([$uid]);
        $weekly_activity = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks Users - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stats-table th, .stats-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .stats-table th { background: #f8f9fa; font-weight: 600; color: #444; }
        .stats-table tr:hover { background: #f1f5f9; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-yellow { background: #fef3c7; color: #b45309; }
        .badge-red { background: #fee2e2; color: #dc2626; }
        .badge-blue { background: #dbeafe; color: #2563eb; }
        .details-panel { position: fixed; top: 0; right: 0; width: 480px; height: 100%; background: white; box-shadow: -5px 0 20px rgba(0,0,0,0.1); padding: 30px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; z-index: 1000; }
        .details-panel.open { transform: translateX(0); }
        .close-btn { float: right; cursor: pointer; font-size: 1.5rem; color: #666; text-decoration: none; }
        .stat-card { background: #f9fafb; padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 15px; }
        .stat-card .value { font-size: 2rem; font-weight: 800; }
        .stat-card .label { font-size: 0.85rem; color: #666; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div>
                    <a href="modules.php" style="text-decoration: none; color: #666;">&larr; Back to Modules</a>
                    <h1 style="margin-top: 5px;">✅ Tasks Module Users</h1>
                    <p>Track user task management activity.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total Tasks</th>
                            <th>Completed</th>
                            <th>Pending</th>
                            <th>High Priority</th>
                            <th>Last Activity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <?php $rate = $u['total_tasks'] > 0 ? round(($u['completed_tasks'] / $u['total_tasks']) * 100) : 0; ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong><br>
                                <span style="font-size: 0.8em; color: #888;"><?php echo htmlspecialchars($u['email']); ?></span>
                            </td>
                            <td><span class="badge badge-blue"><?php echo $u['total_tasks']; ?></span></td>
                            <td><span class="badge badge-green"><?php echo $u['completed_tasks']; ?></span></td>
                            <td><span class="badge badge-yellow"><?php echo $u['pending_tasks']; ?></span></td>
                            <td><span class="badge badge-red"><?php echo $u['high_priority']; ?></span></td>
                            <td style="font-size: 0.85rem; color: #666;"><?php echo $u['last_activity'] ? date('M d', strtotime($u['last_activity'])) : '-'; ?></td>
                            <td>
                                <a href="?view_user=<?php echo $u['id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($details_user): ?>
            <div class="details-panel open">
                <a href="tasks_users.php" class="close-btn">&times;</a>
                <h2>📊 <?php echo htmlspecialchars($details_user['username']); ?>'s Tasks</h2>
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                
                <!-- Completion Rate -->
                <div class="stat-card" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                    <div class="value" style="color: #16a34a;">
                        <?php echo $task_stats['total'] > 0 ? round(($task_stats['completed'] / $task_stats['total']) * 100) : 0; ?>%
                    </div>
                    <div class="label">Completion Rate</div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="stat-card">
                        <div class="value" style="color: #2563eb;"><?php echo $task_stats['total']; ?></div>
                        <div class="label">Total Tasks</div>
                    </div>
                    <div class="stat-card">
                        <div class="value" style="color: #16a34a;"><?php echo $task_stats['completed']; ?></div>
                        <div class="label">Completed</div>
                    </div>
                </div>
                
                <h3 style="margin-top: 20px;">📌 Priority Breakdown</h3>
                <div style="background: #f9fafb; padding: 15px; border-radius: 10px;">
                    <?php foreach ($priority_stats as $p): ?>
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                        <span style="text-transform: capitalize; font-weight: 600;"><?php echo $p['priority']; ?></span>
                        <span class="badge badge-<?php echo $p['priority'] == 'high' ? 'red' : ($p['priority'] == 'medium' ? 'yellow' : 'blue'); ?>">
                            <?php echo $p['count']; ?> tasks
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <h3 style="margin-top: 20px;">📅 Weekly Activity</h3>
                <div style="background: #f9fafb; padding: 15px; border-radius: 10px;">
                    <?php if (empty($weekly_activity)): ?>
                        <p style="color: #999; text-align: center;">No recent activity</p>
                    <?php else: ?>
                        <?php foreach ($weekly_activity as $w): ?>
                        <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                            <span>Week <?php echo substr($w['week'], -2); ?></span>
                            <span class="badge badge-blue"><?php echo $w['count']; ?> tasks created</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
