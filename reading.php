<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page = 'reading';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle Add Book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $total_pages = (int)$_POST['total_pages'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("INSERT INTO books (user_id, title, author, total_pages, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $author, $total_pages, $status]);
    header("Location: reading.php");
    exit;
}

// Handle Update Progress
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    $book_id = $_POST['book_id'];
    $current_page = (int)$_POST['current_page'];
    
    // Check if reached total pages to auto-complete
    $stmt = $pdo->prepare("SELECT total_pages FROM books WHERE id = ? AND user_id = ?");
    $stmt->execute([$book_id, $user_id]);
    $total = $stmt->fetchColumn();
    
    $new_status = 'reading';
    if ($current_page >= $total && $total > 0) {
        $current_page = $total;
        $new_status = 'completed';
    }
    
    $stmt = $pdo->prepare("UPDATE books SET current_page = ?, status = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$current_page, $new_status, $book_id, $user_id]);
    echo json_encode(['success' => true]);
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM books WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: reading.php");
    exit;
}

// Fetch Books
$filter_status = $_GET['status'] ?? 'reading';
$stmt = $pdo->prepare("SELECT * FROM books WHERE user_id = ? AND status = ? ORDER BY created_at DESC");
$stmt->execute([$user_id, $filter_status]);
$books = $stmt->fetchAll();

// Stats
$statsStmt = $pdo->prepare("SELECT 
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'reading' THEN 1 END) as reading,
    SUM(current_page) as total_pages_read
    FROM books WHERE user_id = ?");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading Tracker - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reading-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .book-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); border-color: var(--primary); }
        
        .progress-container {
            background: rgba(0,0,0,0.05);
            height: 10px;
            border-radius: 10px;
            margin: 10px 0;
            overflow: hidden;
            position: relative;
        }
        .progress-bar {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .status-reading { background: #e3f2fd; color: #1976d2; }
        .status-completed { background: #e8f5e9; color: #2e7d32; }
        .status-wishlist { background: #fff3e0; color: #ef6c00; }

        .tabs {
            display: flex; gap: 10px; margin-bottom: 30px;
        }
        .tab {
            padding: 10px 25px;
            border-radius: 15px;
            background: rgba(255,255,255,0.4);
            border: 1px solid var(--glass-border);
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            transition: all 0.3s;
        }
        .tab.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .pages-input {
            width: 70px;
            padding: 5px 10px;
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            text-align: center;
            font-family: 'JetBrains Mono', monospace;
            font-weight: bold;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            padding: 20px;
            text-align: center;
        }

        /* Aesthetics */
        .book-title { font-size: 1.25rem; font-weight: 800; color: #000 !important; line-height: 1.3; }
        .book-author { font-size: 0.95rem; color: #222; font-weight: 600; }
        .page-counter { font-family: 'JetBrains Mono', monospace; font-size: 0.95rem; color: #000; font-weight: 700; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Reading Tracker 📖</h1>
                    <p style="color: #444; font-weight: 500;">Your digital library and progress tracker.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ Add New Book</button>
            </header>

            <div class="stats-grid">
                <div class="glass-card stat-card" style="border: 1px solid var(--glass-border);">
                    <div style="font-size: 0.85rem; color: #333; font-weight: 800;">BOOKS READ</div>
                    <div style="font-size: 2.2rem; font-weight: 800; color: var(--success);"><?php echo $stats['completed'] ?: 0; ?></div>
                </div>
                <div class="glass-card stat-card" style="border: 1px solid var(--glass-border);">
                    <div style="font-size: 0.85rem; color: #333; font-weight: 800;">CURRENTLY READING</div>
                    <div style="font-size: 2.2rem; font-weight: 800; color: var(--primary);"><?php echo $stats['reading'] ?: 0; ?></div>
                </div>
                <div class="glass-card stat-card" style="border: 1px solid var(--glass-border);">
                    <div style="font-size: 0.85rem; color: #333; font-weight: 800;">PAGES COMPLETED</div>
                    <div style="font-size: 2.2rem; font-weight: 800; color: #000; font-family: 'JetBrains Mono';"><?php echo $stats['total_pages_read'] ?: 0; ?></div>
                </div>
            </div>

            <div class="tabs">
                <a href="?status=reading" class="tab <?php echo $filter_status == 'reading' ? 'active' : ''; ?>">Currently Reading</a>
                <a href="?status=completed" class="tab <?php echo $filter_status == 'completed' ? 'active' : ''; ?>">Finished</a>
                <a href="?status=wishlist" class="tab <?php echo $filter_status == 'wishlist' ? 'active' : ''; ?>">Wishlist</a>
            </div>

            <div class="reading-grid">
                <?php if (count($books) > 0): ?>
                    <?php foreach($books as $book): 
                        $pct = ($book['total_pages'] > 0) ? round(($book['current_page'] / $book['total_pages']) * 100) : 0;
                    ?>
                        <div class="book-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                    <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                                </div>
                                <div class="status-badge status-<?php echo $book['status']; ?>">
                                    <?php echo $book['status']; ?>
                                </div>
                            </div>

                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?php echo $pct; ?>%;"></div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div class="page-counter">
                                    <?php if ($book['status'] !== 'wishlist'): ?>
                                        <input type="number" 
                                               class="pages-input" 
                                               value="<?php echo $book['current_page']; ?>" 
                                               onchange="updateProgress(<?php echo $book['id']; ?>, this.value)"
                                               max="<?php echo $book['total_pages']; ?>"
                                               min="0">
                                        / <?php echo $book['total_pages']; ?> pages
                                    <?php else: ?>
                                        Wishlisted
                                    <?php endif; ?>
                                </div>
                                <div style="font-weight: 900; font-family: 'JetBrains Mono'; color: #000;">
                                    <?php echo $pct; ?>%
                                </div>
                            </div>

                            <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                                <a href="?delete=<?php echo $book['id']; ?>" class="btn" style="background: rgba(192, 108, 108, 0.1); color: var(--danger); padding: 5px 10px;" onclick="return confirm('Remove this book from library?')">Remove</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
                        <div style="font-size: 4rem; margin-bottom: 20px;">📚</div>
                        <h3 style="color: #666;">No books in this section.</h3>
                        <p style="color: #888;">Ready to start your next adventure?</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="bookModal">
        <div class="glass-card" style="width: 400px; padding: 30px;">
            <h3 style="margin-bottom: 25px;">Add to Library</h3>
            <form action="reading.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Book Title</label>
                    <input type="text" name="title" class="form-input" required placeholder="The Alchemist">
                </div>
                <div class="form-group">
                    <label>Author</label>
                    <input type="text" name="author" class="form-input" required placeholder="Paulo Coelho">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Total Pages</label>
                        <input type="number" name="total_pages" class="form-input" value="200" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-input">
                            <option value="reading">Currently Reading</option>
                            <option value="wishlist">Wishlist</option>
                            <option value="completed">Finished</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="color: #000 !important; font-weight: 800;">Close</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">Add Book</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('bookModal').classList.add('active'); }
        function closeModal() { document.getElementById('bookModal').classList.remove('active'); }
        
        async function updateProgress(bookId, currentPage) {
            const formData = new FormData();
            formData.append('action', 'update_progress');
            formData.append('book_id', bookId);
            formData.append('current_page', currentPage);

            try {
                const response = await fetch('reading.php', {
                    method: 'POST',
                    body: formData
                });
                if (response.ok) {
                    location.reload(); // Quick refresh to update bars and stats
                }
            } catch (err) {
                console.error(err);
            }
        }
    </script>
</body>
</html>
