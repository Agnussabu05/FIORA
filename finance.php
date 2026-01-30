<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page = 'finance';
$tab = $_GET['tab'] ?? 'overview';

// Date Filtering (Global)
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$endDate = date("Y-m-t", strtotime($startDate));
$currentMonthName = date("F Y", strtotime($startDate));

// ==========================================
// BACKEND LOGIC
// ==========================================

// 1. ADD TRANSACTION (Personal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $type = $_POST['type']; 
    $date = $_POST['date'];
    $description = $_POST['description'];
    
    $stmt = $pdo->prepare("INSERT INTO expenses (user_id, amount, category, type, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $amount, $category, $type, $date, $description]);
    header("Location: finance.php?month=$month&year=$year");
    exit;
}

// 2. CREATE GROUP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $name = $_POST['group_name'];
    $invite_code = substr(md5(uniqid()), 0, 8); // Simple 8-char code
    
    $stmt = $pdo->prepare("INSERT INTO finance_groups (name, created_by, invite_code) VALUES (?, ?, ?)");
    $stmt->execute([$name, $user_id, $invite_code]);
    $group_id = $pdo->lastInsertId();
    
    // Auto-add creator
    $pdo->prepare("INSERT INTO finance_group_members (group_id, user_id) VALUES (?, ?)")->execute([$group_id, $user_id]);
    
    header("Location: finance.php?tab=shared");
    exit;
}

// 3. JOIN GROUP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_group') {
    $code = $_POST['invite_code'];
    $stmt = $pdo->prepare("SELECT id FROM finance_groups WHERE invite_code = ?");
    $stmt->execute([$code]);
    $group = $stmt->fetch();
    
    if ($group) {
        // Check if already member
        $check = $pdo->prepare("SELECT id FROM finance_group_members WHERE group_id = ? AND user_id = ?");
        $check->execute([$group['id'], $user_id]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO finance_group_members (group_id, user_id) VALUES (?, ?)")->execute([$group['id'], $user_id]);
        }
    }
    header("Location: finance.php?tab=shared");
    exit;
}

// 4. ADD SHARED EXPENSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_shared') {
    $group_id = $_POST['group_id'];
    $amount = $_POST['amount'];
    $desc = $_POST['description'];
    $date = $_POST['date']; // Assuming date field exists or defaulting to TODAY
    
    $pdo->prepare("INSERT INTO shared_expenses (group_id, paid_by, amount, description, expense_date) VALUES (?, ?, ?, ?, ?)")
        ->execute([$group_id, $user_id, $amount, $desc, $date]);
        
    header("Location: finance.php?tab=shared");
    exit;
}

// 5. ADD RECURRING BILL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bill') {
    $title = $_POST['title'];
    $amount = $_POST['amount'];
    $due_day = $_POST['due_day'];
    $category = $_POST['category'];
    
    // Calculate next due date
    $currentDay = date('j');
    $nextDate = date('Y-m') . '-' . str_pad($due_day, 2, '0', STR_PAD_LEFT);
    if ($due_day < $currentDay) {
        $nextDate = date('Y-m', strtotime('+1 month')) . '-' . str_pad($due_day, 2, '0', STR_PAD_LEFT);
    }
    
    $pdo->prepare("INSERT INTO recurring_bills (user_id, title, amount, due_day, category, next_due_date) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$user_id, $title, $amount, $due_day, $category, $nextDate]);
        
    header("Location: finance.php?tab=bills");
    exit;
}

// ==========================================
// DATA FETCHING
// ==========================================

// Personal Data (Overview Tab)
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$user_id, $startDate, $endDate]);
$totalIncome = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$user_id, $startDate, $endDate]);
$totalExpense = $stmt->fetchColumn() ?: 0;
$balance = $totalIncome - $totalExpense;

$stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND transaction_date BETWEEN ? AND ? ORDER BY transaction_date DESC");
$stmt->execute([$user_id, $startDate, $endDate]);
$transactions = $stmt->fetchAll();

