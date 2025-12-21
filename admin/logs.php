<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch Logs
$stmt = $pdo->query("
    SELECT l.*, u.username 
    FROM admin_activity_logs l 
    JOIN users u ON l.admin_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT 100
");
$logs = $stmt->fetchAll();

$page = 'logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .log-table {
            width: 100%;
            border-collapse: collapse;
        }
        .log-table th, .log-table td {
            text-align: left;
            padding: 14px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .log-table th { font-weight: 600; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; }
        .action-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-update { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .badge-delete { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .badge-toggle { background: rgba(16, 185, 129, 0.1); color: #10B981; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>Admin Activity Logs</h1>
                    <p>Transparent audit trail of all administrative actions.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="glass-card" style="padding: 0;">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php echo date('M j, H:i', strtotime($log['created_at'])); ?>
                                </td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($log['username']); ?></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'badge-update';
                                        if(strpos($log['action'], 'delete') !== false) $badgeClass = 'badge-delete';
                                        if(strpos($log['action'], 'toggle') !== false) $badgeClass = 'badge-toggle';
                                    ?>
                                    <span class="action-badge <?php echo $badgeClass; ?>">
                                        <?php echo str_replace('_', ' ', $log['action']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.9rem;"><?php echo htmlspecialchars($log['details']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($logs)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">No activity logs found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
