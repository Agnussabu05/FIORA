<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'finance';

// Date Filtering
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$endDate = date("Y-m-t", strtotime($startDate));
$currentMonthName = date("F Y", strtotime($startDate));

// Handle Add Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $type = $_POST['type']; // income or expense
    $date = $_POST['date'];
    $description = $_POST['description'];
    
    $stmt = $pdo->prepare("INSERT INTO expenses (user_id, amount, category, type, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $amount, $category, $type, $date, $description]);
    header("Location: finance.php?month=$month&year=$year");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: finance.php?month=$month&year=$year");
    exit;
}

// Calculations for selected month
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$user_id, $startDate, $endDate]);
$totalIncome = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$user_id, $startDate, $endDate]);
$totalExpense = $stmt->fetchColumn() ?: 0;

$balance = $totalIncome - $totalExpense;

// Fetch Transactions for selected month
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND transaction_date BETWEEN ? AND ? ORDER BY transaction_date DESC, id DESC");
$stmt->execute([$user_id, $startDate, $endDate]);
$transactions = $stmt->fetchAll();

// Category Breakdown (Expenses only)
$chartStmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ? GROUP BY category");
$chartStmt->execute([$user_id, $startDate, $endDate]);
$chartData = $chartStmt->fetchAll();

$categories = [];
$catData = [];
foreach($chartData as $row) {
    $categories[] = $row['category'];
    $catData[] = (float)$row['total'];
}

