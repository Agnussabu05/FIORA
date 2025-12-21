<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Simulated Export Logic
if (isset($_POST['export_type'])) {
    $type = $_POST['export_type'];
    // In a real app, this would generate a CSV and force download
    // Here we'll just log it
    $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'export_report', "Exported $type report"]);
    $msg = "Success: $type report has been generated and is ready for download (Simulated).";
}

$page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-top: 20px; }
        .report-card { 
            background: rgba(255, 255, 255, 0.05); 
            padding: 24px; 
            border-radius: 20px; 
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s ease;
        }
        .report-card:hover { background: rgba(255, 255, 255, 0.08); transform: translateY(-3px); }
        .report-icon { font-size: 2.5rem; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>Reports & Analytics</h1>
                    <p>Export system data for performance evaluation.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if(isset($msg)): ?>
                    <div style="background: rgba(16, 185, 129, 0.1); color: #10B981; padding: 15px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #10B981;">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <div class="report-grid">
                    <div class="report-card">
                        <div class="report-icon">👥</div>
                        <h3>User Activity</h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 10px 0 20px;">Detailed log of user logins and sessions.</p>
                        <form method="POST"><input type="hidden" name="export_type" value="User Activity"><button type="submit" class="btn btn-primary" style="width: 100%;">Export CSV</button></form>
                    </div>
                    
                    <div class="report-card">
                        <div class="report-icon">✅</div>
                        <h3>Task Analytics</h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 10px 0 20px;">Completion rates and productivity trends.</p>
                        <form method="POST"><input type="hidden" name="export_type" value="Task Analytics"><button type="submit" class="btn btn-primary" style="width: 100%;">Export CSV</button></form>
                    </div>
                    
                    <div class="report-card">
                        <div class="report-icon">🔥</div>
                        <h3>Habit Stats</h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 10px 0 20px;">Streak data and consistency reports.</p>
                        <form method="POST"><input type="hidden" name="export_type" value="Habit Stats"><button type="submit" class="btn btn-primary" style="width: 100%;">Export CSV</button></form>
                    </div>

                    <div class="report-card">
                        <div class="report-icon">💰</div>
                        <h3>Expense Overview</h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 10px 0 20px;">Anonymized spending patterns across users.</p>
                        <form method="POST"><input type="hidden" name="export_type" value="Expense Overview"><button type="submit" class="btn btn-primary" style="width: 100%;">Export CSV</button></form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
