<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$page = 'modules'; // Highlight 'Modules' in sidebar

// Fetch User Financial Stats
$sql = "SELECT 
            u.id, 
            u.username, 
            u.email,
            (SELECT COALESCE(SUM(amount), 0) FROM expenses e WHERE e.user_id = u.id AND e.type = 'income') as total_income,
            (SELECT COALESCE(SUM(amount), 0) FROM expenses e WHERE e.user_id = u.id AND e.type = 'expense') as total_expense,
            (SELECT COUNT(*) FROM expenses e WHERE e.user_id = u.id) as transaction_count,
            (SELECT COUNT(*) FROM finance_group_members gm WHERE gm.user_id = u.id) as groups_joined,
            (SELECT COUNT(*) FROM recurring_bills rb WHERE rb.user_id = u.id) as recurring_bills
        FROM users u 
        WHERE u.role != 'admin'
        ORDER BY total_expense DESC";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();

// Handle "View Details" specific user
$details_user = null;
if (isset($_GET['view_user'])) {
    $uid = $_GET['view_user'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $details_user = $stmt->fetch();
    
    if ($details_user) {
        // Fetch AGGREGATED stats only (privacy-friendly)
        // Category breakdown (count only, no amounts)
        $stmt = $pdo->prepare("
            SELECT category, type, COUNT(*) as count 
            FROM expenses WHERE user_id = ? 
            GROUP BY category, type 
            ORDER BY count DESC
        ");
        $stmt->execute([$uid]);
        $category_stats = $stmt->fetchAll();
        
        // Monthly activity count (no amounts)
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, 
                   COUNT(*) as count
            FROM expenses WHERE user_id = ? 
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month DESC LIMIT 6
        ");
        $stmt->execute([$uid]);
        $monthly_activity = $stmt->fetchAll();
        
        // Recurring bills count only (no amounts/titles)
        $stmt = $pdo->prepare("SELECT COUNT(*) as bill_count FROM recurring_bills WHERE user_id = ?");
        $stmt->execute([$uid]);
        $bills_count = $stmt->fetch()['bill_count'];
        
        // Activity summary
        $stmt = $pdo->prepare("SELECT 
            COUNT(CASE WHEN type='income' THEN 1 END) as income_count,
            COUNT(CASE WHEN type='expense' THEN 1 END) as expense_count
            FROM expenses WHERE user_id = ?");
        $stmt->execute([$uid]);
        $activity_summary = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Users - Fiora Admin</title>
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
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-red { background: #fee2e2; color: #dc2626; }
        .badge-blue { background: #dbeafe; color: #2563eb; }
        .badge-purple { background: #f3e8ff; color: #9333ea; }
        
        .details-panel {
            position: fixed;
            top: 0; right: 0;
            width: 520px;
            height: 100%;
            background: white;
            box-shadow: -5px 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .details-panel.open {
            transform: translateX(0);
        }
        .close-btn {
            float: right;
            cursor: pointer;
            font-size: 1.5rem;
            color: #666;
            text-decoration: none;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        .summary-card {
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .summary-card.income { background: linear-gradient(135deg, #dcfce7, #bbf7d0); }
        .summary-card.expense { background: linear-gradient(135deg, #fee2e2, #fecaca); }
        .summary-card h4 { margin: 0; font-size: 0.85rem; text-transform: uppercase; opacity: 0.7; }
        .summary-card .amount { font-size: 1.5rem; font-weight: 800; margin-top: 5px; }
        
        .tx-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .tx-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div>
                    <a href="modules.php" style="text-decoration: none; color: #666;">&larr; Back to Modules</a>
                    <h1 style="margin-top: 5px;">💰 Finance Module Users</h1>
                    <p>Track user financial activities and spending patterns.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total Income</th>
                            <th>Total Expense</th>
                            <th>Balance</th>
                            <th>Transactions</th>
                            <th>Bills</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <?php $balance = $u['total_income'] - $u['total_expense']; ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong><br>
                                <span style="font-size: 0.8em; color: #888;"><?php echo htmlspecialchars($u['email']); ?></span>
                            </td>
                            <td><span class="badge badge-green">₹<?php echo number_format($u['total_income'], 2); ?></span></td>
                            <td><span class="badge badge-red">₹<?php echo number_format($u['total_expense'], 2); ?></span></td>
                            <td>
                                <span style="font-weight: 700; color: <?php echo $balance >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                                    ₹<?php echo number_format($balance, 2); ?>
                                </span>
                            </td>
                            <td><span class="badge badge-blue"><?php echo $u['transaction_count']; ?></span></td>
                            <td><span class="badge badge-purple"><?php echo $u['recurring_bills']; ?></span></td>
                            <td>
                                <a href="?view_user=<?php echo $u['id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;">View Details</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #888; padding: 30px;">No users found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($details_user): ?>
            <div class="details-panel open">
                <a href="finance_users.php" class="close-btn">&times;</a>
                <h2>📊 <?php echo htmlspecialchars($details_user['username']); ?>'s Activity</h2>
                <p style="color: #888; font-size: 0.9rem; margin-top: -10px;">Privacy-protected summary</p>
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                
                <!-- Activity Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card income">
                        <h4>Income Entries</h4>
                        <div class="amount" style="color: #16a34a;"><?php echo $activity_summary['income_count']; ?></div>
                    </div>
                    <div class="summary-card expense">
                        <h4>Expense Entries</h4>
                        <div class="amount" style="color: #dc2626;"><?php echo $activity_summary['expense_count']; ?></div>
                    </div>
                </div>
                
                <!-- Category Breakdown -->
                <h3>📁 Category Breakdown</h3>
                <?php if (empty($category_stats)): ?>
                    <p style="color: #999;">No activity recorded.</p>
                <?php else: ?>
                    <div style="background: #f9fafb; border-radius: 10px; padding: 15px;">
                        <?php foreach ($category_stats as $cat): ?>
                        <div class="tx-item">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 1.2rem;"><?php echo $cat['type'] == 'income' ? '📈' : '📉'; ?></span>
                                <div>
                                    <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($cat['category']); ?></div>
                                    <div style="font-size: 0.8rem; color: #888;"><?php echo ucfirst($cat['type']); ?></div>
                                </div>
                            </div>
                            <span class="badge <?php echo $cat['type'] == 'income' ? 'badge-green' : 'badge-red'; ?>">
                                <?php echo $cat['count']; ?> entries
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Monthly Activity -->
                <h3 style="margin-top: 25px;">📅 Monthly Activity</h3>
                <?php if (empty($monthly_activity)): ?>
                    <p style="color: #999;">No monthly data.</p>
                <?php else: ?>
                    <div style="background: #f9fafb; border-radius: 10px; padding: 15px;">
                        <?php foreach ($monthly_activity as $month): ?>
                        <div class="tx-item">
                            <div style="font-weight: 600; color: #333;">
                                <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                            </div>
                            <span class="badge badge-blue"><?php echo $month['count']; ?> transactions</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Bills Summary -->
                <h3 style="margin-top: 25px;">🔄 Recurring Bills</h3>
                <div style="background: #f3e8ff; padding: 15px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 800; color: #8b5cf6;"><?php echo $bills_count; ?></div>
                    <div style="color: #6b21a8; font-weight: 600;">Active Bills</div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>