// Monthly Trend (Daily Income vs Expense)
$trendStmt = $pdo->prepare("
    SELECT 
        DAY(transaction_date) as day,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as inc,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as exp
    FROM expenses 
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
    GROUP BY DAY(transaction_date)
    ORDER BY day
");
$trendStmt->execute([$user_id, $startDate, $endDate]);
$trendRows = $trendStmt->fetchAll();

$daysInMonth = date('t', strtotime($startDate));
$dailyInc = array_fill(1, $daysInMonth, 0);
$dailyExp = array_fill(1, $daysInMonth, 0);
foreach ($trendRows as $row) {
    $dailyInc[$row['day']] = (float)$row['inc'];
    $dailyExp[$row['day']] = (float)$row['exp'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Premium Finance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --success: #6B8C73;
            --danger: #C06C6C;
            --chart-bg: rgba(255, 255, 255, 0.5);
        }

        .finance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .month-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--glass-bg);
            padding: 8px 15px;
            border-radius: 15px;
            border: 1px solid var(--glass-border);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .summary-card {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .summary-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.08); }

        .card-icon {
            width: 45px; height: 45px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }

        .amount-display {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .tx-list-container {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .tx-item {
            background: rgba(255,255,255,0.3);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .tx-item:hover { background: rgba(255,255,255,0.6); transform: scale(1.01); }

        .tx-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .type-income { color: var(--success); }
        .type-expense { color: var(--danger); }

        .btn-nav {
            padding: 8px 12px;
            border-radius: 10px;
            background: white;
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-nav:hover { background: var(--primary); color: white; }

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
            <header class="finance-header">
                <div>
                    <h1 style="color: #000 !important;">Financial Dashboard</h1>
                    <p style="color: #222; font-weight: 700;">Balance: <strong>₹<?php echo number_format($balance, 2); ?></strong> in <?php echo $currentMonthName; ?></p>
                </div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div class="month-selector">
                        <?php 
                        $prevMonth = $month - 1; $prevYear = $year; if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
                        $nextMonth = $month + 1; $nextYear = $year; if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
                        ?>
                        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn-nav">←</a>
                        <span style="font-weight: 700; width: 120px; text-align: center;"><?php echo $currentMonthName; ?></span>
                        <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn-nav">→</a>
                    </div>
                    <button class="btn btn-primary" onclick="openModal()">+ Add Transaction</button>
                </div>
            </header>

            <div class="summary-grid">
                <div class="summary-card">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="card-icon" style="background: rgba(107, 140, 115, 0.2); color: var(--success); font-weight: 900;">📈</div>
                        <span style="font-weight: 800; color: #222;">Total Income</span>
                    </div>
                    <div class="amount-display type-income" style="font-size: 2.2rem; font-weight: 800;">₹<?php echo number_format($totalIncome, 2); ?></div>
                </div>
                <div class="summary-card">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="card-icon" style="background: rgba(192, 108, 108, 0.2); color: var(--danger); font-weight: 900;">📉</div>
                        <span style="font-weight: 800; color: #222;">Total Expense</span>
                    </div>
                    <div class="amount-display type-expense" style="font-size: 2.2rem; font-weight: 800;">₹<?php echo number_format($totalExpense, 2); ?></div>
                </div>
                <div class="summary-card" style="background: var(--primary); color: white; border: none;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="card-icon" style="background: rgba(255,255,255,0.2); color: white;">🏦</div>
                        <span style="font-weight: 600; opacity: 0.9;">Net Balance</span>
                    </div>
                    <div class="amount-display" style="color: white;">₹<?php echo number_format($balance, 2); ?></div>
                </div>
            </div>

            <div class="dashboard-grid" style="grid-template-columns: 1.5fr 1fr; gap: 30px;">
                <div class="glass-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <h3>History Tracking</h3>
                        <span style="font-size: 0.8rem; background: var(--primary); color: white; padding: 4px 10px; border-radius: 20px;">
                            <?php echo count($transactions); ?> Records
                        </span>
                    </div>
                    <div class="tx-list-container">
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach($transactions as $tx): ?>
                                <div class="tx-item">
                                    <div style="display: flex; gap: 15px; align-items: center;">
                                        <div class="tx-icon" style="background: <?php echo $tx['type'] == 'income' ? 'rgba(107, 140, 115, 0.1)' : 'rgba(192, 108, 108, 0.1)'; ?>;">
                                            <?php echo $tx['type'] == 'income' ? '💰' : '🛒'; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 800; color: #000; font-size: 1.05rem;"><?php echo htmlspecialchars($tx['category']); ?></div>
                                            <div style="font-size: 0.9rem; color: #333; font-weight: 600;">
                                                <?php echo date('M d', strtotime($tx['transaction_date'])); ?> 
                                                <?php if($tx['description']): ?>• <?php echo htmlspecialchars($tx['description']); ?><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="<?php echo $tx['type'] == 'income' ? 'type-income' : 'type-expense'; ?>" style="font-weight: 700; font-family: 'JetBrains Mono', monospace;">
                                            <?php echo $tx['type'] == 'income' ? '+' : '-'; ?>₹<?php echo number_format($tx['amount'], 2); ?>
                                        </div>
                                        <a href="?delete=<?php echo $tx['id']; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                           style="font-size: 0.75rem; color: var(--text-muted); text-decoration: none;" 
                                           onclick="return confirm('Delete this record?')">Remove</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 50px; color: var(--text-muted);">
                                <div style="font-size: 3rem; margin-bottom: 15px;">🍃</div>
                                <p>No transactions found for this period.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 30px;">
                    <div class="glass-card">
                        <h3 style="margin-bottom: 20px;">Category Pulse</h3>
                        <canvas id="categoryChart" height="300"></canvas>
                        <?php if (empty($catData)): ?>
                            <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-top: 15px;">No spending data yet.</p>
                        <?php endif; ?>
                    </div>
                    <div class="glass-card">
                        <h3 style="margin-bottom: 20px;">Daily Trend</h3>
                        <canvas id="trendChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="txModal">
        <div class="glass-card" style="width: 420px; padding: 30px;">
            <h3 style="margin-bottom: 25px;">New Record</h3>
            <form action="finance.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Entry Type</label>
                        <select name="type" class="form-input" onchange="toggleCategories(this.value)" style="padding: 10px;">
                            <option value="expense">Expense 📉</option>
                            <option value="income">Income 📈</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" class="form-input" required placeholder="0.00" style="padding: 10px;">
                    </div>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-input" id="categorySelect" style="padding: 10px;">
                        <option value="Food">Food</option>
                        <option value="Transport">Transport</option>
                        <option value="Shopping">Shopping</option>
                        <option value="Bills">Bills</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Health">Health</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" class="form-input" required value="<?php echo date('Y-m-d'); ?>" style="padding: 10px;">
                </div>

                <div class="form-group">
                    <label>Short Note</label>
                    <input type="text" name="description" class="form-input" placeholder="What was this for?" style="padding: 10px;">
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="color: #000 !important; font-weight: 800;">Dismiss</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('txModal').classList.add('active'); }
        function closeModal() { document.getElementById('txModal').classList.remove('active'); }
        document.getElementById('txModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('txModal')) closeModal();
        });

        const expenseCats = ['Food', 'Transport', 'Shopping', 'Bills', 'Entertainment', 'Health', 'Other'];
        const incomeCats = ['Salary', 'Freelance', 'Gift', 'Investment', 'Other'];
        
        function toggleCategories(type) {
            const select = document.getElementById('categorySelect');
            select.innerHTML = '';
            const list = type === 'income' ? incomeCats : expenseCats;
            list.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat;
                opt.innerText = cat;
                select.appendChild(opt);
            });
        }

        // --- CHARTS ---
        const themeColors = ['#6B8C73', '#C06C6C', '#D9A066', '#9E9080', '#5D4037', '#8D6E63', '#4E342E'];

        // Category Breakdown
        const catCtx = document.getElementById('categoryChart').getContext('2d');
        const catLabels = <?php echo json_encode($categories); ?>;
        const catPoints = <?php echo json_encode($catData); ?>;

        if (catPoints.length > 0) {
            new Chart(catCtx, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catPoints,
                        backgroundColor: themeColors,
                        borderWidth: 0,
                        hoverOffset: 15
                    }]
                },
                options: {
                    cutout: '65%',
                    plugins: { 
                        legend: { position: 'bottom', labels: { padding: 20, font: { weight: '600' } } } 
                    }
                }
            });
        }

        // Daily Trend
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const days = Array.from({length: <?php echo $daysInMonth; ?>}, (_, i) => i + 1);
        const incData = <?php echo json_encode(array_values($dailyInc)); ?>;
        const expData = <?php echo json_encode(array_values($dailyExp)); ?>;

        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: days,
                datasets: [
                    { label: 'Inc', data: incData, backgroundColor: '#6B8C73', borderRadius: 5 },
                    { label: 'Exp', data: expData, backgroundColor: '#C06C6C', borderRadius: 5 }
                ]
            },
            options: {
                responsive: true,
                scales: { 
                    x: { display: false },
                    y: { beginAtZero: true, grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
