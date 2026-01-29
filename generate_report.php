<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$date = date('F d, Y');

// 1. Fetch Task Stats
$totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE user_id = $user_id")->fetchColumn();
$completedTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE user_id = $user_id AND status = 'completed'")->fetchColumn();
$pendingTasks = $totalTasks - $completedTasks;
$completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

// 2. Fetch Habit Stats
$stmt = $pdo->prepare("
    SELECT h.name, COUNT(l.id) as checks 
    FROM habits h 
    LEFT JOIN habit_logs l ON h.id = l.habit_id 
    WHERE h.user_id = ? 
    GROUP BY h.id
");
$stmt->execute([$user_id]);
$habits = $stmt->fetchAll();

// 3. Fetch Mood Stats (Last 30 Days)
$stmt = $pdo->prepare("SELECT AVG(mood_score) as avg_mood, COUNT(*) as entries FROM mood_logs WHERE user_id = ? AND log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$user_id]);
$moodStats = $stmt->fetch();
$avgMood = $moodStats['avg_mood'] ? round($moodStats['avg_mood'], 1) : 0;
$moodCount = $moodStats['entries'];

// 4. Fetch Finance Stats (Current Month)
$monthStart = date('Y-m-01');
$stmt = $pdo->prepare("SELECT type, SUM(amount) as total FROM expenses WHERE user_id = ? AND transaction_date >= ? GROUP BY type");
$stmt->execute([$user_id, $monthStart]);
$finances = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$income = $finances['income'] ?? 0;
$expense = $finances['expense'] ?? 0;
$balance = $income - $expense;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fiora Report - <?php echo $username; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #fdfdfd; color: #1e293b; max-width: 800px; margin: 0 auto; padding: 40px; }
        
        @media print {
            body { background: white; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }

        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 1.5rem; font-weight: 800; color: #6366f1; letter-spacing: -0.5px; }
        
        .section { margin-bottom: 40px; }
        .section-title { font-size: 1.2rem; font-weight: 700; color: #334155; margin-bottom: 15px; border-left: 4px solid #6366f1; padding-left: 10px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; text-align: center; }
        .stat-val { font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 5px; }
        .stat-label { font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { font-weight: 600; color: #64748b; font-size: 0.9rem; }
        
        .btn-print {
            position: fixed; bottom: 30px; right: 30px;
            background: #0f172a; color: white; border: none; padding: 12px 25px;
            border-radius: 50px; font-weight: 600; cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: transform 0.2s;
        }
        .btn-print:hover { transform: translateY(-2px); }
    </style>
</head>
<body>

    <a href="index.php" class="btn-print no-print" style="left: 30px; right: auto; background: #64748b; text-decoration: none;">← Back to Dashboard</a>
    <button class="btn-print no-print" onclick="window.print()">🖨️ Print / Save PDF</button>

    <div class="header">
        <div>
            <div class="logo">✦ FIORA REPORT</div>
            <div style="color: #64748b; margin-top: 5px;">Productivity & Wellness Summary</div>
        </div>
        <div style="text-align: right;">
            <div style="font-weight: 700; font-size: 1.1rem;"><?php echo htmlspecialchars($username); ?></div>
            <div style="color: #94a3b8; font-size: 0.9rem;"><?php echo $date; ?></div>
        </div>
    </div>

    <!-- Executive Summary -->
    <div class="section">
        <div class="section-title">🚀 Executive Summary</div>
        <p style="color: #475569; line-height: 1.6;">
            Here is your requested activity report. 
            You have completed <strong><?php echo $completedTasks; ?> tasks</strong> in total, with a completion rate of <strong><?php echo $completionRate; ?>%</strong>. 
            Your average mood this month is <strong><?php echo $avgMood; ?>/5</strong>.
            Financially, your net balance for the month stands at <strong>₹<?php echo number_format($balance); ?></strong>.
        </p>
    </div>

    <!-- Productivity -->
    <div class="section">
        <div class="section-title">✅ Productivity Stats</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-val"><?php echo $totalTasks; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #10b981;"><?php echo $completedTasks; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #f59e0b;"><?php echo $pendingTasks; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>

    <!-- Habits -->
    <div class="section">
        <div class="section-title">🔥 Habit Consistency</div>
        <?php if(empty($habits)): ?>
            <p style="color: #94a3b8; font-style: italic;">No active habits found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Habit Name</th>
                        <th style="text-align: right;">Total Check-ins</th>
                        <th style="text-align: right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($habits as $h): ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($h['name']); ?></td>
                        <td style="text-align: right;"><?php echo $h['checks']; ?></td>
                        <td style="text-align: right;">
                            <?php if($h['checks'] > 10) echo '<span style="color:#10b981">Excellent 🌟</span>'; 
                                  elseif($h['checks'] > 5) echo '<span style="color:#3b82f6">Good 👍</span>';
                                  else echo '<span style="color:#f59e0b">Building...</span>'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Finance -->
    <div class="section">
        <div class="section-title">💰 Financial Snapshot (This Month)</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-val" style="color: #10b981;">₹<?php echo number_format($income); ?></div>
                <div class="stat-label">Income</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #ef4444;">₹<?php echo number_format($expense); ?></div>
                <div class="stat-label">Expenses</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #6366f1;">₹<?php echo number_format($balance); ?></div>
                <div class="stat-label">Net Savings</div>
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 50px; color: #cbd5e1; font-size: 0.8rem;">
        Generated by Fiora System • <?php echo date('Y-m-d H:i:s'); ?>
    </div>

</body>
</html>
