<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$page = 'modules';

// Fetch User Habit Stats
$sql = "SELECT 
            u.id, 
            u.username, 
            u.email,
            (SELECT COUNT(*) FROM habits h WHERE h.user_id = u.id) as total_habits,
            (SELECT COUNT(DISTINCT DATE(check_in_date)) FROM habit_logs hl JOIN habits h ON hl.habit_id = h.id WHERE h.user_id = u.id AND check_in_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as active_days_week,
            (SELECT COUNT(*) FROM habit_logs hl JOIN habits h ON hl.habit_id = h.id WHERE h.user_id = u.id) as total_logs
        FROM users u 
        WHERE u.role != 'admin'
        ORDER BY total_habits DESC";
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
        // Habit list with log counts
        $stmt = $pdo->prepare("SELECT h.name, h.emoji, 
            (SELECT COUNT(*) FROM habit_logs hl WHERE hl.habit_id = h.id) as log_count
            FROM habits h WHERE h.user_id = ? ORDER BY log_count DESC");
        $stmt->execute([$uid]);
        $user_habits = $stmt->fetchAll();
        
        // Weekly consistency
        $stmt = $pdo->prepare("SELECT DATE(check_in_date) as log_date, COUNT(*) as count
            FROM habit_logs hl JOIN habits h ON hl.habit_id = h.id 
            WHERE h.user_id = ? AND check_in_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(check_in_date) ORDER BY log_date DESC");
        $stmt->execute([$uid]);
        $weekly_logs = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habits Users - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stats-table th, .stats-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .stats-table th { background: #f8f9fa; font-weight: 600; color: #444; }
        .stats-table tr:hover { background: #f1f5f9; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-orange { background: #ffedd5; color: #c2410c; }
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
                    <h1 style="margin-top: 5px;">🔥 Habits Module Users</h1>
                    <p>Track user habit building progress.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total Habits</th>
                            <th>Active Days (Week)</th>
                            <th>Total Check-ins</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong><br>
                                <span style="font-size: 0.8em; color: #888;"><?php echo htmlspecialchars($u['email']); ?></span>
                            </td>
                            <td><span class="badge badge-blue"><?php echo $u['total_habits']; ?></span></td>
                            <td><span class="badge badge-orange"><?php echo $u['active_days_week']; ?>/7 days</span></td>
                            <td><span class="badge badge-green"><?php echo $u['total_logs']; ?></span></td>
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
                <a href="habits_users.php" class="close-btn">&times;</a>
                <h2>🔥 <?php echo htmlspecialchars($details_user['username']); ?>'s Habits</h2>
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                
                <!-- Weekly Consistency -->
                <div class="stat-card" style="background: linear-gradient(135deg, #ffedd5, #fed7aa);">
                    <div class="value" style="color: #c2410c;"><?php echo count($weekly_logs); ?>/7</div>
                    <div class="label">Days Active This Week</div>
                </div>
                
                <h3>📋 Habits List</h3>
                <div style="background: #f9fafb; padding: 15px; border-radius: 10px;">
                    <?php if (empty($user_habits)): ?>
                        <p style="color: #999; text-align: center;">No habits created</p>
                    <?php else: ?>
                        <?php foreach ($user_habits as $h): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 1.3rem;"><?php echo $h['emoji'] ?: '🔥'; ?></span>
                                <span style="font-weight: 600;"><?php echo htmlspecialchars($h['name']); ?></span>
                            </div>
                            <span class="badge badge-green"><?php echo $h['log_count']; ?> check-ins</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <h3 style="margin-top: 20px;">📅 Recent Activity</h3>
                <div style="background: #f9fafb; padding: 15px; border-radius: 10px;">
                    <?php if (empty($weekly_logs)): ?>
                        <p style="color: #999; text-align: center;">No activity this week</p>
                    <?php else: ?>
                        <?php foreach ($weekly_logs as $log): ?>
                        <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                            <span><?php echo date('D, M d', strtotime($log['log_date'])); ?></span>
                            <span class="badge badge-blue"><?php echo $log['count']; ?> habits</span>
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