// Group Data (Shared Tab)
$userGroups = [];
if ($tab === 'shared') {
    $gStmt = $pdo->prepare("
        SELECT g.* 
        FROM finance_groups g 
        JOIN finance_group_members m ON g.id = m.group_id 
        WHERE m.user_id = ?
    ");
    $gStmt->execute([$user_id]);
    $userGroups = $gStmt->fetchAll();
    
    // Fetch expenses for the FIRST group by default if exists
    $activeGroupId = $_GET['group_id'] ?? ($userGroups[0]['id'] ?? null);
    $groupExpenses = [];
    if($activeGroupId) {
        $geStmt = $pdo->prepare("
            SELECT se.*, u.username 
            FROM shared_expenses se 
            JOIN users u ON se.paid_by = u.id 
            WHERE se.group_id = ? 
            ORDER BY se.expense_date DESC
        ");
        $geStmt->execute([$activeGroupId]);
        $groupExpenses = $geStmt->fetchAll();

        // Fetch Members
        $gmStmt = $pdo->prepare("SELECT u.username FROM finance_group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ?");
        $gmStmt->execute([$activeGroupId]);
        $groupMembers = $gmStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Bills Data (Bills Tab)
$bills = [];
if ($tab === 'bills') {
    $bStmt = $pdo->prepare("SELECT * FROM recurring_bills WHERE user_id = ? ORDER BY due_day ASC");
    $bStmt->execute([$user_id]);
    $bills = $bStmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Finance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Flatpickr Overrides for High Contrast */
        .flatpickr-calendar {
            box-shadow: 0 10px 30px rgba(0,0,0,0.2) !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            border-radius: 16px !important;
            background: white !important;
        }
        .flatpickr-day { color: #1f2937 !important; font-weight: 500; }
        .flatpickr-day.selected, .flatpickr-day:hover {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
        }
        .flatpickr-current-month, .flatpickr-current-month input.cur-year {
            color: #111827 !important; font-weight: 700;
        }
        .flatpickr-weekday { color: #4b5563 !important; font-weight: 600; }
        
        .flatpickr-ok-btn {
            width: 90%; margin: 10px 5%; padding: 10px;
            background: var(--primary); color: white;
            border: none; border-radius: 8px; cursor: pointer; font-weight: 700;
        }
        .flatpickr-ok-btn:hover { background: #000; }

        .nav-tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid var(--glass-border); padding-bottom: 15px; }
        .nav-tab {
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .nav-tab.active { background: var(--primary); color: white; }
        .nav-tab:hover:not(.active) { background: rgba(0,0,0,0.05); }

        .group-card {
            background: white; border: 1px solid var(--glass-border);
            padding: 15px; border-radius: 15px; cursor: pointer;
            transition: transform 0.2s;
        }
        .group-card:hover { transform: translateY(-3px); border-color: var(--primary); }
        .group-card.active { border: 2px solid var(--primary); background: #f0fdf4; }
        
        .bill-card {
            display: flex; justify-content: space-between; align-items: center;
            background: white; padding: 15px 20px; border-radius: 12px;
            margin-bottom: 10px; border-left: 4px solid var(--primary);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .bill-date {
            background: #f3f4f6; padding: 8px 12px; border-radius: 8px;
            text-align: center; font-weight: 800; color: #374151;
            min-width: 60px;
        }

        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
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
            <header style="margin-bottom: 25px;">
                <h1 style="color: var(--primary);">Financial Hub 💸</h1>
            </header>

            <div class="nav-tabs">
                <a href="?tab=overview" class="nav-tab <?php echo $tab === 'overview' ? 'active' : ''; ?>">📊 Overview</a>
                <a href="?tab=shared" class="nav-tab <?php echo $tab === 'shared' ? 'active' : ''; ?>">👥 Shared Expenses</a>
                <a href="?tab=bills" class="nav-tab <?php echo $tab === 'bills' ? 'active' : ''; ?>">📅 Bills Calendar</a>
            </div>

            <?php if ($tab === 'overview'): ?>
                <!-- OVERVIEW CONTENT (Existing Logic) -->
                <div class="finance-header">
                    <div>
                        <h2 style="margin: 0;">Personal Dashboard</h2>
                        <span style="color: var(--text-muted);">Tracking for <?php echo $currentMonthName; ?></span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-secondary" onclick="document.getElementById('activityModal').classList.add('active')" 
                                style="padding: 12px 20px; border-radius: 15px; font-weight: 700; background: white; border: 1px solid var(--glass-border); color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
                            📜 History
                        </button>
                        <button class="btn btn-primary" onclick="document.getElementById('txModal').classList.add('active')" 
                                style="padding: 12px 25px; border-radius: 15px; font-weight: 800; background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); display: flex; align-items: center; gap: 8px; transition: transform 0.2s;">
                            <span style="font-size: 1.2rem;">+</span> Add Transaction
                        </button>
                    </div>
                </div>
                
                <div class="summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Income Card -->
                    <div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); padding: 20px; border-radius: 20px; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.1); transition: transform 0.2s; cursor: default;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                        <div style="background: #10b981; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                            📈
                        </div>
                        <div>
                            <div style="color: #047857; font-weight: 700; font-size: 0.9rem; text-transform: uppercase;">Total Income</div>
                            <div style="font-size: 1.8rem; font-weight: 800; color: #065f46; font-family: 'JetBrains Mono', monospace;">₹<?php echo number_format($totalIncome, 2); ?></div>
                        </div>
                    </div>

                    <!-- Expense Card -->
                    <div style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); padding: 20px; border-radius: 20px; border: 1px solid #fecaca; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 6px rgba(239, 68, 68, 0.1); transition: transform 0.2s; cursor: default;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                         <div style="background: #ef4444; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                            📉
                        </div>
                        <div>
                            <div style="color: #b91c1c; font-weight: 700; font-size: 0.9rem; text-transform: uppercase;">Total Expense</div>
                            <div style="font-size: 1.8rem; font-weight: 800; color: #991b1b; font-family: 'JetBrains Mono', monospace;">₹<?php echo number_format($totalExpense, 2); ?></div>
                        </div>
                    </div>

                    <!-- Balance Card -->
                    <div style="background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); padding: 20px; border-radius: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); color: white; transition: transform 0.2s; cursor: default;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                         <div style="background: rgba(255,255,255,0.2); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            🏦
                        </div>
                        <div>
                            <div style="color: rgba(255,255,255,0.9); font-weight: 700; font-size: 0.9rem; text-transform: uppercase;">Net Balance</div>
                            <div style="font-size: 1.8rem; font-weight: 800; color: white; font-family: 'JetBrains Mono', monospace;">₹<?php echo number_format($balance, 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div class="glass-card">
                        <h3 style="margin-bottom: 15px;">📊 Daily Trend (Income vs Expense)</h3>
                        <canvas id="trendChart" height="200"></canvas>
                    </div>
                    <div class="glass-card">
                        <h3 style="margin-bottom: 15px;">🍩 Spending Breakdown</h3>
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>

                <!-- Transaction List Trigger Removed (Moved to Header) -->

            <?php elseif ($tab === 'shared'): ?>
                <!-- SHARED EXPENSES CONTENT -->
                <div class="finance-header">
                    <h2>Group Expenses</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-secondary" onclick="document.getElementById('joinGroupModal').classList.add('active')">Join Group</button>
                        <button class="btn btn-primary" onclick="document.getElementById('createGroupModal').classList.add('active')">+ Create Group</button>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 250px 1fr; gap: 25px;">
                    <!-- Sidebar: Groups List -->
                    <div>
                        <h4 style="margin-bottom: 15px; color: #666;">Your Groups</h4>
                        <?php foreach($userGroups as $g): ?>
                            <div class="group-card <?php echo $activeGroupId == $g['id'] ? 'active' : ''; ?>" 
                                 onclick="window.location.href='finance.php?tab=shared&group_id=<?php echo $g['id']; ?>'">
                                <div style="font-weight: 800;"><?php echo htmlspecialchars($g['name']); ?></div>
                                <div style="font-size: 0.8rem; color: #888; display: flex; align-items: center; gap: 5px;">
                                    Code: <span id="code-<?php echo $g['id']; ?>" style="font-family: monospace; background: #eee; padding: 2px 5px; border-radius: 4px;"><?php echo $g['invite_code']; ?></span>
                                    <button onclick="event.stopPropagation(); copyCode('<?php echo $g['id']; ?>')" style="background: none; border: none; cursor: pointer; color: var(--primary); font-size: 0.8rem; font-weight: 700;">Copy</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($userGroups)): ?>
                            <div style="color: #999; font-style: italic;">No groups yet. Create or join one!</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Main: Expenses List -->
                    <div>
                        <?php if($activeGroupId): ?>
                            <div style="background: white; padding: 20px; border-radius: 15px; border: 1px solid var(--glass-border);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <div>
                                        <h3 style="margin: 0;">Expenses</h3>
                                        <button onclick="document.getElementById('membersModal').classList.add('active')" style="background: #000; border: none; padding: 6px 12px; color: white; border-radius: 8px; font-size: 0.8rem; cursor: pointer; font-weight: 600; margin-top: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                                            👥 View Members
                                        </button>
                                    </div>
                                    <button class="btn btn-primary" onclick="document.getElementById('addSharedModal').classList.add('active')">+ Add Expense</button>
                                </div>
                                
                                <?php foreach($groupExpenses as $exp): ?>
                                    <div style="border-bottom: 1px solid #eee; padding: 15px 0; display: flex; justify-content: space-between;">
                                        <div>
                                            <div style="font-weight: 700;"><?php echo htmlspecialchars($exp['description']); ?></div>
                                            <div style="font-size: 0.85rem; color: #666;">
                                                Paid by <strong style="color: var(--primary);"><?php echo htmlspecialchars($exp['username']); ?></strong> • <?php echo $exp['expense_date']; ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-weight: 800; font-size: 1.1rem;">₹<?php echo number_format($exp['amount'], 2); ?></div>
                                            <?php 
                                            $memberCount = count($groupMembers);
                                            if ($memberCount > 0): 
                                                $split = $exp['amount'] / $memberCount;
                                            ?>
                                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                                                    Start split: ₹<?php echo number_format($split, 2); ?> / person
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 50px; background: rgba(255,255,255,0.5); border-radius: 20px;">
                                👈 Select a group to view expenses
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($tab === 'bills'): ?>
                <!-- BILLS CALENDAR CONTENT -->
                <div class="finance-header">
                    <h2>Recurring Bills</h2>
                    <button class="btn btn-primary" onclick="document.getElementById('addBillModal').classList.add('active')">+ Add Bill</button>
                </div>
                
                <div class="glass-card">
                    <?php if(empty($bills)): ?>
                         <div style="text-align: center; padding: 40px; color: #888;">No recurring bills set up. Relax! 🧘</div>
                    <?php else: ?>
                        <?php foreach($bills as $bill): 
                            $daysLeft = (strtotime($bill['next_due_date']) - time()) / 86400;
                            $statusColor = $daysLeft < 3 ? '#ef4444' : ($daysLeft < 7 ? '#f59e0b' : '#10b981');
                        ?>
                            <div class="bill-card">
                                <div style="display: flex; align-items: center; gap: 20px;">
                                    <div class="bill-date">
                                        <div style="font-size: 0.8rem; text-transform: uppercase;">Due</div>
                                        <div style="font-size: 1.4rem;"><?php echo $bill['due_day']; ?></div>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; font-size: 1.1rem;"><?php echo htmlspecialchars($bill['title']); ?></div>
                                        <div style="color: #666; font-size: 0.9rem;">Next: <?php echo date('M d, Y', strtotime($bill['next_due_date'])); ?></div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 800; font-size: 1.2rem; margin-bottom: 5px;">₹<?php echo number_format($bill['amount'], 2); ?></div>
                                    <span style="font-size: 0.8rem; padding: 3px 8px; border-radius: 4px; background: <?php echo $statusColor; ?>; color: white;">
                                        <?php echo $daysLeft < 0 ? 'Overdue' : ceil($daysLeft) . ' days left'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODALS for Groups, Bills, etc. would go here (simplified for this update) -->
    
    <!-- Recent Activity Modal -->
    <div class="modal" id="activityModal" onclick="if(event.target==this)this.classList.remove('active')">
        <div class="glass-card" style="width: 500px; padding: 30px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Recent Activity</h3>
                <button onclick="document.getElementById('activityModal').classList.remove('active')" style="background:none; border:none; font-size: 1.2rem; cursor: pointer;">✕</button>
            </div>
            
            <?php if(empty($transactions)): ?>
                <div style="text-align: center; color: #999; padding: 20px;">No transactions found for this period.</div>
            <?php else: ?>
                <?php foreach($transactions as $tx): ?>
                    <div class="tx-item" style="border-bottom: 1px solid #eee; padding: 12px 0; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                             <div style="font-weight: 700; color: #333;"><?php echo htmlspecialchars($tx['category']); ?></div>
                             <div style="font-size: 0.85rem; color: #666;">
                                <?php echo date('M d', strtotime($tx['transaction_date'])); ?> • <?php echo htmlspecialchars($tx['description']); ?>
                             </div>
                        </div>
                        <div style="font-weight:bold; font-family: 'JetBrains Mono', monospace; color: <?php echo $tx['type'] == 'income' ? '#10b981' : '#ef4444'; ?>">
                            <?php echo $tx['type'] == 'income' ? '+' : '-'; ?>₹<?php echo number_format($tx['amount']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Group Members Modal -->
    <div class="modal" id="membersModal" onclick="if(event.target==this)this.classList.remove('active')">
        <div class="glass-card" style="width: 350px; padding: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Group Members</h3>
                <button onclick="document.getElementById('membersModal').classList.remove('active')" style="background:none; border:none; font-size: 1.2rem; cursor: pointer;">✕</button>
            </div>
            
            <?php if(!empty($groupMembers)): ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach($groupMembers as $member): ?>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #f8fafc; border-radius: 8px;">
                            <div style="width: 30px; height: 30px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #64748b;">
                                <?php echo strtoupper(substr($member, 0, 1)); ?>
                            </div>
                            <span style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($member); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; color: #94a3b8;">No members info available.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal" id="createGroupModal" onclick="if(event.target==this)this.classList.remove('active')">
        <div class="glass-card" style="width: 400px; padding: 30px;">
            <h3>Create Group</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_group">
                <input type="text" name="group_name" placeholder="Group Name (e.g. Roommates)" class="form-input" required style="width: 100%; margin: 15px 0;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create</button>
            </form>
        </div>
    </div>
    
    <!-- Join Group Modal -->
    <div class="modal" id="joinGroupModal" onclick="if(event.target==this)this.classList.remove('active')">
        <div class="glass-card" style="width: 400px; padding: 30px;">
            <h3>Join Group</h3>
            <form method="POST">
                <input type="hidden" name="action" value="join_group">
                <input type="text" name="invite_code" placeholder="Enter Invite Code" class="form-input" required style="width: 100%; margin: 15px 0;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Join</button>
            </form>
        </div>
    </div>

    <!-- Add Transaction Modal (Existing) -->
    <div class="modal" id="txModal" onclick="if(event.target==this)this.classList.remove('active')">
        <div class="glass-card" style="width: 400px; padding: 30px;">
            <h3>New Transaction</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="type" id="tx-type" value="expense">
                
                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <button type="button" onclick="setType('income')" id="btn-income" style="flex: 1; padding: 12px; border-radius: 12px; border: 1px solid #ddd; background: white; font-weight: 700; color: #666; cursor: pointer; transition: all 0.2s;">
                        💰 Income
                    </button>
                    <button type="button" onclick="setType('expense')" id="btn-expense" style="flex: 1; padding: 12px; border-radius: 12px; border: none; background: #ef4444; font-weight: 700; color: white; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">
                         Expense 📉
                    </button>
                </div>

                <script>
                    function setType(type) {
                        document.getElementById('tx-type').value = type;
                        const inc = document.getElementById('btn-income');
                        const exp = document.getElementById('btn-expense');
                        
                        if (type === 'income') {
                            inc.style.background = '#10b981';
                            inc.style.color = 'white';
                            inc.style.border = 'none';
                            inc.style.boxShadow = '0 4px 12px rgba(16, 185, 129, 0.3)';
                            
                            exp.style.background = 'white';
                            exp.style.color = '#666';
                            exp.style.border = '1px solid #ddd';
                            exp.style.boxShadow = 'none';
                            
                            updateCategories('income');
                        } else {
                            exp.style.background = '#ef4444';
                            exp.style.color = 'white';
                            exp.style.border = 'none';
                            exp.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.3)';
                            
                            inc.style.background = 'white';
                            inc.style.color = '#666';
                            inc.style.border = '1px solid #ddd';
                            inc.style.boxShadow = 'none';
                            
                            updateCategories('expense');
                        }
                    }

                    function updateCategories(type) {
                        const select = document.getElementById('tx-category');
                        select.innerHTML = '<option value="" disabled selected>Select Category</option>';
                        
                        const incomeCats = [
                            {val: 'Salary', label: '💰 Salary'},
                            {val: 'Freelance', label: '💻 Freelance'},
                            {val: 'Investments', label: '📈 Investments'},
                            {val: 'Gifts', label: '🎁 Gifts'},
                            {val: 'Other', label: '🔹 Other'}
                        ];
                        
                        const expenseCats = [
                            {val: 'Food', label: '🍔 Food'},
                            {val: 'Transport', label: '🚗 Transport'},
                            {val: 'Shopping', label: '🛍️ Shopping'},
                            {val: 'Bills', label: '💡 Bills'},
                            {val: 'Entertainment', label: '🎬 Entertainment'},
                            {val: 'Health', label: '🏥 Health'},
                            {val: 'Education', label: '📚 Education'},
                            {val: 'Other', label: '🔹 Other'}
                        ];
                        
                        const fastCats = type === 'income' ? incomeCats : expenseCats;
                        
                        fastCats.forEach(cat => {
                            const opt = document.createElement('option');
                            opt.value = cat.val;
                            opt.innerText = cat.label;
                            select.appendChild(opt);
                        });
                    }
                    
                    // Initialize with expense (default)
                    document.addEventListener('DOMContentLoaded', () => updateCategories('expense'));
                </script>

                <input type="number" name="amount" placeholder="Amount" class="form-input" required style="width: 100%; margin-bottom: 10px;">
                <input type="text" name="description" placeholder="Description" class="form-input" style="width: 100%; margin-bottom: 10px;">
                <select name="category" id="tx-category" class="form-input" required style="width: 100%; margin-bottom: 10px;">
                    <!-- Populated by JS -->
                </select>
                <input type="text" name="date" value="<?php echo date('Y-m-d'); ?>" class="form-input flatpickr-input" required style="width: 100%; margin-bottom: 10px;" placeholder="Select Date">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save</button>
            </form>
        </div>
    </div>
    
    <!-- Add Shared Expense Modal -->
    <div class="modal" id="addSharedModal" onclick="if(event.target==this)this.classList.remove('active')">
        <div class="glass-card" style="width: 400px; padding: 30px;">
            <h3>Add Group Expense</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_shared">
                <input type="hidden" name="group_id" value="<?php echo $activeGroupId; ?>">
                <input type="number" name="amount" placeholder="Amount" class="form-input" required style="width: 100%; margin-bottom: 10px;">
                <input type="text" name="description" placeholder="What for?" class="form-input" required style="width: 100%; margin-bottom: 10px;">
                <input type="text" name="date" value="<?php echo date('Y-m-d'); ?>" class="form-input flatpickr-input" required style="width: 100%; margin-bottom: 10px;" placeholder="Select Date">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Add to Group</button>
            </form>
        </div>
    </div>
    
    <!-- Add Bill Modal -->
    <div class="modal" id="addBillModal" onclick="if(event.target==this)this.classList.remove('active')">
        <div class="glass-card" style="width: 400px; padding: 30px;">
            <h3>Add Recurring Bill</h3>
            <form method="POST" id="addBillForm" novalidate onsubmit="return validateBillForm()">
                <input type="hidden" name="action" value="add_bill">
                <div style="margin-bottom: 10px;">
                    <input type="text" name="title" id="billTitle" placeholder="Bill Name (e.g. Netflix)" class="form-input" style="width: 100%;">
                    <span class="error-msg" id="error-title" style="color: #ef4444; font-size: 0.8rem; display: none;">⚠️ Please enter a bill name</span>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <input type="number" name="amount" id="billAmount" placeholder="Amount" class="form-input" style="width: 100%;">
                    <span class="error-msg" id="error-amount" style="color: #ef4444; font-size: 0.8rem; display: none;">⚠️ Please enter an amount</span>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <input type="number" name="due_day" id="billDay" placeholder="Day of Month (1-31)" min="1" max="31" class="form-input" style="width: 100%;">
                    <span class="error-msg" id="error-day" style="color: #ef4444; font-size: 0.8rem; display: none;">⚠️ Please enter a valid day (1-31)</span>
                </div>

                <div style="margin-bottom: 10px;">
                    <select name="category" id="billCategory" class="form-input" style="width: 100%;">
                        <option value="" disabled selected>Select Category</option>
                        <option value="Utilities">💡 Utilities</option>
                        <option value="Subscription">📺 Subscription</option>
                        <option value="Rent">🏠 Rent</option>
                        <option value="Internet">🌐 Internet</option>
                        <option value="Insurance">🛡️ Insurance</option>
                        <option value="Education">🎓 Education</option>
                        <option value="Other">🔹 Other</option>
                    </select>
                    <span class="error-msg" id="error-category" style="color: #ef4444; font-size: 0.8rem; display: none;">⚠️ Please select a category</span>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Add Reminder</button>
            </form>
            <script>
                function validateBillForm() {
                    let isValid = true;
                    
                    // Title
                    const title = document.getElementById('billTitle');
                    if(title.value.trim() === '') {
                        document.getElementById('error-title').style.display = 'block';
                        title.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        document.getElementById('error-title').style.display = 'none';
                        title.style.borderColor = ''; // reset
                    }

                    // Amount
                    const amount = document.getElementById('billAmount');
                    if(amount.value.trim() === '') {
                        document.getElementById('error-amount').style.display = 'block';
                        amount.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        document.getElementById('error-amount').style.display = 'none';
                        amount.style.borderColor = '';
                    }

                    // Day
                    const day = document.getElementById('billDay');
                    if(day.value.trim() === '' || day.value < 1 || day.value > 31) {
                        document.getElementById('error-day').style.display = 'block';
                        day.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        document.getElementById('error-day').style.display = 'none';
                        day.style.borderColor = '';
                    }

                    // Category
                    const cat = document.getElementById('billCategory');
                    if(cat.value === '') {
                        document.getElementById('error-category').style.display = 'block';
                        cat.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        document.getElementById('error-category').style.display = 'none';
                        cat.style.borderColor = '';
                    }

                    return isValid;
                }
            </script>
        </div>
    </div>

    <!-- Chart.js Logic -->
    <script>
        function copyCode(id) {
            const code = document.getElementById('code-' + id).innerText;
            navigator.clipboard.writeText(code).then(() => {
                alert('Invite Code Copied: ' + code);
            });
        }

        <?php
        // Fetch Chart Data for standard view
        if ($tab === 'overview') {
             // 1. Daily Trend
            $trendStmt = $pdo->prepare("
                SELECT DAY(transaction_date) as day, 
                       SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as inc,
                       SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as exp
                FROM expenses WHERE user_id=? AND transaction_date BETWEEN ? AND ? GROUP BY DAY(transaction_date) ORDER BY day
            ");
            $trendStmt->execute([$user_id, $startDate, $endDate]);
            $trendData = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
            $days = range(1, date('t', strtotime($startDate)));
            $incSeries = array_fill_keys($days, 0);
            $expSeries = array_fill_keys($days, 0);
            foreach($trendData as $r) {
                $incSeries[$r['day']] = (float)$r['inc'];
                $expSeries[$r['day']] = (float)$r['exp'];
            }

            // 2. Category Breakdown
            $catStmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id=? AND type='expense' AND transaction_date BETWEEN ? AND ? GROUP BY category");
            $catStmt->execute([$user_id, $startDate, $endDate]);
            $catData = $catStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        ?>
        
        <?php if ($tab === 'overview'): ?>
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($incSeries)); ?>,
                datasets: [
                    { label: 'Income', data: <?php echo json_encode(array_values($incSeries)); ?>, backgroundColor: '#10b981', borderRadius: 4 },
                    { label: 'Expense', data: <?php echo json_encode(array_values($expSeries)); ?>, backgroundColor: '#ef4444', borderRadius: 4 }
                ]
            },
            options: { 
                responsive: true, 
                scales: { 
                    x: { display: true, grid: { display: false } }, 
                    y: { display: true, beginAtZero: true, border: { display: false } } 
                }, 
                plugins: { legend: { display: true, position: 'bottom' } } 
            }
        });

        const catCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($catData, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($catData, 'total')); ?>,
                    backgroundColor: ['#f87171', '#fbbf24', '#34d399', '#60a5fa', '#a78bfa', '#f472b6'],
                    borderWidth: 0
                }]
            },
            options: { cutout: '70%', plugins: { legend: { position: 'right' } } }
        });
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".flatpickr-input", {
                enableTime: false,
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y",
                maxDate: "today",
                onReady: function(selectedDates, dateStr, instance) {
                    const btn = document.createElement("button");
                    btn.type = "button"; 
                    btn.innerText = "OK";
                    btn.className = "flatpickr-ok-btn";
                    btn.onclick = function() {
                        instance.close();
                    };
                    instance.calendarContainer.appendChild(btn);
                }
            });
        });
    </script>
