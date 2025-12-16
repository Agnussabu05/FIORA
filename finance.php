<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'finance';

// Handle Add Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $type = $_POST['type']; // income or expense
    $date = $_POST['date'];
    $description = $_POST['description'];
    
    // user_id = $user_id
    $stmt = $pdo->prepare("INSERT INTO expenses (user_id, amount, category, type, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $amount, $category, $type, $date, $description]);
    header("Location: finance.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: finance.php");
    exit;
}

// Fetch Calculations
$userId = $user_id;
// Total Income
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND type = 'income'");
$stmt->execute([$userId]);
$totalIncome = $stmt->fetchColumn() ?: 0;

// Total Expense
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND type = 'expense'");
$stmt->execute([$userId]);
$totalExpense = $stmt->fetchColumn() ?: 0;

$balance = $totalIncome - $totalExpense;

// Fetch Transactions
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY transaction_date DESC");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll();

// Prepare Chart Data (Category wise expenses)
$chartStmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id = ? AND type = 'expense' GROUP BY category");
$chartStmt->execute([$userId]);
$chartData = $chartStmt->fetchAll();

$categories = [];
$catData = [];
foreach($chartData as $row) {
    $categories[] = $row['category'];
    $catData[] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Finance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .finance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .icon-box {
            width: 50px; height: 50px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        .tx-list {
            margin-top: 20px;
        }
        .tx-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--glass-border);
            font-size: 0.95rem;
        }
        .tx-income { color: var(--success); }
        .tx-expense { color: var(--danger); }
        
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            z-index: 999;
        }
        .modal.active { display: flex; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Expense Tracker 💰</h1>
                    <p style="color: var(--text-muted);">Manage your budget effectively.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ Add Transaction</button>
            </header>

            <div class="finance-summary">
                <div class="summary-card">
                    <div class="icon-box" style="background: rgba(0, 184, 148, 0.2); color: var(--success);">💵</div>
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-muted);">Income</div>
                        <div style="font-size: 1.5rem; font-weight: bold;">$<?php echo number_format($totalIncome, 2); ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-box" style="background: rgba(214, 48, 49, 0.2); color: var(--danger);">📉</div>
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-muted);">Expenses</div>
                        <div style="font-size: 1.5rem; font-weight: bold;">$<?php echo number_format($totalExpense, 2); ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-box" style="background: rgba(108, 99, 255, 0.2); color: var(--primary);">🏦</div>
                    <div>
                        <div style="font-size: 0.9rem; color: var(--text-muted);">Balance</div>
                        <div style="font-size: 1.5rem; font-weight: bold;">$<?php echo number_format($balance, 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
                <div class="glass-card">
                    <h3>Recent Transactions</h3>
                    <div class="tx-list">
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach($transactions as $tx): ?>
                                <div class="tx-item">
                                    <div style="display: flex; gap: 15px; align-items: center;">
                                        <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <?php echo $tx['type'] == 'income' ? '💰' : '🛒'; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($tx['category']); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo $tx['transaction_date']; ?> • <?php echo htmlspecialchars($tx['description']); ?></div>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="<?php echo $tx['type'] == 'income' ? 'tx-income' : 'tx-expense'; ?>" style="font-weight: bold;">
                                            <?php echo $tx['type'] == 'income' ? '+' : '-'; ?>$<?php echo number_format($tx['amount'], 2); ?>
                                        </div>
                                        <a href="?delete=<?php echo $tx['id']; ?>" style="font-size: 0.8rem; color: var(--text-muted); text-decoration: none;">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="padding: 20px; text-align: center; color: var(--text-muted);">No transactions yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="glass-card">
                    <h3>Expenses by Category</h3>
                    <div style="padding: 20px;">
                        <canvas id="expenseChart"></canvas>
                    </div>
                    <?php if (empty($catData)): ?>
                        <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem;">No expenses data to display.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Transaction Modal -->
    <div class="modal" id="txModal">
        <div class="glass-card" style="width: 400px;">
            <h3 style="margin-bottom: 20px;">Add Transaction</h3>
            <form action="finance.php" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Type</label>
                    <select name="type" class="form-input" onchange="toggleCategories(this.value)">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Amount ($)</label>
                    <input type="number" step="0.01" name="amount" class="form-input" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Category</label>
                    <select name="category" class="form-input" id="categorySelect">
                        <!-- Options populated by JS -->
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
                    <label style="display: block; margin-bottom: 5px;">Date</label>
                    <input type="date" name="date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">Description (Optional)</label>
                    <input type="text" name="description" class="form-input" placeholder="Lunch, Uber, etc.">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('txModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('txModal').classList.remove('active');
        }
        document.getElementById('txModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('txModal')) closeModal();
        });

        // Dynamic categories
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

        // Chart
        const ctx = document.getElementById('expenseChart').getContext('2d');
        const labels = <?php echo json_encode($categories); ?>;
        const data = <?php echo json_encode($catData); ?>;

        if (data.length > 0) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            '#6C63FF', '#FF6584', '#00b894', '#fdcb6e', '#d63031', '#e17055', '#a29bfe'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#a0a0a0' } }
                    }
                }
            });
        }
    </script>
</body>
</html>
