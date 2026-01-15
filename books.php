<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page = 'books';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle "Buy/Borrow" Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_book') {
        // ... (Logic to be implemented in next step) ...
    }
}

// Fetch Books
$filter_category = $_GET['category'] ?? 'All';
$search_query = $_GET['search'] ?? '';

$sql = "SELECT b.*, u.username as owner_name FROM books b JOIN users u ON b.user_id = u.id WHERE b.status = 'Available'";
$params = [];

if ($filter_category !== 'All') {
    $sql .= " AND b.category = ?";
    $params[] = $filter_category;
}

if (!empty($search_query)) {
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$sql .= " ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Library - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
        }
        .book-card {
            background: rgba(255,255,255,0.6);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            background: rgba(255,255,255,0.8);
        }
        .book-cover {
            height: 280px;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 3rem;
            position: relative;
        }
        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .book-info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .book-title {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 4px;
            line-height: 1.3;
        }
        .book-author {
            color: #555;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .book-meta {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        .book-price {
            font-weight: 800;
            color: var(--success);
            font-size: 1.1rem;
        }
        .book-type-badge {
            background: #eee;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #444;
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: #fff;
            padding: 30px;
            border-radius: 20px;
            width: 450px;
            max-width: 90%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>The Library 📚</h1>
                    <p>Exchange knowledge. Buy, sell, or borrow used books.</p>
                </div>
                <button onclick="openModal()" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px;">
                    <span>+</span> List a Book
                </button>
            </header>

            <!-- Library Tabs -->
            <div class="tabs" style="margin-bottom: 25px;">
                <button onclick="switchTab('marketplace')" class="tab active" id="tab-marketplace">📚 Marketplace</button>
                <button onclick="switchTab('tracker')" class="tab" id="tab-tracker">📖 My Reading Tracker</button>
            </div>

            <!-- TAB 1: MARKETPLACE -->
            <div id="view-marketplace">


            <!-- Filters -->
            <div class="glass-card" style="margin-bottom: 30px; padding: 15px; display: flex; align-items: center; gap: 15px;">
                <div style="font-weight: 700; color: #444;">Filter:</div>
                <a href="?category=All" class="tag-pill" style="<?php echo $filter_category == 'All' ? 'background:var(--primary); color:white;' : ''; ?>">All</a>
                <a href="?category=Textbook" class="tag-pill" style="<?php echo $filter_category == 'Textbook' ? 'background:var(--primary); color:white;' : ''; ?>">Textbooks</a>
                <a href="?category=Fiction" class="tag-pill" style="<?php echo $filter_category == 'Fiction' ? 'background:var(--primary); color:white;' : ''; ?>">Fiction</a>
                <a href="?category=Self-Help" class="tag-pill" style="<?php echo $filter_category == 'Self-Help' ? 'background:var(--primary); color:white;' : ''; ?>">Self-Help</a>
                
                <form style="margin-left: auto; display: flex;">
                    <input type="text" name="search" placeholder="Search title..." class="form-input" style="padding: 10px; width: 200px;" value="<?php echo htmlspecialchars($search_query); ?>">
                </form>
            </div>

            <!-- Grid -->
            <?php if (empty($books)): ?>
                <div style="text-align: center; padding: 60px; color: #666;">
                    <h2>🍃</h2>
                    <p>No books found in the library properly yet.</p>
                    <button onclick="openModal()" style="margin-top: 10px; text-decoration: underline; background: none; border: none; cursor: pointer; color: var(--primary);">Be the first to list one!</button>
                </div>
            <?php else: ?>
                <div class="books-grid">
                    <?php foreach ($books as $book): ?>
                        <div class="book-card">
                            <div class="book-cover">
                                <?php if ($book['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($book['image_path']); ?>" alt="Cover">
                                <?php else: ?>
                                    📖
                                <?php endif; ?>
                            </div>
                            <div class="book-info">
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                                <div style="font-size: 0.8rem; color: #888; margin-bottom: 10px;">
                                    Seller: <b><?php echo htmlspecialchars($book['owner_name']); ?></b>
                                </div>
                                <div class="book-meta">
                                    <div class="book-price">
                                        <?php echo $book['price'] > 0 ? '$' . $book['price'] : 'Free'; ?>
                                    </div>
                                    <div class="book-type-badge">
                                        <?php echo $book['type']; ?>
                                    </div>
                                </div>
                                <button onclick="viewBook(<?php echo $book['id']; ?>)" class="btn btn-secondary" style="width: 100%; margin-top: 15px; font-size: 0.9rem;">View Details</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>
            <?php endif; ?>
            </div>

            <!-- TAB 2: READING TRACKER (Integrated) -->
            <div id="view-tracker" style="display: none;">
                <iframe src="reading.php?embedded=true" style="width: 100%; height: 80vh; border: none; overflow: hidden; border-radius: 20px;"></iframe>
            </div>

        </main>
    </div>

    <!-- Script for Tabs -->
    <script>
        function switchTab(tabName) {
            // Hide all views
            document.getElementById('view-marketplace').style.display = 'none';
            document.getElementById('view-tracker').style.display = 'none';
            
            // Deactivate buttons
            document.getElementById('tab-marketplace').classList.remove('active');
            document.getElementById('tab-tracker').classList.remove('active');
            
            // Show selected
            document.getElementById('view-' + tabName).style.display = 'block';
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>
        </main>
    </div>

    <!-- Add Book Modal -->
    <div id="addBookModal" class="modal-overlay">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">List a Book 📖</h2>
            <form action="api/books_action.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_book">
                
                <div class="form-group">
                    <label>Book Title</label>
                    <input type="text" name="title" required class="form-input" placeholder="e.g. Atomic Habits">
                </div>
                
                <div class="form-group">
                    <label>Author</label>
                    <input type="text" name="author" required class="form-input" placeholder="e.g. James Clear">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-input" style="height: 48px;">
                            <option>Textbook</option>
                            <option>Fiction</option>
                            <option>Non-Fiction</option>
                            <option>Self-Help</option>
                            <option>Sci-Fi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition" class="form-input" style="height: 48px;">
                            <option>New</option>
                            <option>Like New</option>
                            <option>Good</option>
                            <option>Fair</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Type</label>
                    <div style="display: flex; gap: 15px; margin-top: 5px;">
                        <label><input type="radio" name="type" value="Sell" checked onclick="togglePrice(true)"> Sell</label>
                        <label><input type="radio" name="type" value="Borrow" onclick="togglePrice(false)"> Lend (Free)</label>
                    </div>
                </div>

                <div class="form-group" id="priceGroup">
                    <label>Price ($)</label>
                    <input type="number" name="price" step="0.01" class="form-input" placeholder="10.00">
                </div>
                
                 <div class="form-group">
                    <label>Cover Image</label>
                    <input type="file" name="image" accept="image/*" class="form-input">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">List Book</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('addBookModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('addBookModal').style.display = 'none';
        }
        function togglePrice(show) {
            const group = document.getElementById('priceGroup');
            const input = group.querySelector('input');
            if(show) {
                group.style.opacity = '1';
                input.disabled = false;
            } else {
                group.style.opacity = '0.5';
                input.disabled = true;
                input.value = '0.00';
            }
        }
        
        // Close modal on outside click
        document.getElementById('addBookModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
