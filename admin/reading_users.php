<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$page = 'modules'; // Highlight 'Modules' in sidebar

// Fetch User Stats
$sql = "SELECT 
            u.id, 
            u.username, 
            u.email,
            (SELECT COUNT(*) FROM books b WHERE b.user_id = u.id AND b.status = 'Available') as active_listings,
            (SELECT COUNT(*) FROM book_transactions bt WHERE bt.seller_id = u.id AND bt.type = 'buy') as books_sold,
            (SELECT COUNT(*) FROM book_transactions bt WHERE bt.buyer_id = u.id AND bt.type = 'buy') as books_bought,
            (SELECT COUNT(*) FROM book_transactions bt WHERE bt.seller_id = u.id AND bt.type = 'borrow') as books_lent,
            (SELECT COUNT(*) FROM book_transactions bt WHERE bt.buyer_id = u.id AND bt.type = 'borrow') as books_borrowed
        FROM users u 
        WHERE u.role != 'admin' -- Optional: hide other admins?
        ORDER BY active_listings DESC";
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
        // Fetch their books
        $stmt = $pdo->prepare("SELECT * FROM books WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$uid]);
        $user_books = $stmt->fetchAll();
        
        // Fetch their transaction history (as Buyer/Borrower)
        // Fetch all transaction history (Buy, Sell, Borrow, Lend)
        $stmt = $pdo->prepare("
            SELECT bt.*, b.title, 
                   s.username as seller_name, 
                   byr.username as buyer_name
            FROM book_transactions bt
            JOIN books b ON bt.book_id = b.id
            JOIN users s ON bt.seller_id = s.id
            JOIN users byr ON bt.buyer_id = byr.id
            WHERE bt.seller_id = ? OR bt.buyer_id = ?
            ORDER BY bt.transaction_date DESC
        ");
        $stmt->execute([$uid, $uid]);
        $user_history = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading Activity - Fiora Admin</title>
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
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .badge-blue { background: #e0f2fe; color: #0284c7; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-purple { background: #f3e8ff; color: #9333ea; }
        
        /* Modal-like overlay for details */
        .details-panel {
            position: fixed;
            top: 0; right: 0;
            width: 500px;
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
        }
        
        .book-list-item {
            display: flex;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .book-mini-cover {
            width: 40px;
            height: 60px;
            background: #ddd;
            border-radius: 4px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div>
                    <a href="modules.php" style="text-decoration: none; color: #666;">&larr; Back to Modules</a>
                    <h1 style="margin-top: 5px;">Reading Module Users</h1>
                    <p>Track book exchange activity per user.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Active Listings</th>
                            <th>Sold</th>
                            <th>Bought</th>
                            <th>Lent</th>
                            <th>Borrowed</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong><br>
                                <span style="font-size: 0.8em; color: #888;"><?php echo htmlspecialchars($u['email']); ?></span>
                            </td>
                            <td><span class="badge badge-blue"><?php echo $u['active_listings']; ?></span></td>
                            <td><span class="badge badge-green"><?php echo $u['books_sold']; ?></span></td>
                            <td><span class="badge" style="background: #fffbeb; color: #b45309;"><?php echo $u['books_bought']; ?></span></td>
                            <td><span class="badge badge-purple"><?php echo $u['books_lent']; ?></span></td>
                            <td><?php echo $u['books_borrowed']; ?></td>
                            <td>
                                <a href="?view_user=<?php echo $u['id']; ?>" class="btn-small" style="color: var(--primary); text-decoration: none; font-weight: 500;">View Details</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($details_user): ?>
            <div class="details-panel open">
                <a href="reading_users.php" class="close-btn">&times;</a>
                <h2><?php echo htmlspecialchars($details_user['username']); ?>'s Library</h2>
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                
                <h3>Currently Listed Books</h3>
                <?php if (empty($user_books)): ?>
                    <p style="color: #999;">No books listed.</p>
                <?php else: ?>
                    <?php foreach ($user_books as $book): ?>
                    <div class="book-list-item">
                        <?php if(isset($book['image_path']) && $book['image_path']): ?>
                            <img src="../<?php echo htmlspecialchars($book['image_path']); ?>" class="book-mini-cover">
                        <?php else: ?>
                            <div class="book-mini-cover" style="display:flex;align-items:center;justify-content:center;">📖</div>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                            <small class="badge" style="background:#eee;"><?php echo $book['status']; ?></small>
                            <small>Price: <?php echo $book['price'] > 0 ? '₹'.$book['price'] : 'Free'; ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <h3 style="margin-top: 30px;">Transaction History</h3>
                <?php if (empty($user_history)): ?>
                    <p style="color: #999;">No transactions found.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($user_history as $h): ?>
                        <?php 
                            $is_seller = ($h['seller_id'] == $uid);
                            $formatted_type = '';
                            $color = '#666';
                            $other_party = '';
                            
                            if ($h['type'] == 'buy') {
                                if ($is_seller) {
                                    $formatted_type = 'SOLD';
                                    $color = '#10B981'; // Green
                                    $other_party = 'To: ' . htmlspecialchars($h['buyer_name']);
                                } else {
                                    $formatted_type = 'BOUGHT';
                                    $color = '#F59E0B'; // Orange
                                    $other_party = 'From: ' . htmlspecialchars($h['seller_name']);
                                }
                            } elseif ($h['type'] == 'borrow') {
                                if ($is_seller) {
                                    $formatted_type = 'LENT';
                                    $color = '#8B5CF6'; // Purple
                                    $other_party = 'To: ' . htmlspecialchars($h['buyer_name']);
                                } else {
                                    $formatted_type = 'BORROWED';
                                    $color = '#3B82F6'; // Blue
                                    $other_party = 'From: ' . htmlspecialchars($h['seller_name']);
                                }
                            } else {
                                $formatted_type = strtoupper($h['type']);
                            }
                        ?>
                        <li style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:700; font-size: 0.8rem; color: <?php echo $color; ?>;"><?php echo $formatted_type; ?></span>
                                <span style="font-weight:600; font-size: 0.9rem; color: #444;">₹<?php echo number_format($h['price'], 2); ?></span>
                            </div>
                            <div style="font-weight: 600; margin-top: 2px;"><?php echo htmlspecialchars($h['title']); ?></div>
                            <div style="font-size: 0.85rem; color: #888; margin-top: 4px;">
                                <?php echo $other_party; ?> • <?php echo date('M d, Y', strtotime($h['transaction_date'])); ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>
