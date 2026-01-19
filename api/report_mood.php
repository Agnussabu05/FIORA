<?php
// api/report_mood.php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch all mood logs
$stmt = $pdo->prepare("SELECT * FROM mood_logs WHERE user_id = ? ORDER BY log_date DESC");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats & Prediction
$totalLogs = count($logs);
$avgMood = 0;
$prediction = "Insufficient data";
$tips = ["Continue logging to unlock predictions!"];
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
                "You're building positive momentum! Keep doing what you're doing.",
                "Identify what went well this week and repeat it.",
                "Share your good vibes with a friend or tribe member."
            ];
            $monthOutlook = "Based on your upward momentum, next month looks promising! You could consistently reach 'Good' or 'Incredible' states if you maintain your current habits.";
        } elseif ($slope < -0.1) {
            $prediction = "Trending Downwards 📉";
            $trendColor = "#d32f2f"; // Red
            $tips = [
                "It looks like a tough patch. Prioritize rest and sleep this week.",
                "Check your 'What's on your mind' notes for recurring stressors.",
                "Try the 4-7-8 breathing technique before bed."
            ];
            $monthOutlook = "The current trend suggests next month might be challenging. It's a sign to slow down and focus on self-care now to prevent burnout.";
        } else {
            $prediction = "Stable / Neutral ⚓";
            $trendColor = "#1976d2"; // Blue
            $tips = [
                "Stability is good! Try introducing one small new positive habit.",
                "Maintain your routine but look for micro-moments of joy.",
                "Reflect: What would turn a 'okay' day into a 'good' day?"
            ];
            $monthOutlook = "Your mood is stable. Expect a steady month ahead. To see more 'Good' days, try tweaking one part of your daily routine.";
        }
        
        // Calculate Volatility (Standard Deviation)
        $variance = 0;
        foreach ($recentLogs as $log) {
            $variance += pow($log['mood_score'] - $avgMood, 2);
        }
        $stdDev = sqrt($variance / count($recentLogs));
        
        if ($stdDev > 1.0) {
            $monthOutlook .= " <br><br><strong>Note:</strong> Your mood has been fluctuating a lot recently. Expect some ups and downs—that's completely normal.";
        }
    } else {
        $prediction = "Needs more data (3+ logs)";
        $tips = ["Log your mood for a few more days to see trends!"];
        $monthOutlook = "Log more entries to unlock your monthly forecast! 📅";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mood Report - <?php echo htmlspecialchars($username); ?></title>
    <style>
        body { font-family: 'Inter', sans-serif; color: #333; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 40px; }
        h1 { border-bottom: 2px solid #eee; padding-bottom: 20px; color: #6C5DD3; }
        .stats { display: flex; gap: 40px; margin-bottom: 40px; background: #f8f9fa; padding: 20px; border-radius: 10px; }
        .stat-item strong { display: block; font-size: 2em; color: #333; }
        .stat-item span { color: #666; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #ddd; color: #666; }
        td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: top; }
        .mood-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold; }
        .mood-1 { background: #ffe0e0; color: #d32f2f; } /* Stressed */
        .mood-2 { background: #e3f2fd; color: #1976d2; } /* Sad */
        .mood-3 { background: #f3e5f5; color: #7b1fa2; } /* Neutral */
        .mood-4 { background: #e8f5e9; color: #388e3c; } /* Good */
        .mood-5 { background: #fff3e0; color: #f57c00; } /* Incredible */
        .date { color: #666; font-size: 0.9em; white-space: nowrap; }
        .note { font-style: italic; color: #555; }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="../mood.php" style="text-decoration: none; color: #6C5DD3; font-weight: 600; font-size: 0.9em;">&larr; Back to Dashboard</a>
        <button onclick="window.print()" style="background: #6C5DD3; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 600;">Print / Save as PDF</button>
    </div>

    <h1>Mood Report: <?php echo htmlspecialchars($username); ?></h1>
    
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
            <?php echo isset($monthOutlook) ? $monthOutlook : 'Log 3+ days to see your monthly forecast!'; ?>
        </p>
    </div>

    <!-- Tips Card -->
    <div style="background: white; border: 1px solid #eee; border-radius: 10px; padding: 25px; margin-bottom: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        <h3 style="margin-top: 0; color: #444;">💡 Personalized Actions</h3>
        <p style="color: #666; margin-bottom: 15px; font-size: 0.9em;">Based on your recent trend (slope: <?php echo isset($slope) ? round($slope, 2) : 'N/A'; ?>):</p>
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
</body>
</html>
