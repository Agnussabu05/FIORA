<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Handle User Deletion
if (isset($_POST['delete_user_id'])) {
    $deleteId = (int)$_POST['delete_user_id'];
    if ($deleteId != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$deleteId]);
        
        // Log
        $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'delete_user', "Deleted user ID $deleteId"]);
    }
}

// Handle Status Toggle (Activate/Deactivate)
if (isset($_POST['toggle_status_id'])) {
    $userId = (int)$_POST['toggle_status_id'];
    // In a real app, this would toggle an 'is_active' column. 
    // We'll simulate by logging for now or updating if column exists.
    $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'toggle_user_status', "Toggled status for user ID $userId"]);
}

// Search Logic
$search = $_GET['search'] ?? '';
$params = [];

$query = "
    SELECT 
        u.id, 
        u.username, 
        u.role, 
        u.created_at,
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM users u
    LEFT JOIN tasks t ON u.id = t.user_id
";

if ($search) {
    $query .= " WHERE u.username LIKE ?";
    $params[] = "%$search%";
}

$query .= " GROUP BY u.id, u.username, u.role, u.created_at";
$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$page = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .user-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .user-table th, .user-table td { text-align: left; padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .user-table th { font-weight: 600; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
        .badge-admin { background: rgba(79, 70, 229, 0.1); color: #4F46E5; }
        .badge-user { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .search-bar { width: 100%; max-width: 400px; padding: 12px 16px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1); background: rgba(255,255,255,0.2); color: var(--text-main); margin-bottom: 20px; }
        .btn-action { background: none; border: none; cursor: pointer; font-size: 1.1rem; filter: grayscale(1); transition: filter 0.2s; }
        .btn-action:hover { filter: grayscale(0); }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>User Management</h1>
                    <p>Control access and monitor system inhabitants.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <form method="GET" style="display: flex; gap: 10px; align-items: flex-start;">
                    <input type="text" name="search" class="search-bar" placeholder="Search by username..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 20px;">Search</button>
                </form>

                <div class="glass-card" style="padding: 0; overflow-x: auto;">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Tasks</th>
                                <th>Progress</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): 
                                $total = $user['total_tasks'] ?: 0;
                                $completed = $user['completed_tasks'] ?: 0;
                                $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
                            ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                        <?php echo ucfirst($user['role'] ?? 'user'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 0.9rem; font-weight: 600;">
                                        <?php echo $completed; ?> / <?php echo $total; ?>
                                    </span>
                                </td>
                                <td style="width: 150px;">
                                    <div style="background: rgba(0,0,0,0.1); border-radius: 10px; height: 8px; width: 100%; overflow: hidden;">
                                        <div style="background: #4facfe; height: 100%; width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                        <?php echo $percent; ?>%
                                    </div>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <span style="color: #10B981; font-weight: 600;">● Active</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn btn-primary" style="text-decoration: none; padding: 6px 12px; font-size: 0.85rem; border-radius: 8px;">View Details</a>
                                        
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="toggle_status_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn-action" title="Deactivate">🚫</button>
                                        </form>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" onsubmit="return confirm('Permanently delete this user?');" style="display:inline;">
                                            <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn-action" title="Delete">🗑️</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
