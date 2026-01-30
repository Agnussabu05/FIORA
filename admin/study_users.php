<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$page = 'modules';

// Fetch User Study Stats
$sql = "SELECT 
            u.id, 
            u.username, 
            u.email,
            (SELECT COUNT(*) FROM study_group_members sgm WHERE sgm.user_id = u.id) as groups_joined,
            (SELECT COALESCE(SUM(duration_minutes), 0) FROM study_sessions ss WHERE ss.user_id = u.id) as total_study_minutes
        FROM users u 
        WHERE u.role != 'admin'
        ORDER BY total_study_minutes DESC";
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
        // Groups list
        $stmt = $pdo->prepare("SELECT sg.name, sg.subject 
            FROM study_groups sg 
            JOIN study_group_members sgm ON sg.id = sgm.group_id 
            WHERE sgm.user_id = ?");
        $stmt->execute([$uid]);
        $user_groups = $stmt->fetchAll();
        
        // Study session summary
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as session_count,
            COALESCE(SUM(duration_minutes), 0) as total_minutes
            FROM study_sessions WHERE user_id = ?");
        $stmt->execute([$uid]);
        $session_stats = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Users - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stats-table th, .stats-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .stats-table th { background: #f8f9fa; font-weight: 600; color: #444; }
        .stats-table tr:hover { background: #f1f5f9; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-purple { background: #f3e8ff; color: #9333ea; }
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
                    <h1 style="margin-top: 5px;">📖 Study Module Users</h1>
                    <p>Track user study group participation.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Groups Joined</th>
                            <th>Study Time</th>
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
                            <td><span class="badge badge-blue"><?php echo $u['groups_joined']; ?></span></td>
                            <td><span class="badge badge-green"><?php echo round($u['total_study_minutes'] / 60, 1); ?> hrs</span></td>
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
                <a href="study_users.php" class="close-btn">&times;</a>
                <h2>📖 <?php echo htmlspecialchars($details_user['username']); ?>'s Study</h2>
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                
                <!-- Study Time -->
                <div class="stat-card" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                    <div class="value" style="color: #1d4ed8;">
                        <?php echo round($session_stats['total_minutes'] / 60, 1); ?> hrs
                    </div>
                    <div class="label">Total Study Time</div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="stat-card">
                        <div class="value" style="color: #9333ea;"><?php echo $session_stats['session_count']; ?></div>
                        <div class="label">Sessions</div>
                    </div>
                    <div class="stat-card">
                        <div class="value" style="color: #16a34a;"><?php echo count($user_groups); ?></div>
                        <div class="label">Active Groups</div>
                    </div>
                </div>
                
                <h3 style="margin-top: 20px;">👥 Groups Joined</h3>
                <div style="background: #f9fafb; padding: 15px; border-radius: 10px;">
                    <?php if (empty($user_groups)): ?>
                        <p style="color: #999; text-align: center;">Not in any groups</p>
                    <?php else: ?>
                        <?php foreach ($user_groups as $g): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span style="font-weight: 600;"><?php echo htmlspecialchars($g['name']); ?></span>
                            <span class="badge badge-purple"><?php echo htmlspecialchars($g['subject'] ?? 'General'); ?></span>
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
