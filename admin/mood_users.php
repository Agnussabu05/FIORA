<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$page = 'modules'; // Highlight 'Modules' in sidebar

// Fetch User Mood Stats
$sql = "SELECT 
            u.id, 
            u.username, 
            u.email,
            (SELECT COUNT(*) FROM mood_logs ml WHERE ml.user_id = u.id) as total_logs,
            (SELECT ROUND(AVG(mood_score), 1) FROM mood_logs ml WHERE ml.user_id = u.id) as avg_mood,
            (SELECT MAX(log_date) FROM mood_logs ml WHERE ml.user_id = u.id) as last_log
        FROM users u 
        WHERE u.role != 'admin'
        HAVING total_logs > 0
        ORDER BY last_log DESC";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Activity - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .stats-table th, .stats-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .stats-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #444;
        }
        .stats-table tr:hover {
            background: #f1f5f9;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .badge-mood-low { background: #fee2e2; color: #ef4444; }
        .badge-mood-med { background: #fef3c7; color: #d97706; }
        .badge-mood-high { background: #dcfce7; color: #16a34a; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div>
                    <a href="modules.php" style="text-decoration: none; color: #666;">&larr; Back to Modules</a>
                    <h1 style="margin-top: 5px;">Mood Tracker Users</h1>
                    <p>View user well-being statistics.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total Logs</th>
                            <th>Avg Mood</th>
                            <th>Last Check-in</th>
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
                            <td><?php echo $u['total_logs']; ?></td>
                            <td>
                                <?php 
                                    $bg = '#eee'; $col = '#333';
                                    if($u['avg_mood'] >= 4) { $bg = '#dcfce7'; $col='#16a34a'; }
                                    elseif($u['avg_mood'] >= 2.5) { $bg = '#dbeafe'; $col='#2563eb'; }
                                    else { $bg = '#fee2e2'; $col='#ef4444'; }
                                ?>
                                <span class="badge" style="background: <?php echo $bg; ?>; color: <?php echo $col; ?>;">
                                    <?php echo $u['avg_mood']; ?>/5
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($u['last_log'])); ?></td>
                            <td>
                                <a href="mood_report_view.php?user_id=<?php echo $u['id']; ?>" class="btn-small" style="color: var(--primary); text-decoration: none; font-weight: 600;">View Report &rarr;</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($users)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #888;">No mood data found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
