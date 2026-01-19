<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['user_id'])) {
    die("User ID required");
}

$target_user_id = $_GET['user_id'];

// Get Username
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$user = $stmt->fetch();
$target_username = $user ? $user['username'] : 'Unknown User';

// Fetch all mood logs
$stmt = $pdo->prepare("SELECT * FROM mood_logs WHERE user_id = ? ORDER BY log_date DESC");
$stmt->execute([$target_user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats & Prediction
$totalLogs = count($logs);
$avgMood = 0;
$prediction = "Insufficient data";
$tips = ["User needs to log more data."];
$trendColor = "#999";

if ($totalLogs > 0) {
    $sum = array_sum(array_column($logs, 'mood_score'));
    $avgMood = round($sum / $totalLogs, 1);
    
    // Simple Linear Regression for Trend (Last 7 logs)
    $recentLogs = array_slice($logs, 0, 7); // Get newest first
    $recentLogs = array_reverse($recentLogs); // Reorder to Oldest -> Newest
    
    if (count($recentLogs) >= 3) {
        $n = count($recentLogs);
        $x = []; // Time (0, 1, 2...)
        $y = []; // Scores
        
        foreach ($recentLogs as $i => $log) {
            $x[] = $i;
            $y[] = $log['mood_score'];
        }
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += ($x[$i] * $y[$i]);
            $sumXX += ($x[$i] * $x[$i]);
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        
        if ($slope > 0.1) {
            $prediction = "Trending Upwards 🚀";
            $trendColor = "#388e3c"; // Green
            $tips = [
                "User is building positive momentum.",
                "Advise them to identify what went well.",
                "Encourage sharing good vibes."
            ];
            $monthOutlook = "Based on their upward momentum, next month looks promising! They could consistently reach 'Good' or 'Incredible' states.";
        } elseif ($slope < -0.1) {
            $prediction = "Trending Downwards 📉";
            $trendColor = "#d32f2f"; // Red
            $tips = [
                "Seems like a tough patch.",
                "Check notes for recurring stressors.",
                "Suggest rest and sleep."
            ];
            $monthOutlook = "The current trend suggests next month might be challenging. It's a sign they might need to slow down.";
        } else {
            $prediction = "Stable / Neutral ⚓";
            $trendColor = "#1976d2"; // Blue
            $tips = [
                "Stability is good. Suggest small positive habits.",
                "Maintain routine.",
                "Reflect on turning 'okay' days to 'good' days."
            ];
            $monthOutlook = "User mood is stable. Expect a steady month ahead.";
        }
        
        // Calculate Volatility
        $variance = 0;
        foreach ($recentLogs as $log) {
            $variance += pow($log['mood_score'] - $avgMood, 2);
        }
        $stdDev = sqrt($variance / count($recentLogs));
        
        if ($stdDev > 1.0) {
            $monthOutlook .= " <br><br><strong>Note:</strong> High mood fluctuation detected.";
        }
    } else {
        $prediction = "Needs more data (3+ logs)";
        $tips = ["Wait for more user logs."];
        $monthOutlook = "Insufficient data for forecast.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Report - <?php echo htmlspecialchars($target_username); ?></title>
    <style>
        body { font-family: 'Inter', sans-serif; color: #333; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 40px; background: #f4f6f8; }
        .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        h1 { border-bottom: 2px solid #eee; padding-bottom: 20px; color: #6C5DD3; margin-top: 0; }
        .stats { display: flex; gap: 40px; margin-bottom: 40px; background: #f8f9fa; padding: 20px; border-radius: 10px; }
        .stat-item strong { display: block; font-size: 2em; color: #333; }
        .stat-item span { color: #666; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #ddd; color: #666; }
        td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: top; }
        .mood-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold; }
        .mood-1 { background: #ffe0e0; color: #d32f2f; }
        .mood-2 { background: #e3f2fd; color: #1976d2; }
        .mood-3 { background: #f3e5f5; color: #7b1fa2; }
        .mood-4 { background: #e8f5e9; color: #388e3c; }
        .mood-5 { background: #fff3e0; color: #f57c00; }
        .date { color: #666; font-size: 0.9em; white-space: nowrap; }
        .note { font-style: italic; color: #555; }
        @media print {
            body { padding: 0; background: white; }
            .container { box-shadow: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="mood_users.php" style="text-decoration: none; color: #6C5DD3; font-weight: 600; font-size: 0.9em;">&larr; Back to User List</a>
        <div>
            <span style="background: #eee; padding: 5px 10px; border-radius: 5px; font-size: 0.8em; margin-right: 10px;">ADMIN VIEW</span>
            <button onclick="window.print()" style="background: #6C5DD3; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 600;">Print Report</button>
        </div>
    </div>

    <div class="container">
        <h1>Mood Report: <?php echo htmlspecialchars($target_username); ?></h1>
        
        <div class="stats">
            <div class="stat-item">
                <strong><?php echo $totalLogs; ?></strong>
                <span>Total Check-ins</span>
            </div>
            <div class="stat-item">
                <strong><?php echo $avgMood; ?>/5</strong>
                <span>Average Mood</span>
            </div>
            <div class="stat-item">
                <strong style="color: <?php echo $trendColor; ?>"><?php echo $prediction; ?></strong>
                <span>Forecast</span>
            </div>
        </div>

        <!-- Next Month Projection Card -->
        <div style="background: white; border-left: 5px solid #6C5DD3; border-radius: 8px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0; color: #6C5DD3; display: flex; align-items: center; gap: 10px;">
                <span>📅</span> Next Month Forecast
            </h3>
            <p style="color: #444; font-size: 1.1em; line-height: 1.6; font-weight: 500;">
                <?php echo isset($monthOutlook) ? $monthOutlook : 'Insufficient data for forecast.'; ?>
            </p>
        </div>

        <!-- Tips Card -->
        <div style="background: white; border: 1px solid #eee; border-radius: 10px; padding: 25px; margin-bottom: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
            <h3 style="margin-top: 0; color: #444;">💡 Analysis</h3>
            <p style="color: #666; margin-bottom: 15px; font-size: 0.9em;">System derived suggestions for this user:</p>
            <ul style="color: #555; list-style-type: none; padding: 0;">
                <?php foreach ($tips as $tip): ?>
                    <li style="margin-bottom: 12px; display: flex; align-items: start; gap: 10px;">
                        <span style="color: #6C5DD3; margin-top: 3px;">✨</span> 
                        <span><?php echo $tip; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Mood</th>
                    <th>Sleep</th>
                    <th>"What's on your mind?"</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="date"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></td>
                    <td>
                        <span class="mood-badge mood-<?php echo $log['mood_score']; ?>">
                            <?php echo htmlspecialchars($log['mood_label']); ?>
                            (<?php echo $log['mood_score']; ?>)
                        </span>
                    </td>
                    <td><?php echo $log['sleep_hours']; ?>h</td>
                    <td class="note"><?php echo !empty($log['note']) ? htmlspecialchars($log['note']) : '<span style="color:#ccc">-</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
