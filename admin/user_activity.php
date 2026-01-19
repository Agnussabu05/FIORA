<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$page = 'user_activity';

// Aggregated Activity Feed Query
// We combine Moods, Books, and System Logs (and potentially Tasks)
$sql = "
    SELECT * FROM (
        -- 1. Mood Logs
        SELECT 
            ml.log_date as activity_time,
            'mood' as type,
            u.username,
            u.email,
            CONCAT('Logged mood: ', ml.mood_label, ' (', ml.mood_score, '/5)') as description,
            ml.note as details
        FROM mood_logs ml
        JOIN users u ON ml.user_id = u.id

        UNION ALL

        -- 2. Book Transactions
        SELECT 
            bt.transaction_date as activity_time,
            'book' as type,
            u.username,
            u.email,
            CONCAT(UPPER(bt.type), ' book for ₹', bt.price) as description,
            (SELECT title FROM books WHERE id = bt.book_id) as details
        FROM book_transactions bt
        -- For transactions, we usually track the 'actor' (Buyer or Borrower)
        JOIN users u ON bt.buyer_id = u.id

        UNION ALL
        
        -- 3. Task Creation (using created_at as proxy for activity)
        SELECT 
            t.created_at as activity_time,
            'task' as type,
            u.username,
            u.email,
            CONCAT('Created task: ', t.title) as description,
            CONCAT('Status: ', t.status) as details
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE u.username != 'demo'

        UNION ALL

        -- 4. System Logs (General)
        SELECT 
            sl.created_at as activity_time,
            'system' as type,
            u.username,
            u.email,
            sl.action as description,
            sl.details as details
        FROM system_logs sl
        JOIN users u ON sl.user_id = u.id
    ) as activity_feed
    ORDER BY activity_time DESC
    LIMIT 50
";

try {
    $stmt = $pdo->query($sql);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activities = [];
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Feed - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }
        .timeline-item {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 24px;
            top: 50px;
            bottom: -30px;
            width: 2px;
            background: #e5e7eb;
            z-index: 0;
        }
        .timeline-item:last-child::before { display: none; }
        
        .avatar-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 2px solid #fff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            z-index: 1;
        }
        
        .activity-card {
            flex: 1;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: 1px solid #f3f4f6;
        }
        .activity-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .username { font-weight: 700; color: #333; }
        .time { font-size: 0.85rem; color: #888; }
        .activity-type {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6C5DD3;
        }
        
        .type-mood { color: #F59E0B; }
        .type-book { color: #10B981; }
        .type-task { color: #3B82F6; }
        .type-system { color: #6B7280; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>User Activity Feed</h1>
                    <p>Real-time stream of user actions across the platform.</p>
                </div>
                <button onclick="location.reload()" class="btn btn-primary" style="padding: 8px 16px; display: flex; align-items: center; gap: 8px;">
                    <span>🔄</span> Refresh
                </button>
            </header>

            <div class="content-wrapper">
                <?php if (isset($error)): ?>
                    <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        SQL Error: <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="timeline">
                    <?php foreach ($activities as $act): ?>
                        <?php
                            $icon = '📌';
                            $typeClass = 'type-system';
                            switch($act['type']) {
                                case 'mood': $icon = '🌈'; $typeClass = 'type-mood'; break;
                                case 'book': $icon = '📚'; $typeClass = 'type-book'; break;
                                case 'task': $icon = '✅'; $typeClass = 'type-task'; break;
                                case 'system': $icon = '⚙️'; $typeClass = 'type-system'; break;
                            }
                        ?>
                        <div class="timeline-item">
                            <div class="avatar-circle">
                                <?php echo $icon; ?>
                            </div>
                            <div class="activity-card">
                                <span class="activity-type <?php echo $typeClass; ?>"><?php echo ucfirst($act['type']); ?></span>
                                <div class="activity-header">
                                    <span class="username"><?php echo htmlspecialchars($act['username']); ?></span>
                                    <span class="time"><?php echo date('M j, g:i a', strtotime($act['activity_time'])); ?></span>
                                </div>
                                <div style="font-size: 1.05em; color: #444; margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($act['description']); ?>
                                </div>
                                <?php if($act['details']): ?>
                                    <div style="font-size: 0.9em; color: #666; font-style: italic; background: #f9fafb; padding: 8px; border-radius: 6px; margin-top: 8px;">
                                        "<?php echo htmlspecialchars(mb_strimwidth($act['details'], 0, 100, "...")); ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($activities)): ?>
                        <div style="text-align: center; padding: 40px; color: #888;">
                            No recent activity found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
