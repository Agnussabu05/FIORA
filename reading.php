<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Prevent browser caching of dynamic content to avoid ghost duplicates on back navigation
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$page = 'reading';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// View Handling
$view = $_GET['view'] ?? 'tracker'; // 'tracker' or 'marketplace'

// --- BACKEND LOGIC ---

// Auto-Check Overdue (Simple Lazy Check)
// If I have borrowed books past due date, alert me.
$overdue_stmt = $pdo->prepare("SELECT title, due_date FROM books WHERE user_id = ? AND status = 'reading' AND borrowed_from IS NOT NULL AND due_date < NOW()");
$overdue_stmt->execute([$user_id]);
$overdue_books = $overdue_stmt->fetchAll();

foreach ($overdue_books as $ob) {
    // Check if we already notified today
    // Optimization: Just show a flash message or rely on visual cues
    // For now, we populate $overdue_books to use in UI warnings
}

// Fetch Active Borrows (Move logic up for use in modal) - ALREADY DONE IN STEP 68, just ensuring no duplicates if I messed up step 68. 
// Actually step 68 was in the Data Fetching section (Line 290+). This assumes I am only editing the modal HTML.



// AJAX: Search Suggestions
if (isset($_GET['action']) && $_GET['action'] === 'search_suggest') {
    $term = $_GET['term'] ?? '';
    header('Content-Type: application/json');
    if (strlen($term) < 2) {
        echo json_encode([]);
        exit;
    }
    // Search both books and authors
    $stmt = $pdo->prepare("SELECT DISTINCT title, author FROM books WHERE (title LIKE ? OR author LIKE ?) AND (status = 'Available' OR borrowed_from IS NOT NULL) LIMIT 5");
    $stmt->execute(["%$term%", "%$term%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Add Personal Book (Tracker)
    if ($action === 'add_personal') {
        $title = $_POST['title'];
        $author = $_POST['author'];
        $total_pages = (int)$_POST['total_pages'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("INSERT INTO books (user_id, title, author, total_pages, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $author, $total_pages, $status]);
        header("Location: reading.php?view=tracker");
        exit;
    }

    // 1b. Set Target Date
    if ($action === 'set_target') {
        $book_id = $_POST['book_id'];
        $target_date = $_POST['target_date'];
        
        $stmt = $pdo->prepare("UPDATE books SET target_date = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$target_date, $book_id, $user_id]);
        header("Location: reading.php?view=tracker");
        exit;
    }
    
    // 2. Update Progress
    if ($action === 'update_progress') {
        $book_id = $_POST['book_id'];
        $current_page = (int)$_POST['current_page'];
        
        // Auto-complete logic
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
    
    // 2b. Update Total Pages
    if ($action === 'set_total_pages') {
        $book_id = $_POST['book_id'];
        $total_pages = (int)$_POST['total_pages'];
        
        $stmt = $pdo->prepare("UPDATE books SET total_pages = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$total_pages, $book_id, $user_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // List/Relist Book from Tracker (Tracker -> Available)
    if ($action === 'relist_book') {
        $book_id = $_POST['book_id'];
        $type = $_POST['type'];
        $price = ($type === 'Borrow') ? 0.00 : (float)$_POST['price'];
        
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $total_pages = (int)$_POST['total_pages'];
        $category = $_POST['category'];
        $condition = $_POST['condition'];
        $duration = (int)$_POST['duration'];
        
        $stmt = $pdo->prepare("UPDATE books SET status = 'Available', type = ?, price = ?, title = ?, author = ?, total_pages = ?, category = ?, `condition` = ?, lending_duration = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$type, $price, $title, $author, $total_pages, $category, $condition, $duration, $book_id, $user_id]);
        
        $_SESSION['flash_msg'] = "Book listed in Marketplace! 🚀";
        header("Location: reading.php?view=marketplace&filter=listings");
        exit;
    }

    // Start Reading (Wishlist -> Reading)
    if ($action === 'start_reading') {
        $book_id = $_POST['book_id'];
        // Ensure book belongs to user
        $stmt = $pdo->prepare("UPDATE books SET status = 'reading', current_page = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$book_id, $user_id]);
        
        // Optional: Set a default target date? No, let user set it.
        $_SESSION['flash_msg'] = "Book moved to Reading! 📖 Time to dive in.";
        header("Location: reading.php?view=tracker");
        exit;
    }

    // 3. Sell/List Book (Marketplace)
    if ($action === 'sell_book') {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $category = $_POST['category'];
        if ($category === 'Other' && !empty($_POST['custom_category'])) {
            $category = trim($_POST['custom_category']);
        }
        $condition = $_POST['condition'];
        $type = $_POST['type']; // Sell or Borrow
        $price = ($type === 'Borrow') ? 0.00 : (float)$_POST['price'];
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        $lending_duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 14; 
        
        $total_pages = isset($_POST['total_pages']) ? (int)$_POST['total_pages'] : 0;
        
        if ($quantity < 1) $quantity = 1;
        if ($lending_duration < 1) $lending_duration = 14;

        // Insert Multiple Copies
        $stmt = $pdo->prepare("INSERT INTO books (user_id, title, author, category, `condition`, type, price, status, total_pages, current_page, lending_duration) VALUES (?, ?, ?, ?, ?, ?, ?, 'Available', ?, 0, ?)");
        
        for ($i = 0; $i < $quantity; $i++) {
            $stmt->execute([$user_id, $title, $author, $category, $condition, $type, $price, $total_pages, $lending_duration]);
        }
        
        
        header("Location: reading.php?view=marketplace&filter=listings");
        exit;
    }

    // 4. Buy/Borrow Book
    if ($action === 'buy_book') {
        $book_id = $_POST['book_id'];
        
        $check = $pdo->prepare("SELECT * FROM books WHERE id = ? AND status = 'Available' AND user_id != ?");
        $check->execute([$book_id, $user_id]);
        $book = $check->fetch();

        if ($book) {
            $prev_owner = $book['user_id'];
            $is_borrow = ($book['type'] === 'Borrow');
            
            // Dynamic Due Date
            $days = $book['lending_duration'] ?? 14;
            $borrowed_from = $is_borrow ? $prev_owner : null;
            $due_date = $is_borrow ? date('Y-m-d', strtotime("+$days days")) : null;
            $new_status = $is_borrow ? 'reading' : 'wishlist'; 
            
            // Fix: If total_pages is 0, maybe try updates? For now just reset current_page.
            $stmt = $pdo->prepare("UPDATE books SET user_id = ?, status = ?, current_page = 0, borrowed_from = ?, due_date = ? WHERE id = ?");
            $stmt->execute([$user_id, $new_status, $borrowed_from, $due_date, $book_id]);

            // RECORD TRANSACTION
            $transType = $is_borrow ? 'borrow' : 'buy';
            $tStmt = $pdo->prepare("INSERT INTO book_transactions (book_id, seller_id, buyer_id, type, price) VALUES (?, ?, ?, ?, ?)");
            $tStmt->execute([$book_id, $prev_owner, $user_id, $transType, $book['price']]);
            
            // --- NOTIFICATION Logic ---
            $msgType = $is_borrow ? 'borrow' : 'sale';
            $msgContent = "";
            if ($is_borrow) {
                $msgContent = "🤝 $username has borrowed your book '{$book['title']}'. It is due on " . date('M d, Y', strtotime($due_date));
            } else {
                if ($book['price'] > 0) {
                    $msgContent = "💰 $username just bought your book '{$book['title']}' for ₹{$book['price']}.";
                } else {
                    $msgContent = "🎁 Gift! $username claimed your free book '{$book['title']}'.";
                }
            }
            
            // Notify the OWNER
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
            $notifStmt->execute([$prev_owner, $msgContent, $msgType]);
            
            $_SESSION['flash_msg'] = $is_borrow ? "Book borrowed successfully! 📚" : "Book purchased successfully! 🎉";
            if ($is_borrow) {
                header("Location: reading.php?view=marketplace&filter=borrowed");
            } else {
                header("Location: reading.php?view=tracker");
            }
            exit;
        } else {
            $_SESSION['flash_error'] = "Action failed: Book is unavailable or you are the owner.";
            header("Location: reading.php?view=marketplace");
            exit;
        }

        }
    
    // 5. Pre-book / Reserve
    if ($action === 'prebook') {
        $book_id = $_POST['book_id'];
        
        // Check for duplicates
        $check = $pdo->prepare("SELECT id FROM book_reservations WHERE book_id = ? AND user_id = ? AND status = 'pending'");
        $check->execute([$book_id, $user_id]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO book_reservations (book_id, user_id) VALUES (?, ?)");
            $stmt->execute([$book_id, $user_id]);
            
            // Get Due Date to inform user
            $bStmt = $pdo->prepare("SELECT due_date, title FROM books WHERE id = ?");
            $bStmt->execute([$book_id]);
            $bInfo = $bStmt->fetch();
            $dueStr = $bInfo['due_date'] ? date('M d', strtotime($bInfo['due_date'])) : 'soon';
            
            // Notify user
            $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'info')");
            $msg = "Waitlist Confirmed! You are queued for '{$bInfo['title']}'. Expected availability: $dueStr. We'll alert you.";
            $nStmt->execute([$user_id, $msg]);
        }
        
        header("Location: reading.php?view=marketplace&reserved=1");
        exit;
    }

    // 6. Return Book
    if ($action === 'return_book') {
        $book_id = $_POST['book_id'];
        
        // Ensure I currently have the book and it was borrowed
        $check = $pdo->prepare("SELECT * FROM books WHERE id = ? AND user_id = ? AND borrowed_from IS NOT NULL");
        $check->execute([$book_id, $user_id]);
        $book = $check->fetch();

        if ($book) {
            $original_owner = $book['borrowed_from'];
            
            // Return to original owner
            $stmt = $pdo->prepare("UPDATE books SET user_id = ?, status = 'Available', current_page = 0, borrowed_from = NULL, due_date = NULL WHERE id = ?");
            $stmt->execute([$original_owner, $book_id]);

            // RECORD RETURN TRANSACTION
            $tStmt = $pdo->prepare("INSERT INTO book_transactions (book_id, seller_id, buyer_id, type, price) VALUES (?, ?, ?, 'return', 0)");
            $tStmt->execute([$book_id, $user_id, $original_owner]); 
            
            // Notify Owner
            $msgContent = "📚 $username has returned your book '{$book['title']}'. It is now back in your library.";
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'return')");
            $notifStmt->execute([$original_owner, $msgContent]);
            
            // CHECK RESERVATIONS (Pre-bookings)
            $resCheck = $pdo->prepare("SELECT * FROM book_reservations WHERE book_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 1");
            $resCheck->execute([$book_id]);
            $reservation = $resCheck->fetch();
            
            if ($reservation) {
                // Determine who to notify (The person who reserved it)
                $waiter_id = $reservation['user_id'];
                
                // Notify the waiter
                $waiterMsg = "🎉 Good news! The book '{$book['title']}' is now available! Go to the library to grab it.";
                $notifStmt->execute([$waiter_id, $waiterMsg, 'alert']);
                
                // Update reservation
                $updRes = $pdo->prepare("UPDATE book_reservations SET status = 'notified' WHERE id = ?");
                $updRes->execute([$reservation['id']]);
            }
            
            header("Location: reading.php?view=tracker&returned=1");
            exit;
        }
    }
}

// Handle Delete (Personal)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM books WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: reading.php?view=tracker");
    exit;
}

// --- DATA FETCHING ---

// Fetch Personal Books (Tracker)
$filter_status = $_GET['status'] ?? 'reading';
$tracker_books = [];
$stats = [];

// Fetch Transactions (History)
$sold_history = [];
$lent_active = [];

// --- GLOBAL DATA (Required for counts in both views) ---
// Enhanced Stats
$statsStmt = $pdo->prepare("SELECT 
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'reading' THEN 1 END) as reading,
    COUNT(CASE WHEN status = 'wishlist' THEN 1 END) as wishlist,
    COUNT(CASE WHEN status = 'Available' THEN 1 END) as available,
    COUNT(CASE WHEN borrowed_from IS NOT NULL THEN 1 END) as borrowed,
    SUM(current_page) as total_pages_read
    FROM books WHERE user_id = ?");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch();

// --- FILTER PARAMETERS ---
$market_category = $_GET['category'] ?? 'All';
$market_search = $_GET['search'] ?? '';

// Helper for dynamic filtering
function buildFilterSql($baseSql, $cat, $search, &$params) {
    if ($cat !== 'All') {
        $baseSql .= " AND b.category = ?";
        $params[] = $cat;
    }
    if (!empty($search)) {
        $baseSql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    return $baseSql;
}

// Fetch Sold History
$soldParams = [$user_id];
$soldSql = "SELECT t.*, b.title, b.author, b.total_pages, b.current_page, u.username as buyer_name 
            FROM book_transactions t 
            JOIN books b ON t.book_id = b.id 
            JOIN users u ON t.buyer_id = u.id 
            WHERE t.seller_id = ? AND t.type = 'buy'";
// Apply filters specifically for 'listings' tab or if user is searching globally
if ($view === 'marketplace' && ($market_category !== 'All' || $market_search)) {
    // Note: Transaction table doesn't have category, joined 'books' does
    $soldSql = buildFilterSql($soldSql, $market_category, $market_search, $soldParams);
}
$soldSql .= " ORDER BY t.transaction_date DESC";
$soldStmt = $pdo->prepare($soldSql);
$soldStmt->execute($soldParams);
$sold_history = $soldStmt->fetchAll();

// Fetch Active Loans
$lentParams = [$user_id];
$lentSql = "SELECT b.*, u.username as borrower_name 
            FROM books b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.borrowed_from = ?";
if ($view === 'marketplace' && ($market_category !== 'All' || $market_search)) {
    $lentSql = buildFilterSql($lentSql, $market_category, $market_search, $lentParams);
}
$lentStmt = $pdo->prepare($lentSql);
$lentStmt->execute($lentParams);
$lent_active = $lentStmt->fetchAll();

// Fetch Active Borrows
$borrowedParams = [$user_id];
$borrowedSql = "SELECT b.*, u.username as lender_name 
                FROM books b 
                JOIN users u ON b.borrowed_from = u.id 
                WHERE b.user_id = ? AND b.borrowed_from IS NOT NULL";
if ($view === 'marketplace' && ($market_category !== 'All' || $market_search)) {
    $borrowedSql = buildFilterSql($borrowedSql, $market_category, $market_search, $borrowedParams);
}
$borrowedStmt = $pdo->prepare($borrowedSql);
$borrowedStmt->execute($borrowedParams);
$borrowed_active = $borrowedStmt->fetchAll();

// Fetch Returned History (Past Loans/Borrows)
$returnedStmt = $pdo->prepare("SELECT t.*, b.title, u_seller.username as owner_name, u_buyer.username as returner_name
                               FROM book_transactions t
                               JOIN books b ON t.book_id = b.id
                               LEFT JOIN users u_seller ON t.seller_id = u_seller.id
                               LEFT JOIN users u_buyer ON t.buyer_id = u_buyer.id
                               WHERE (t.seller_id = ? OR t.buyer_id = ?) AND t.type = 'return'
                               ORDER BY t.transaction_date DESC LIMIT 10");
$returnedStmt->execute([$user_id, $user_id]);
$returned_history = $returnedStmt->fetchAll();

$lent_count = count($lent_active);
$sold_count = count($sold_history);
$listings_count = $stats['available'] + $sold_count + $lent_count; 
$borrowed_count = count($borrowed_active); 

// Fetch Purchased Books (books bought by user from marketplace)
$purchasedParams = [$user_id];
$purchasedSql = "SELECT b.*, t.price as purchase_price, t.transaction_date as purchase_date, u.username as seller_name 
                FROM book_transactions t 
                JOIN books b ON t.book_id = b.id 
                JOIN users u ON t.seller_id = u.id 
                WHERE t.buyer_id = ? AND t.type = 'buy'
                ORDER BY t.transaction_date DESC";
$purchasedStmt = $pdo->prepare($purchasedSql);
$purchasedStmt->execute($purchasedParams);
$purchased_books = $purchasedStmt->fetchAll();
$purchased_count = count($purchased_books);

if ($view === 'tracker') {
    // Base SQL for simple fetching
    $base_sql = "SELECT b.*, u.username as lender_name FROM books b LEFT JOIN users u ON b.borrowed_from = u.id WHERE b.user_id = ?";
    
    if ($filter_status === 'borrowed') {
        // Fetch ONLY borrowed books (held by me, owned by others)
        $stmt = $pdo->prepare("SELECT b.*, u.username as lender_name FROM books b JOIN users u ON b.borrowed_from = u.id WHERE b.user_id = ? AND b.borrowed_from IS NOT NULL ORDER BY b.due_date ASC");
        $stmt->execute([$user_id]);
        $tracker_books = $stmt->fetchAll();
    }
    elseif ($filter_status === 'purchased') {
        // Use the purchased_books already fetched above
        $tracker_books = $purchased_books;
    }
    elseif ($filter_status === 'all') {
        $stmt = $pdo->prepare($base_sql . " AND b.status IN ('reading', 'completed', 'wishlist') ORDER BY FIELD(b.status, 'reading', 'wishlist', 'completed'), b.created_at DESC");
        $stmt->execute([$user_id]);
        $tracker_books = $stmt->fetchAll();
    } else {
        // Specific Status (reading, completed, wishlist, Available)
        $stmt = $pdo->prepare($base_sql . " AND b.status = ? ORDER BY b.created_at DESC");
        $stmt->execute([$user_id, $filter_status]);
        $tracker_books = $stmt->fetchAll();
    } 

    // --- MERGE LOGIC FOR LISTINGS (TRACKER VIEW) ---
    if ($filter_status === 'Available') {
        foreach ($sold_history as $sale) {
            $sale['status'] = 'Sold';
            $sale['_is_sold'] = true;
            $sale['owner_name'] = 'You (Sold)';
            $sale['type'] = 'Sell'; 
            $tracker_books[] = $sale;
        }
        foreach ($lent_active as $loan) {
            $loan['_is_lent'] = true;
            $tracker_books[] = $loan;
        }
    }
    
    if ($filter_status === 'lent') {
        foreach ($lent_active as $loan) {
            $loan['_is_lent'] = true;
            $loan['status'] = 'Available'; 
            $tracker_books[] = $loan;
        }
    }
}

// --- MARKETPLACE LOGIC ---
$marketplace_books = [];
$market_category = $_GET['category'] ?? 'All';
$market_search = $_GET['search'] ?? '';

// Fetch dynamic categories
$cats_stmt = $pdo->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != ''");
$db_cats = $cats_stmt->fetchAll(PDO::FETCH_COLUMN);

$default_cats = [
    'Textbook', 'Fiction', 'Non-Fiction', 'Self-Help', 
    'Adventure', 'Mystery', 'Romance', 'Sci-Fi', 'Fantasy',
    'Horror', 'Thriller', 'Biography', 'History', 'Classics',
    'Comic', 'Art', 'Business', 'Technology', 'Science', 'Philosophy',
    'Psychology', 'Travel', 'Health', 'Cooking', 'Poetry', 'Religion'
];
$major_cats = ['Textbook', 'Fiction', 'Non-Fiction', 'Self-Help']; 
$all_categories = array_unique(array_merge($default_cats, $db_cats));
sort($all_categories);
array_unshift($all_categories, 'All');

if ($view === 'marketplace') {
    $sql = "SELECT b.*, 
            COALESCE(u_owner.username, u_curr.username) as owner_name, 
            u_owner.username as real_owner
            FROM books b 
            LEFT JOIN users u_curr ON b.user_id = u_curr.id 
            LEFT JOIN users u_owner ON b.borrowed_from = u_owner.id
            WHERE ((b.status = 'Available') 
               OR (b.borrowed_from IS NOT NULL)) 
            ";
    
    $params = [];

    if ($market_category !== 'All') {
        $sql .= " AND b.category = ?";
        $params[] = $market_category;
    }
    if (!empty($market_search)) {
        $sql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
        $params[] = "%$market_search%";
        $params[] = "%$market_search%";
    }
    $sql .= " ORDER BY b.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_books = $stmt->fetchAll();

    // Fetch my reservations
    $myResStmt = $pdo->prepare("SELECT book_id FROM book_reservations WHERE user_id = ? AND status = 'pending'");
    $myResStmt->execute([$user_id]);
    $my_reservations = $myResStmt->fetchAll(PDO::FETCH_COLUMN);

    // Grouping Logic
    $grouped_books = [];
    foreach ($raw_books as $b) {
        // Robust normalization: trim, lowercase, then hash
        $t_clean = trim(strtolower($b['title']));
        $a_clean = trim(strtolower($b['author']));
        $key = md5($t_clean . '|' . $a_clean);
        
        if (!isset($grouped_books[$key])) {
            $grouped_books[$key] = [
                'meta' => $b, 
                'count' => 0,          
                'available_count' => 0,
                'min_price' => $b['price'],
                'max_price' => $b['price'],
                'copies' => []
            ];
        }
        
        $group = &$grouped_books[$key];
        
        $is_avail = ($b['status'] === 'Available');
        
        $is_mine = ($b['user_id'] == $user_id && $is_avail) || ($b['borrowed_from'] == $user_id);
        if ($is_mine) {
            $b['owner_name'] = 'You';
        }

        $b['is_available'] = $is_avail;
        $b['is_mine'] = $is_mine;
        $b['is_reserved_by_me'] = in_array($b['id'], $my_reservations);
        if (!$is_avail && !$is_mine) {
            $b['owner_name'] = $b['real_owner'] ?? 'Unknown';
        }

        // Prevent duplicate IDs in the same group (sanity check)
        $exists_in_group = false;
        foreach($group['copies'] as $existing_copy) {
            if ($existing_copy['id'] == $b['id']) {
                $exists_in_group = true;
                break;
            }
        }

        if (!$exists_in_group) {
            $group['copies'][] = $b;
            $group['count']++;
            if ($is_avail) $group['available_count']++;
            
            if ($b['price'] < $group['min_price']) $group['min_price'] = $b['price'];
            if ($b['price'] > $group['max_price']) $group['max_price'] = $b['price'];
        }
    }
    
    $marketplace_books = array_values($grouped_books);
}

// Helper for CSS cover color
function getBookColor($title) {
    // Determine hue based on title - pastel
    $hash = md5($title);
    $hue = hexdec(substr($hash, 0, 2)) % 360;
    return "hsl($hue, 70%, 85%)";
}
function getBookDarkColor($title) {
    // Darker version for text
    $hash = md5($title);
    $hue = hexdec(substr($hash, 0, 2)) % 360;
    return "hsl($hue, 70%, 30%)";
}

?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Hub - Fiora</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Shared & Tracker Styles */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { padding: 20px; text-align: center; border: 1px solid var(--glass-border); }
        
        .main-tabs { display: flex; gap: 20px; margin-bottom: 30px; border-bottom: 1px solid var(--glass-border); padding-bottom: 15px; }
        .main-tab { 
            font-size: 1.2rem; font-weight: 700; color: #888; text-decoration: none; padding: 5px 10px; transition: all 0.2s; 
            display: flex; align-items: center; gap: 8px;
        }
        .main-tab.active { color: var(--primary); border-bottom: 3px solid var(--primary); }
        .main-tab:hover { color: var(--primary); }

        /* Tracker Specific */
        .sub-tabs { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        .sub-tab { padding: 8px 20px; border-radius: 12px; background: rgba(255,255,255,0.4); border: 1px solid var(--glass-border); text-decoration: none; color: #444; font-weight: 600; transition: all 0.2s; }
        .sub-tab:hover { background: rgba(255,255,255,0.8); }
        .sub-tab.active { background: var(--primary); color: white; border-color: var(--primary); }

        .reading-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .book-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 20px; padding: 25px; transition: transform 0.3s; position: relative; }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .progress-bar { height: 10px; background: rgba(0,0,0,0.05); border-radius: 10px; margin: 15px 0; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--primary); transition: width 0.5s ease; }

        /* Marketplace Specific */
        .market-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 25px; }
        .market-card { background: rgba(255,255,255,0.6); border: 1px solid var(--glass-border); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: all 0.2s; }
        .market-card:hover { transform: translateY(-5px); background: rgba(255,255,255,0.9); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        
        /* CSS Generated Book Cover */
        .css-cover { 
            height: 180px; 
            display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;
            padding: 20px;
            position: relative;
        }
        .css-cover::before { 
            content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 5px; background: rgba(0,0,0,0.1); 
        }
        .cover-title { font-weight: 800; font-family: 'JetBrains Mono'; line-height: 1.2; font-size: 1.1rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .cover-author { font-size: 0.8rem; margin-top: 5px; opacity: 0.8; font-weight: 600;}

        .market-info { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .tag-pill { padding: 4px 12px; border-radius: 20px; background: rgba(255,255,255,0.5); border: 1px solid var(--glass-border); font-size: 0.85rem; color: #555; text-decoration: none; transition: all 0.2s; white-space: nowrap; }
        .tag-pill:hover { background: white; border-color: var(--primary); color: var(--primary); }

        /* Modals */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 450px; max-width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        
        .return-badge { position: absolute; top: 15px; left: 15px; background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: bold; z-index: 5; }
        
        /* Custom Select */
        .custom-select-wrapper { position: relative; }
        .custom-select-trigger { cursor: pointer; position: relative; background: rgba(255, 255, 255, 0.8); border: 1px solid rgba(0,0,0,0.05); padding: 14px; border-radius: 12px; color: #555; }
        .custom-select-trigger::after { content: '▼'; position: absolute; right: 15px; font-size: 0.8rem; color: #999; }
        .custom-options { display: none; position: absolute; top: 105%; left: 0; right: 0; background: white; border: 1px solid #eee; border-radius: 12px; max-height: 250px; overflow-y: auto; z-index: 2000; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .custom-option { padding: 10px 15px; cursor: pointer; transition: all 0.2s; border-bottom: 1px solid #f9f9f9; }
        .custom-option:hover { background: #f0f9ff; color: var(--primary); padding-left: 20px; }
        .history-list { list-style: none; padding: 0; max-height: 300px; overflow-y: auto; }
        .history-item { display: flex; justify-content: space-between; padding: 15px; border-bottom: 1px solid #eee; align-items: center; }
        .history-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1>Book Hub 📖</h1>
                    <p style="color: #666;">Track your journey and discover new books.</p>
                </div>
                <?php if ($view === 'tracker'): ?>
                    <button class="btn btn-primary" onclick="openModal('addPersonalModal')">+ Add Book</button>
                    <!-- Small History Button -->
                    <button class="btn" onclick="openModal('loansModal')" style="margin-left: 10px; background: var(--glass-bg); border: 1px solid var(--glass-border); color: #1976d2;">🤝 Active Loans</button>
                    <button class="btn" onclick="openModal('historyModal')" style="margin-left: 5px; background: var(--glass-bg); border: 1px solid var(--glass-border); color: #555;">📜 History</button>
                <?php else: ?>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-secondary" onclick="openModal('lendBookModal')" style="background: white; color: var(--primary); border: 1px solid var(--primary);">🤝 Lend a Book</button>
                        <button class="btn btn-primary" onclick="openModal('sellBookModal')">💰 Sell a Book</button>
                    </div>
                <?php endif; ?>
            </header>

            <!-- Top Navigation -->
            <div class="main-tabs">
                <a href="?view=tracker" class="main-tab <?php echo $view === 'tracker' ? 'active' : ''; ?>">
                    📊 My Tracker
                </a>
                <a href="?view=marketplace" class="main-tab <?php echo $view === 'marketplace' ? 'active' : ''; ?>">
                    🏛️ Book Hub
                </a>
            </div>

            <!-- OVERDUE ALERT -->
            <?php if (!empty($overdue_books)): ?>
                <div style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 1.5rem;">⚠️</span>
                    <div>
                        <strong>Action Required: Overdue Books!</strong><br>
                        Please return: <?php echo implode(", ", array_column($overdue_books, 'title')); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- VIEW: TRACKER -->
            <?php if ($view === 'tracker'): ?>
                <div class="stats-grid">
                    <div class="glass-card stat-card">
                        <div style="font-size: 0.8rem; font-weight: 800; color: #555;">BOOKS COMPLETED</div>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--success);"><?php echo $stats['completed'] ?? 0; ?></div>
                    </div>
                    <div class="glass-card stat-card">
                        <div style="font-size: 0.8rem; font-weight: 800; color: #555;">CURRENTLY READING</div>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--primary);"><?php echo $stats['reading'] ?? 0; ?></div>
                    </div>
                    <div class="glass-card stat-card">
                        <div style="font-size: 0.8rem; font-weight: 800; color: #555;">PAGES READ</div>
                        <div style="font-size: 2rem; font-weight: 800; font-family: 'JetBrains Mono';"><?php echo $stats['total_pages_read'] ?? 0; ?></div>
                    </div>
                </div>

                <div class="sub-tabs">
                    <a href="?view=tracker&status=reading" class="sub-tab <?php echo $filter_status == 'reading' ? 'active' : ''; ?>">
                        Reading <span style="opacity:0.7; font-size:0.8em; margin-left:4px;"><?php echo $stats['reading']; ?></span>
                    </a>
                    <a href="?view=tracker&status=completed" class="sub-tab <?php echo $filter_status == 'completed' ? 'active' : ''; ?>">
                        Finished <span style="opacity:0.7; font-size:0.8em; margin-left:4px;"><?php echo $stats['completed']; ?></span>
                    </a>
                    <a href="?view=tracker&status=wishlist" class="sub-tab <?php echo $filter_status == 'wishlist' ? 'active' : ''; ?>">
                        Wishlist <span style="opacity:0.7; font-size:0.8em; margin-left:4px;"><?php echo $stats['wishlist']; ?></span>
                    </a>
                    <a href="?view=tracker&status=borrowed" class="sub-tab <?php echo $filter_status == 'borrowed' ? 'active' : ''; ?>">
                        📚 Borrowed <span style="opacity:0.7; font-size:0.8em; margin-left:4px;"><?php echo $borrowed_count; ?></span>
                    </a>
                    <a href="?view=tracker&status=purchased" class="sub-tab <?php echo $filter_status == 'purchased' ? 'active' : ''; ?>">
                        🛒 Purchased <span style="opacity:0.7; font-size:0.8em; margin-left:4px;"><?php echo $purchased_count; ?></span>
                    </a>
                    <a href="?view=tracker&status=all" class="sub-tab <?php echo $filter_status == 'all' ? 'active' : ''; ?>">All Books</a>
                </div>

                <div class="reading-grid">
                    <?php if (empty($tracker_books)): ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">
                            <h3>Your shelf is empty.</h3>
                            <?php if($filter_status == 'Available'): ?>
                                <p style="max-width: 500px; margin: 0 auto 15px auto; line-height: 1.6; color: #666;">
                                    Share your library with the community! <br>
                                    Create a <strong>Listing</strong> to <strong style="color: var(--success);">Sell</strong> books for extra cash or <strong style="color: var(--primary);">Lend</strong> them to help others read.
                                </p>
                                <button onclick="openModal('sellBookModal')" class="btn btn-primary">List a Book Now</button>
                            <?php else: ?>
                                <button onclick="openModal('addPersonalModal')" style="background: none; border: none; color: var(--primary); cursor: pointer; text-decoration: underline;">Add your first book</button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach($tracker_books as $book): 
                            $total = $book['total_pages'] ?? 0;
                            $current = $book['current_page'] ?? 0;
                            $pct = ($total > 0) ? round(($current / $total) * 100) : 0;
                            $author = $book['author'] ?? 'Unknown Author';
                            $is_borrowed = !empty($book['borrowed_from']);
                            $is_selling = ($book['status'] === 'Available');
                            
                            $due_msg = '';
                            $due_color = '#1976d2';
                            if ($is_borrowed && !empty($book['due_date'])) {
                                $due_time = strtotime($book['due_date']);
                                $diff_seconds = $due_time - time();
                                $days_left = ceil($diff_seconds / (60 * 60 * 24));
                                
                                if ($days_left < 0) {
                                    $d = abs($days_left);
                                    $s = $d == 1 ? '' : 's';
                                    $due_msg = "Overdue ($d day$s)";
                                    $due_color = '#d32f2f';
                                } elseif ($days_left == 0) {
                                    $due_msg = "Due Today";
                                    $due_color = '#f57c00';
                                } else {
                                    $s = $days_left == 1 ? '' : 's';
                                    $due_msg = date('M j', $due_time) . " ($days_left day$s left)";
                                    if ($days_left <= 3) $due_color = '#f57c00';
                                }
                            }
                        ?>
                            <div class="book-card">
                                <?php if($is_borrowed): ?>
                                    <div class="return-badge" style="color: <?php echo $due_color; ?>; background: color-mix(in srgb, <?php echo $due_color; ?> 10%, white); display: flex; flex-direction: column; gap: 2px; align-items: flex-start; padding: 8px 12px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                                        <div style="line-height: 1.2;">📖 Borrowed from <strong><?php echo htmlspecialchars($book['lender_name'] ?? 'Library'); ?></strong></div>
                                        <div style="font-size: 0.8em; opacity: 0.9; margin-top: 2px;">📅 <?php echo $due_msg; ?></div>
                                    </div>
                                <?php endif; ?>

                                <h3 style="margin-bottom: 5px; <?php echo $is_borrowed ? 'margin-top: 60px;' : ''; ?>"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <div style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">by <?php echo htmlspecialchars($author); ?></div>
                                
                                    <?php if(isset($book['_is_sold']) && $book['_is_sold']): ?>
                                        <div style="background: #eee; padding: 10px; border-radius: 10px; color: #555; text-align: center; margin-bottom: 15px;">
                                            <div style="font-weight: 800; color: #444;">SOLD - ₹<?php echo number_format($book['price'], 2); ?></div>
                                            <div style="font-size: 0.8rem; margin-top: 5px;">To: <?php echo htmlspecialchars($book['buyer_name']); ?></div>
                                            <div style="font-size: 0.7rem; color: #888;"><?php echo date('M d, Y', strtotime($book['transaction_date'])); ?></div>
                                        </div>
                                    <?php elseif($is_selling): ?>
                                        <div style="background: linear-gradient(135deg, #fce38a 0%, #f38181 100%); padding: 10px; border-radius: 10px; color: white; font-weight: bold; text-align: center; margin-bottom: 15px;">
                                    <?php if (isset($book['_is_lent'])): ?>
                                        <div style="font-size: 0.9rem; margin-bottom: 4px; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                            🤝 Lent to: <strong style="text-decoration: underline;"><?php echo htmlspecialchars($book['borrower_name']); ?></strong>
                                            <?php if(!empty($book['due_date'])): 
                                                $due = strtotime($book['due_date']);
                                                $days_left = ceil(($due - time()) / (60 * 60 * 24));
                                                $is_overdue = $days_left < 0;
                                                
                                                if ($is_overdue) {
                                                    $due_txt = "⚠️ Overdue by " . abs($days_left) . " days";
                                                    $due_style = "color: #b91c1c; background: rgba(255,255,255,0.8); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px;";
                                                } else {
                                                    $s = $days_left == 1 ? '' : 's';
                                                    $due_txt = "Returns: " . date('M j', $due) . " ($days_left day$s)";
                                                    $due_style = "opacity: 0.95; margin-top: 4px; font-weight: 600;";
                                                }
                                            ?>
                                                <div style="font-size: 0.8rem; <?php echo $due_style; ?>">
                                                    <?php echo $due_txt; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                            <?php else: ?>
                                                <?php echo $book['type'] == 'Sell' ? 'For Sale: ₹'.$book['price'] : 'Lending: Free'; ?>
                                                <div style="font-size: 0.75rem; opacity: 0.9; margin-top: 2px;">Listed in Marketplace</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $pct; ?>%;"></div>
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div style="font-family: 'JetBrains Mono'; font-size: 0.9rem;">
                                                <?php if ($book['status'] !== 'wishlist'): ?>
                                                    <input type="number" 
                                                           value="<?php echo $book['current_page']; ?>" 
                                                           onchange="updateProgress(<?php echo $book['id']; ?>, this.value)"
                                                           style="width: 60px; padding: 5px; border-radius: 5px; border: 1px solid #ddd; text-align: center;"
                                                    > / <input type="number" 
                                                           value="<?php echo $book['total_pages']; ?>" 
                                                           onchange="updateTotalPages(<?php echo $book['id']; ?>, this.value)"
                                                           style="width: 70px; padding: 5px; border-radius: 5px; border: 1px solid var(--primary); background: #f0f8ff; text-align: center; margin-left: 5px; font-weight: bold; color: var(--primary); cursor: pointer;"
                                                           title="Click to set total pages" placeholder="Total">
                                                <?php else: ?>
                                                    Expected: <?php echo $book['total_pages']; ?> p
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-weight: bold;"><?php echo $pct; ?>%</div>
                                        </div>

                                        <!-- Target Date & Daily Goal -->
                                        <?php if ($book['status'] === 'reading'): ?>
                                            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #eee;">
                                                <form method="POST" action="reading.php" style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="hidden" name="action" value="set_target">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                    <div style="flex: 1;">
                                                        <label style="font-size: 0.7rem; color: #888; display: block; margin-bottom: 2px;">Target Date 🎯</label>
                                                        <input type="date" name="target_date" 
                                                               value="<?php echo $book['target_date'] ?? ''; ?>" 
                                                               onchange="this.form.submit()"
                                                               style="width: 100%; border: 1px solid #eee; border-radius: 4px; padding: 4px; font-size: 0.8rem; color: #555;">
                                                    </div>
                                                </form>

                                                <?php if (!empty($book['target_date'])): 
                                                    $target_ts = strtotime($book['target_date']);
                                                    $today_ts = time();
                                                    $days_diff = ceil(($target_ts - $today_ts) / (60 * 60 * 24));
                                                    
                                                    if ($book['total_pages'] > 0) {
                                                        $pages_left = $book['total_pages'] - $book['current_page'];
                                                        
                                                        if ($pages_left > 0) {
                                                            if ($days_diff > 0) {
                                                                $daily_goal = ceil($pages_left / $days_diff);
                                                                $day_label = $days_diff == 1 ? 'day' : 'days';
                                                                echo "<div style='margin-top: 8px; font-size: 0.8rem; color: var(--primary); background: rgba(25, 118, 210, 0.05); padding: 8px; border-radius: 6px;'>
                                                                        <strong>Daily Goal:</strong> Read <strong style='font-size:1.1em;'>$daily_goal</strong> pages/day
                                                                        <div style='font-size: 0.75rem; opacity: 0.8; margin-top: 3px;'>($days_diff $day_label left)</div>
                                                                      </div>";
                                                            } elseif ($days_diff == 0) {
                                                                  echo "<div style='margin-top: 8px; font-size: 0.8rem; color: #d32f2f; background: #ffebee; padding: 8px; border-radius: 6px;'>
                                                                        <strong>Due Today!</strong> Read $pages_left pages.
                                                                      </div>";
                                                            } else {
                                                                echo "<div style='margin-top: 8px; font-size: 0.8rem; color: #c62828; background: #ffebee; padding: 5px; border-radius: 4px; font-weight:600;'>❌ Not Completed</div>";
                                                            }
                                                        } else {
                                                            echo "<div style='margin-top: 8px; font-size: 0.8rem; color: var(--success);'>Goal Met! 🎉</div>";
                                                        }
                                                    } else {
                                                        echo "<div style='margin-top: 8px; font-size: 0.8rem; color: #ff9800;'>⚠ Set total pages to track goal</div>";
                                                    }
                                                endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($book['status'] === 'wishlist'): ?>
                                            <div style="margin-top: 15px; border-top: 1px dashed #eee; padding-top: 10px;">
                                                <form method="POST" action="reading.php">
                                                    <input type="hidden" name="action" value="start_reading">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 8px; font-size: 0.9rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">🚀 Start Reading</button>
                                                </form>
                                                
                                                <button type="button" data-book-full="<?php echo htmlspecialchars(json_encode($book), ENT_QUOTES, 'UTF-8'); ?>" onclick="openListModal(JSON.parse(this.dataset.bookFull))" class="btn btn-secondary" style="width: 100%; padding: 8px; font-size: 0.9rem; border: 1px dashed #ccc; color: #666; margin-top: 8px;">📢 List to Market</button>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div style="margin-top: 15px;">
                                        <?php if ($is_borrowed): 
                                            // Borrowed Card Actions
                                            $due = $book['due_date'] ? strtotime($book['due_date']) : null;
                                            $is_overdue = $due && time() > $due;
                                        ?>

                                            <form method="POST" onsubmit="return confirm('Return this book?');">
                                                <input type="hidden" name="action" value="return_book">
                                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                <button type="submit" class="btn btn-secondary" style="width: 100%; border: 1px solid var(--primary); color: var(--primary);">Return Book</button>
                                            </form>
                                        <?php endif; ?>

                                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                                            <?php if(!isset($book['_is_sold']) && !$is_borrowed && !$is_selling): ?>
                                                <a href="?delete=<?php echo $book['id']; ?>" onclick="return confirm('Remove?')" style="text-decoration: none; color: #ddd; font-size: 1.2rem; align-self: center;">&times;</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>



            <!-- VIEW: MARKETPLACE -->
            <?php if ($view === 'marketplace'): ?>
                <?php 
                // Determine if we are showing Main Marketplace or a Sub-Filter
                $market_filter = $_GET['filter'] ?? 'all';
                ?>

                <!-- GLOBAL FILTER BAR -->
                <div class="glass-card" style="margin-bottom: 25px; padding: 15px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <div style="font-weight: 700; color: #444;">Quick Filter:</div>


                    
                    <form style="display: flex; gap: 10px; flex-wrap: wrap;" method="GET" action="reading.php">
                        <input type="hidden" name="view" value="marketplace">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($market_filter); ?>">
                        
                        <!-- Category Dropdown -->
                        <div style="position: relative;">
                            <select name="category" class="form-input" style="padding: 8px 15px; cursor: pointer; min-width: 150px;" onchange="this.form.submit()">
                                <?php 
                                foreach($all_categories as $ac) {
                                    $ac = trim($ac); // Clean value
                                    // Robust check for selected state
                                    $selected = (strcasecmp($market_category, $ac) == 0) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($ac) . "' $selected>$ac</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div style="position: relative;">
                            <input type="text" id="searchInput" name="search" placeholder="Search Title/Author..." class="form-input" 
                                   value="<?php echo htmlspecialchars($market_search); ?>" style="padding: 8px 15px; width: 180px;" autocomplete="off">
                            <div id="searchSuggestions" style="display:none; position:absolute; top:100%; left:0; width:100%; background:white; border:1px solid #ccc; border-radius:8px; z-index:1000; box-shadow:0 4px 6px rgba(0,0,0,0.1); max-height:200px; overflow-y:auto;"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 8px 15px;">🔍</button>
                    </form>
                </div>

                <div class="sub-tabs" style="margin-bottom: 20px;">
                    <a href="?view=marketplace" class="sub-tab <?php echo (!isset($_GET['filter']) || $_GET['filter'] == 'all') ? 'active' : ''; ?>">All Books</a>
                    <a href="?view=marketplace&filter=lent" class="sub-tab <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'lent') ? 'active' : ''; ?>">
                         Lent Out 🤝 <span style="opacity:0.7; font-size:0.8em; margin-left:4px;"><?php echo $lent_count; ?></span>
                    </a>
                    <a href="?view=marketplace&filter=borrowed" class="sub-tab <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'borrowed') ? 'active' : ''; ?>">
                         Borrowed 📚 <span style="opacity:0.7; font-size:0.8em; margin-left:4px;"><?php echo $borrowed_count; ?></span>
                    </a>
                    <a href="?view=marketplace&filter=listings" class="sub-tab <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'listings') ? 'active' : ''; ?>">
                         My Listings 💰 <span style="opacity:0.7; font-size:0.8em; margin-left:4px;"><?php echo $listings_count; ?></span>
                    </a>
                </div>

                <?php 
                // Determine if we are showing Main Marketplace or a Sub-Filter
                $market_filter = $_GET['filter'] ?? 'all';
                ?>

                <?php if($market_filter === 'all'): ?>
                    <div class="market-grid">
                    <?php if (empty($marketplace_books)): ?>
                         <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #888;">No books available in the library right now. <br>Be the first to list one!</div>
                    <?php else: ?>
                        <?php 
                        $rendered_hashes = [];
                        foreach ($marketplace_books as $group): 
                            $count = $group['count'];
                            $book = ($count == 1) ? $group['copies'][0] : $group['meta'];
                            
                            // FINAL VISUAL DEDUPLICATION
                            // Ensure we don't render the same book title/author twice even if grouping keys differed slightly
                            $v_key = md5(trim(strtolower($book['title'])) . '|' . trim(strtolower($book['author'])));
                            if (in_array($v_key, $rendered_hashes)) continue;
                            $rendered_hashes[] = $v_key;

                            $bg = getBookColor($book['title']);
                            $fg = getBookDarkColor($book['title']);
                        ?>
                            <?php 
                            $is_my_listing = false;
                            $has_borrow = false;
                            $sell_prices = [];

                            foreach ($group['copies'] as $copy) {
                                if (isset($copy['user_id']) && $copy['user_id'] == $user_id) {
                                    $is_my_listing = true;
                                }
                                $t = strtolower($copy['type'] ?? '');
                                if ($t === 'borrow') $has_borrow = true;
                                if ($t === 'sell') $sell_prices[] = $copy['price'];
                            }

                            // Determine Label
                            if ($has_borrow && !empty($sell_prices)) {
                                $min_p = min($sell_prices);
                                $type_badge = "Borrow • Buy ₹" . number_format($min_p, 0);
                                $badge_color = "#666"; 
                            } elseif ($has_borrow) {
                                $type_badge = "Lending: Free";
                                $badge_color = "#f59e0b"; // Warning Orange
                            } elseif (!empty($sell_prices)) {
                                $min_p = min($sell_prices);
                                $type_badge = "For Sale: ₹" . number_format($min_p, 0);
                                $badge_color = "#10b981"; // Success Green
                            } else {
                                // Default fallback if type is missing but book is displayed
                                $type_badge = "Available"; // Fallback
                                $badge_color = "#999";
                            }
                            ?>
                            <div class="market-card">
                                <div class="css-cover" style="background: <?php echo $bg; ?>; color: <?php echo $fg; ?>;">
                                    <?php if ($is_my_listing): ?>
                                        <div style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); color: var(--primary); padding: 3px 8px; border-radius: 12px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">You Own This</div>
                                    <?php endif; ?>
                                    
                                    <div style="position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); background: rgba(255,255,255,0.95); color: #333; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.15); white-space: nowrap;">
                                        <?php echo $type_badge; ?>
                                    </div>

                                    <div class="cover-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                    <div class="cover-author"><?php echo htmlspecialchars($book['author']); ?></div>
                                </div>
                                <div class="market-info" style="display: flex; flex-direction: column; justify-content: flex-end; padding-top: 10px;">
                                    <button 
                                        data-copies="<?php echo htmlspecialchars(json_encode($count == 1 ? [$book] : $group["copies"]), ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="openSellerModal(JSON.parse(this.dataset.copies))"
                                        class="btn btn-secondary" style="width: 100%; border: 1px solid var(--primary); color: var(--primary);">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($market_filter === 'lent'): ?>
                <div class="reading-grid">
                    <?php if (empty($lent_active)): ?>
                         <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">You haven't lent any books.</div>
                    <?php else: ?>
                        <?php foreach($lent_active as $loan): ?>
                            <!-- Reuse Card Design -->
                            <div class="book-card">
                                <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($loan['title']); ?></h3>
                                <div style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">by <?php echo htmlspecialchars($loan['author']); ?></div>
                                <div style="background: linear-gradient(135deg, #fce38a 0%, #f38181 100%); padding: 10px; border-radius: 10px; color: white; font-weight: bold; text-align: center; margin-bottom: 15px;">
                                    <div style="font-size: 0.9rem; margin-bottom: 4px; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                        🤝 Lent to: <strong style="text-decoration: underline;"><?php echo htmlspecialchars($loan['borrower_name']); ?></strong>
                                    </div>
                                    <?php if(!empty($loan['due_date'])): 
                                        $due = strtotime($loan['due_date']);
                                        $days_left = ceil(($due - time()) / (60 * 60 * 24));
                                        $is_overdue = $days_left < 0;
                                        if ($is_overdue) {
                                            $due_txt = "⚠️ Overdue by " . abs($days_left) . " days";
                                            $due_style = "color: #b91c1c; background: rgba(255,255,255,0.8); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px;";
                                        } else {
                                            $s = $days_left == 1 ? '' : 's';
                                            $due_txt = "Returns: " . date('M j', $due) . " ($days_left day$s)";
                                            $due_style = "opacity: 0.95; margin-top: 4px; font-weight: 600;";
                                        }
                                    ?>
                                        <div style="font-size: 0.8rem; <?php echo $due_style; ?>">
                                            <?php echo $due_txt; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($market_filter === 'borrowed'): ?>
                 <div class="reading-grid">
                    <?php 
                    // RE-FETCH to ensure fresh data if a borrow action just happened
                    $borrowedParams = [$user_id];
                    $borrowedSql = "SELECT b.*, u.username as lender_name 
                                    FROM books b 
                                    JOIN users u ON b.borrowed_from = u.id 
                                    WHERE b.user_id = ? AND b.borrowed_from IS NOT NULL";
                    $borrowedStmt = $pdo->prepare($borrowedSql);
                    $borrowedStmt->execute($borrowedParams);
                    $borrowed_active = $borrowedStmt->fetchAll();
                    ?>
                    <?php if (empty($borrowed_active)): ?>
                         <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">You aren't borrowing any books.</div>
                    <?php else: ?>
                        <?php foreach($borrowed_active as $book): 
                            $is_overdue = false;
                             if (!empty($book['due_date'])) {
                                $due_time = strtotime($book['due_date']);
                                $diff_seconds = $due_time - time();
                                $days_left = ceil($diff_seconds / (60 * 60 * 24));
                                if ($days_left < 0) $is_overdue = true;
                                $due_display = date('M j', $due_time);
                             }
                        ?>
                            <div class="book-card">
                                <div class="return-badge" style="color: <?php echo $is_overdue ? '#d32f2f' : '#1976d2'; ?>; background: white; border: 1px solid #eee; display: flex; flex-direction: column; gap: 2px; align-items: flex-start; padding: 8px 12px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 10px;">
                                    <div style="line-height: 1.2;">📖 Borrowed from <strong><?php echo htmlspecialchars($book['lender_name'] ?? 'Library'); ?></strong></div>
                                    <?php if(!empty($book['due_date'])): ?>
                                        <div style="font-size: 0.8em; opacity: 0.9; margin-top: 2px;">📅 Due: <?php echo $due_display; ?></div>
                                    <?php endif; ?>
                                </div>

                                <h3 style="margin-bottom: 5px; margin-top: 60px;"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <div style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">by <?php echo htmlspecialchars($book['author']); ?></div>
                                
                                <button 
                                    data-book="<?php echo htmlspecialchars(json_encode($book), ENT_QUOTES, 'UTF-8'); ?>"
                                    onclick="openBorrowedDetails(JSON.parse(this.dataset.book))"
                                    class="btn btn-secondary" style="width: 100%; border: 1px solid var(--primary); color: var(--primary);">
                                    View Details
                                </button>
                                

                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($market_filter === 'listings'): ?>
                <div class="reading-grid">
                    <?php 
                        // Fetch My Listings specifically
                        $myListParams = [$user_id];
                        $myListSql = "SELECT * FROM books WHERE user_id = ? AND status = 'Available'";
                        
                        if ($view === 'marketplace' && ($market_category !== 'All' || $market_search)) {
                             // IMPORTANT: Alias 'b' is expected by buildFilterSql but here query is direct from books.
                             // We should alias table to 'b' for consistency with helper function
                             $myListSql = "SELECT b.* FROM books b WHERE b.user_id = ? AND b.status = 'Available'";
                             $myListSql = buildFilterSql($myListSql, $market_category, $market_search, $myListParams);
                        }
                        
                        $myListsStmt = $pdo->prepare($myListSql);
                        $myListsStmt->execute($myListParams);
                        $my_active_listings = $myListsStmt->fetchAll();
                        
                        // Merge with Sold History for display
                        $all_listings = $my_active_listings;
                        // Add flag to sold items
                        foreach($sold_history as $s) { $s['_is_sold'] = true; $all_listings[] = $s; }
                    ?>
                    
                    <?php if (empty($all_listings)): ?>
                         <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">
                            You have no active listings or sales history.
                            <br><br>
                            <button onclick="openModal('sellBookModal')" class="btn btn-primary">List a Book</button>
                         </div>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; margin-bottom: 10px; display: flex; justify-content: flex-end;">
                             <button onclick="openModal('sellBookModal')" class="btn btn-primary" style="padding: 5px 15px; font-size: 0.9rem;">+ New Listing</button>
                        </div>
                        <?php foreach($all_listings as $book): ?>
                             <div class="book-card">
                                <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <div style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">by <?php echo htmlspecialchars($book['author']); ?></div>
                                
                                <?php if(isset($book['_is_sold'])): ?>
                                    <div style="background: #eee; padding: 10px; border-radius: 10px; color: #555; text-align: center; margin-bottom: 15px;">
                                        <div style="font-weight: 800; color: #444;">SOLD - ₹<?php echo number_format($book['price'], 2); ?></div>
                                        <div style="font-size: 0.8rem; margin-top: 5px;">To: <?php echo htmlspecialchars($book['buyer_name']); ?></div>
                                        <div style="font-size: 0.7rem; color: #888;"><?php echo date('M d, Y', strtotime($book['transaction_date'])); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div style="background: linear-gradient(135deg, #fce38a 0%, #f38181 100%); padding: 10px; border-radius: 10px; color: white; font-weight: bold; text-align: center; margin-bottom: 15px;">
                                        <?php echo $book['type'] == 'Sell' ? 'For Sale: ₹'.$book['price'] : 'Lending: Free'; ?>
                                        <div style="font-size: 0.75rem; opacity: 0.9; margin-top: 2px;">Active Listing</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <a href="?delete=<?php echo $book['id']; ?>" onclick="return confirm('Remove Listing?')" style="color: #d32f2f; font-size: 0.8rem; text-decoration: none;">Remove Listing</a>
                                    </div>
                                <?php endif; ?>
                             </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        </main>
    </div>

    <!-- MODAL: Seller Selection -->
    <div class="modal" id="sellerModal">
        <div class="modal-content" style="width: 500px;">
            <h3 style="margin-bottom: 5px;" id="sellerModalTitle">Book Title</h3>
            <div style="margin-bottom: 15px; color: #666; font-size: 0.9rem;">
                <span id="sellerModalAuthor">Author</span> • <span id="sellerModalPages">0 pages</span>
            </div>
            <p style="margin-bottom: 15px; font-size: 0.95rem; font-weight: 600;">Available Options:</p>
            <div id="sellerList" style="max-height: 400px; overflow-y: auto;">
                <!-- Populated by JS -->
            </div>
            <div style="margin-top: 20px; text-align: right;">
                <button onclick="closeModal('sellerModal')" class="btn btn-secondary">Close</button>
            </div>
        </div>
    </div>

    <!-- MODAL: Add Personal Book -->
    <div class="modal" id="addPersonalModal">
        <div class="modal-content">
            <h3>Add to Tracker 📊</h3>
            <form method="POST" onsubmit="return validateForm(this)">
                <input type="hidden" name="action" value="add_personal">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div class="form-group">
                    <label>Author</label>
                    <input type="text" name="author" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div class="form-group">
                    <label>Total Pages</label>
                    <input type="number" name="total_pages" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-input" onblur="validateField(this)" oninput="clearError(this)">
                        <option value="reading">Currently Reading</option>
                        <option value="wishlist">Wishlist</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addPersonalModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Track It</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="sellBookModal">
        <div class="modal-content">
            <h3>Sell a Book 💰</h3>
            <form method="POST" onsubmit="return validateForm(this)">
                <input type="hidden" name="action" value="sell_book">
                <input type="hidden" name="type" value="Sell">

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div class="form-group">
                    <label>Author</label>
                    <input type="text" name="author" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div class="form-group">
                    <label>Total Pages</label>
                    <input type="number" name="total_pages" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group custom-select-wrapper">
                        <label>Category</label>
                        <input type="text" name="category" class="custom-select-trigger form-input" placeholder="Select or type..." autocomplete="off" onfocus="toggleCustomSelect(this)" onblur="setTimeout(() => closeCustomSelect(this), 200)" oninput="filterOptions(this)">
                        <div class="custom-options">
                            <?php foreach($all_categories as $ac) { if($ac=='All') continue; 
                                echo "<div class='custom-option' onclick=\"selectOption(this, '$ac')\">$ac</div>"; 
                            } ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition" class="form-input" onblur="validateField(this)" oninput="clearError(this)">
                            <option>Good</option><option>Like New</option><option>New</option><option>Fair</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Price (₹)</label>
                    <input type="number" name="price" step="0.01" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                
                <div class="form-group">
                    <label>Quantity (How many copies?)</label>
                    <input type="number" name="quantity" class="form-input" value="1" min="1" required>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('sellBookModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">List Book(s)</button>
                </div>
            </form>
        </div>
    </div>



    <!-- MODAL: Lend Book -->
    <div class="modal" id="lendBookModal">
        <div class="modal-content">
            <h3>Lend a Book 🤝</h3>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;">Share your knowledge. Lend a book for free.</p>
            <form method="POST" onsubmit="return validateForm(this)">
                <input type="hidden" name="action" value="sell_book">
                <input type="hidden" name="type" value="Borrow">
                <input type="hidden" name="price" value="0.00">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div class="form-group">
                    <label>Author</label>
                    <input type="text" name="author" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div class="form-group">
                    <label>Total Pages</label>
                    <input type="number" name="total_pages" class="form-input" required onblur="validateField(this)" oninput="clearError(this)">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group custom-select-wrapper">
                        <label>Category</label>
                        <input type="text" name="category" class="custom-select-trigger form-input" placeholder="Select or type..." autocomplete="off" onfocus="toggleCustomSelect(this)" onblur="setTimeout(() => closeCustomSelect(this), 200)" oninput="filterOptions(this)">
                        <div class="custom-options">
                            <?php foreach($all_categories as $ac) { if($ac=='All') continue; 
                                echo "<div class='custom-option' onclick=\"selectOption(this, '$ac')\">$ac</div>"; 
                            } ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition" class="form-input" onblur="validateField(this)" oninput="clearError(this)">
                            <option>Good</option><option>Like New</option><option>New</option><option>Fair</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Quantity (Copies to Lend)</label>
                    <input type="number" name="quantity" class="form-input" value="1" min="1" required>
                </div>
                
                <div class="form-group">
                    <label>Lending Duration (Days)</label>
                    <input type="number" name="duration" class="form-input" value="14" min="1" required>
                    <small style="color:#888;">Days before it should be returned.</small>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('lendBookModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">List for Lending</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Active Loans -->
    <div class="modal" id="loansModal">
        <div class="modal-content" style="width: 500px;">
            <h3>Active Loans 🤝</h3>
            <p style="margin-bottom: 20px; color: #666;">Books you are borrowing or lending right now.</p>
            
            <div style="max-height: 60vh; overflow-y: auto; padding-right: 5px;">
                <!-- ACTIVE BORROWING -->
                <h4 style="margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 8px; color: var(--primary);">📖 Currently Borrowing (In)</h4>
                <?php if (empty($borrowed_active)): ?>
                    <div style="padding: 15px; color: #888; font-size: 0.9rem; font-style: italic;">You haven't borrowed any books.</div>
                <?php else: ?>
                    <ul class="history-list">
                        <?php foreach($borrowed_active as $loan): ?>
                            <li class="history-item">
                                <div>
                                    <div style="font-weight: bold;"><?php echo htmlspecialchars($loan['title']); ?></div>
                                    <div style="font-size: 0.8rem; color: #666;">From: <strong><?php echo htmlspecialchars($loan['lender_name']); ?></strong></div>
                                </div>
                                <?php 
                                    $days_left = (strtotime($loan['due_date']) - time()) / (60 * 60 * 24);
                                    $status_color = $days_left < 0 ? '#d32f2f' : '#1976d2';
                                    $status_text = $days_left < 0 ? 'Overdue' : 'Due in ' . round($days_left) . 'd';
                                ?>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.8rem; font-weight: bold; color: <?php echo $status_color; ?>; background: rgba(0,0,0,0.05); padding: 4px 8px; border-radius: 5px;">
                                        <?php echo $status_text; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- ACTIVE LENDING -->
                <h4 style="margin-top: 25px; border-bottom: 2px solid #eee; padding-bottom: 8px; color: var(--warning);">🤝 Currently Lent Out (Out)</h4>
                <?php if (empty($lent_active)): ?>
                    <div style="padding: 15px; color: #888; font-size: 0.9rem; font-style: italic;">No books currently lent out.</div>
                <?php else: ?>
                    <ul class="history-list">
                        <?php foreach($lent_active as $loan): ?>
                            <li class="history-item">
                                <div>
                                    <div style="font-weight: bold;"><?php echo htmlspecialchars($loan['title']); ?></div>
                                    <div style="font-size: 0.8rem; color: #666;">To: <strong><?php echo htmlspecialchars($loan['borrower_name']); ?></strong></div>
                                </div>
                                <?php 
                                    $days_left = (strtotime($loan['due_date']) - time()) / (60 * 60 * 24);
                                    $status_color = $days_left < 0 ? '#d32f2f' : '#f57c00';
                                    $status_text = $days_left < 0 ? 'Overdue' : 'Returns in ' . round($days_left) . 'd';
                                ?>
                                <div style="font-size: 0.8rem; font-weight: bold; color: <?php echo $status_color; ?>; background: rgba(0,0,0,0.05); padding: 4px 8px; border-radius: 5px;">
                                    <?php echo $status_text; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('loansModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- MODAL: History -->
    <div class="modal" id="historyModal">
        <div class="modal-content" style="width: 500px;">
            <h3>Transaction Log 📜</h3>
            <p style="margin-bottom: 20px; color: #666;">Past sales and returned items.</p>
            
            <div style="max-height: 60vh; overflow-y: auto; padding-right: 5px;">
                <!-- RETURN HISTORY -->
                <h4 style="margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 8px; color: var(--secondary);">↩️ Return History</h4>
                <?php if (empty($returned_history)): ?>
                    <div style="padding: 15px; color: #888; font-size: 0.9rem; font-style: italic;">No return records found.</div>
                <?php else: ?>
                    <ul class="history-list">
                        <?php foreach($returned_history as $ret): 
                            $date = date('M j', strtotime($ret['transaction_date']));
                            // Simplified logic for clarity
                            if ($ret['seller_id'] == $user_id) {
                                // I returned
                                $action = "You returned";
                                $target = "to " . $ret['returner_name']; // Using the alias from fetch
                            } else {
                                // Returned to me
                                $action = "Book returned";
                                $target = "to You";
                            }
                        ?>
                        <li class="history-item" style="opacity: 0.8;">
                             <div>
                                <div style="font-weight: bold; font-size: 0.9rem;"><?php echo htmlspecialchars($ret['title']); ?></div>
                                <div style="font-size: 0.75rem; color: #666;">
                                    <?php if($ret['seller_id'] == $user_id): ?>
                                        You returned to <strong><?php echo htmlspecialchars($ret['returner_name']); ?></strong>
                                    <?php else: ?>
                                        <strong><?php echo htmlspecialchars($ret['owner_name']); ?></strong> returned to You
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size: 0.75rem; color: #999;"><?php echo $date; ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <!-- SALES HISTORY -->
                <h4 style="margin-top: 25px; border-bottom: 2px solid #eee; padding-bottom: 5px; color: var(--success);">💰 Sales History</h4>
                <?php if (empty($sold_history)): ?>
                    <div style="padding: 15px; color: #888; font-size: 0.9rem; font-style: italic;">No sales history yet.</div>
                <?php else: ?>
                    <ul class="history-list">
                        <?php foreach($sold_history as $sale): ?>
                            <li class="history-item">
                                <div>
                                    <div style="font-weight: bold;"><?php echo htmlspecialchars($sale['title']); ?></div>
                                    <div style="font-size: 0.8rem; color: #666;">Sold to: <?php echo htmlspecialchars($sale['buyer_name']); ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; color: var(--success);">+₹<?php echo number_format($sale['price'], 2); ?></div>
                                    <div style="font-size: 0.75rem; color: #999;"><?php echo date('M j, Y', strtotime($sale['transaction_date'])); ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('historyModal')">Close</button>
            </div>
        </div>
    </div>




    <!-- MODAL: Razorpay-Style Payment -->
    <div class="modal" id="paymentModal">
        <div class="modal-content" style="width: 420px; padding: 0; overflow: hidden; border-radius: 12px;">
            <!-- Razorpay Header -->
            <div style="background: linear-gradient(135deg, #3395FF 0%, #2B7AE4 100%); padding: 20px; color: white;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 40px; height: 40px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 1.2rem;">📚</span>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 1rem;">Fiora Book Hub</div>
                            <div style="font-size: 0.75rem; opacity: 0.9;" id="payTitle2">Book Title</div>
                        </div>
                    </div>
                    <button onclick="closeModal('paymentModal')" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: 1.2rem;">×</button>
                </div>
                <div style="margin-top: 15px; text-align: center;">
                    <div style="font-size: 0.8rem; opacity: 0.8;">Amount to Pay</div>
                    <div style="font-size: 2rem; font-weight: 800;" id="payAmountHeader">₹0.00</div>
                </div>
            </div>
            
            <!-- Payment Methods Tabs -->
            <div style="background: #f8f9fa; padding: 0;">
                <div style="display: flex; border-bottom: 1px solid #e0e0e0;" id="paymentTabs">
                    <button class="pay-tab active" onclick="switchPayTab('upi')" data-tab="upi" style="flex: 1; padding: 12px 8px; background: white; border: none; border-bottom: 2px solid #3395FF; color: #3395FF; font-weight: 600; font-size: 0.8rem; cursor: pointer;">
                        <div>📱</div> UPI
                    </button>
                    <button class="pay-tab" onclick="switchPayTab('card')" data-tab="card" style="flex: 1; padding: 12px 8px; background: #f8f9fa; border: none; border-bottom: 2px solid transparent; color: #666; font-weight: 600; font-size: 0.8rem; cursor: pointer;">
                        <div>💳</div> Cards
                    </button>
                    <button class="pay-tab" onclick="switchPayTab('netbanking')" data-tab="netbanking" style="flex: 1; padding: 12px 8px; background: #f8f9fa; border: none; border-bottom: 2px solid transparent; color: #666; font-weight: 600; font-size: 0.8rem; cursor: pointer;">
                        <div>🏦</div> Net Banking
                    </button>
                    <button class="pay-tab" onclick="switchPayTab('wallet')" data-tab="wallet" style="flex: 1; padding: 12px 8px; background: #f8f9fa; border: none; border-bottom: 2px solid transparent; color: #666; font-weight: 600; font-size: 0.8rem; cursor: pointer;">
                        <div>👛</div> Wallet
                    </button>
                </div>
                
                <!-- UPI Tab Content -->
                <div id="tabUpi" class="pay-tab-content" style="padding: 20px; display: block;">
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 8px;">Enter UPI ID</label>
                        <input type="text" id="upiId" placeholder="yourname@upi" style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/UPI-Logo-vector.svg/1200px-UPI-Logo-vector.svg.png" alt="UPI" style="height: 25px; opacity: 0.7;">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span style="font-size: 0.75rem; padding: 4px 8px; background: #e8f5e9; border-radius: 4px; color: #2e7d32;">GPay</span>
                            <span style="font-size: 0.75rem; padding: 4px 8px; background: #e3f2fd; border-radius: 4px; color: #1565c0;">PhonePe</span>
                            <span style="font-size: 0.75rem; padding: 4px 8px; background: #fff3e0; border-radius: 4px; color: #e65100;">Paytm</span>
                        </div>
                    </div>
                </div>
                
                <!-- Card Tab Content -->
                <div id="tabCard" class="pay-tab-content" style="padding: 20px; display: none;">
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 6px;">Card Number</label>
                        <input type="text" placeholder="1234 5678 9012 3456" maxlength="19" style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 6px;">Expiry</label>
                            <input type="text" placeholder="MM/YY" maxlength="5" style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 6px;">CVV</label>
                            <input type="password" placeholder="•••" maxlength="4" style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; opacity: 0.6;">
                        <span style="font-size: 1.5rem;">💳</span>
                        <span style="font-size: 0.75rem; color: #666;">Visa, Mastercard, RuPay supported</span>
                    </div>
                </div>
                
                <!-- Net Banking Tab Content -->
                <div id="tabNetbanking" class="pay-tab-content" style="padding: 20px; display: none;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="border: 1px solid #ddd; padding: 12px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3395FF'" onmouseout="this.style.borderColor='#ddd'">
                            <input type="radio" name="bank" checked> <span style="font-weight: 500;">HDFC Bank</span>
                        </label>
                        <label style="border: 1px solid #ddd; padding: 12px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3395FF'" onmouseout="this.style.borderColor='#ddd'">
                            <input type="radio" name="bank"> <span style="font-weight: 500;">ICICI Bank</span>
                        </label>
                        <label style="border: 1px solid #ddd; padding: 12px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3395FF'" onmouseout="this.style.borderColor='#ddd'">
                            <input type="radio" name="bank"> <span style="font-weight: 500;">SBI</span>
                        </label>
                        <label style="border: 1px solid #ddd; padding: 12px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3395FF'" onmouseout="this.style.borderColor='#ddd'">
                            <input type="radio" name="bank"> <span style="font-weight: 500;">Axis Bank</span>
                        </label>
                    </div>
                    <select style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-top: 12px; font-size: 0.9rem; max-height: 200px;">
                        <option value="">Select Other Bank...</option>
                        <optgroup label="Public Sector Banks">
                            <option>Allahabad Bank</option>
                            <option>Andhra Bank</option>
                            <option>Bank of Baroda</option>
                            <option>Bank of India</option>
                            <option>Bank of Maharashtra</option>
                            <option>Canara Bank</option>
                            <option>Central Bank of India</option>
                            <option>Corporation Bank</option>
                            <option>Dena Bank</option>
                            <option>Indian Bank</option>
                            <option>Indian Overseas Bank</option>
                            <option>Oriental Bank of Commerce</option>
                            <option>Punjab & Sind Bank</option>
                            <option>Punjab National Bank</option>
                            <option>State Bank of India</option>
                            <option>Syndicate Bank</option>
                            <option>UCO Bank</option>
                            <option>Union Bank of India</option>
                            <option>United Bank of India</option>
                            <option>Vijaya Bank</option>
                        </optgroup>
                        <optgroup label="Private Sector Banks">
                            <option>Axis Bank</option>
                            <option>Bandhan Bank</option>
                            <option>Catholic Syrian Bank</option>
                            <option>City Union Bank</option>
                            <option>DCB Bank</option>
                            <option>Dhanlaxmi Bank</option>
                            <option>Federal Bank</option>
                            <option>HDFC Bank</option>
                            <option>ICICI Bank</option>
                            <option>IDBI Bank</option>
                            <option>IDFC First Bank</option>
                            <option>IndusInd Bank</option>
                            <option>Jammu & Kashmir Bank</option>
                            <option>Karnataka Bank</option>
                            <option>Karur Vysya Bank</option>
                            <option>Kotak Mahindra Bank</option>
                            <option>Lakshmi Vilas Bank</option>
                            <option>Nainital Bank</option>
                            <option>RBL Bank</option>
                            <option>South Indian Bank</option>
                            <option>Tamilnad Mercantile Bank</option>
                            <option>Yes Bank</option>
                        </optgroup>
                        <optgroup label="Small Finance Banks">
                            <option>AU Small Finance Bank</option>
                            <option>Capital Small Finance Bank</option>
                            <option>Equitas Small Finance Bank</option>
                            <option>ESAF Small Finance Bank</option>
                            <option>Fincare Small Finance Bank</option>
                            <option>Jana Small Finance Bank</option>
                            <option>North East Small Finance Bank</option>
                            <option>Suryoday Small Finance Bank</option>
                            <option>Ujjivan Small Finance Bank</option>
                            <option>Utkarsh Small Finance Bank</option>
                        </optgroup>
                        <optgroup label="Payments Banks">
                            <option>Airtel Payments Bank</option>
                            <option>India Post Payments Bank</option>
                            <option>Jio Payments Bank</option>
                            <option>Paytm Payments Bank</option>
                            <option>Fino Payments Bank</option>
                            <option>NSDL Payments Bank</option>
                        </optgroup>
                        <optgroup label="Regional Rural Banks">
                            <option>Aryavart Bank</option>
                            <option>Baroda Gujarat Gramin Bank</option>
                            <option>Baroda Rajasthan Kshetriya Gramin Bank</option>
                            <option>Baroda UP Bank</option>
                            <option>Chaitanya Godavari Gramin Bank</option>
                            <option>Chhattisgarh Rajya Gramin Bank</option>
                            <option>Dakshin Bihar Gramin Bank</option>
                            <option>Ellaquai Dehati Bank</option>
                            <option>Himachal Pradesh Gramin Bank</option>
                            <option>J&K Grameen Bank</option>
                            <option>Jharkhand Rajya Gramin Bank</option>
                            <option>Karnataka Gramin Bank</option>
                            <option>Karnataka Vikas Grameena Bank</option>
                            <option>Kerala Gramin Bank</option>
                            <option>Madhya Pradesh Gramin Bank</option>
                            <option>Madhyanchal Gramin Bank</option>
                            <option>Maharashtra Gramin Bank</option>
                            <option>Manipur Rural Bank</option>
                            <option>Meghalaya Rural Bank</option>
                            <option>Mizoram Rural Bank</option>
                            <option>Nagaland Rural Bank</option>
                            <option>Odisha Gramya Bank</option>
                            <option>Paschim Banga Gramin Bank</option>
                            <option>Prathama UP Gramin Bank</option>
                            <option>Puduvai Bharathiar Grama Bank</option>
                            <option>Punjab Gramin Bank</option>
                            <option>Rajasthan Marudhara Gramin Bank</option>
                            <option>Saptagiri Grameena Bank</option>
                            <option>Sarva Haryana Gramin Bank</option>
                            <option>Saurashtra Gramin Bank</option>
                            <option>Tamil Nadu Grama Bank</option>
                            <option>Telangana Grameena Bank</option>
                            <option>Tripura Gramin Bank</option>
                            <option>Utkal Grameen Bank</option>
                            <option>Uttar Bihar Gramin Bank</option>
                            <option>Uttarakhand Gramin Bank</option>
                            <option>Uttarbanga Kshetriya Gramin Bank</option>
                            <option>Vidharbha Konkan Gramin Bank</option>
                        </optgroup>
                        <optgroup label="Cooperative Banks">
                            <option>Andhra Pradesh State Coop Bank</option>
                            <option>Bombay Mercantile Coop Bank</option>
                            <option>Gujarat State Coop Bank</option>
                            <option>Kalupur Commercial Coop Bank</option>
                            <option>Mehsana Urban Coop Bank</option>
                            <option>NKGSB Coop Bank</option>
                            <option>Rajkot Nagrik Sahakari Bank</option>
                            <option>Saraswat Coop Bank</option>
                            <option>Shamrao Vithal Coop Bank</option>
                            <option>Surat People's Coop Bank</option>
                            <option>Thane Bharat Sahakari Bank</option>
                            <option>Zoroastrian Coop Bank</option>
                        </optgroup>
                    </select>
                </div>
                
                <!-- Wallet Tab Content -->
                <div id="tabWallet" class="pay-tab-content" style="padding: 20px; display: none;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3395FF'" onmouseout="this.style.borderColor='#ddd'">
                            <input type="radio" name="wallet" checked> <span style="font-weight: 500;">💙 Paytm</span>
                        </label>
                        <label style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3395FF'" onmouseout="this.style.borderColor='#ddd'">
                            <input type="radio" name="wallet"> <span style="font-weight: 500;">💜 PhonePe</span>
                        </label>
                        <label style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3395FF'" onmouseout="this.style.borderColor='#ddd'">
                            <input type="radio" name="wallet"> <span style="font-weight: 500;">🟡 Amazon Pay</span>
                        </label>
                        <label style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#3395FF'" onmouseout="this.style.borderColor='#ddd'">
                            <input type="radio" name="wallet"> <span style="font-weight: 500;">🟢 Mobikwik</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Pay Button -->
            <div style="padding: 20px; background: white; border-top: 1px solid #eee;">
                <button onclick="processPayment()" id="btnPay" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #3395FF 0%, #2B7AE4 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;">
                    <span>🔒</span> Pay <span id="payBtnAmount">₹0.00</span>
                </button>
                <div style="text-align: center; margin-top: 12px; display: flex; align-items: center; justify-content: center; gap: 5px;">
                    <span style="font-size: 0.7rem; color: #888;">Secured by</span>
                    <span style="font-size: 0.8rem; font-weight: 700; color: #3395FF;">Razorpay</span>
                    <span style="font-size: 0.7rem; color: #888;">🔐</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Hidden elements for backward compatibility
    const payTitleHidden = document.createElement('span');
    payTitleHidden.id = 'payTitle';
    payTitleHidden.style.display = 'none';
    document.body.appendChild(payTitleHidden);
    
    const paySellerHidden = document.createElement('span');
    paySellerHidden.id = 'paySeller';
    paySellerHidden.style.display = 'none';
    document.body.appendChild(paySellerHidden);
    
    const payAmountHidden = document.createElement('span');
    payAmountHidden.id = 'payAmount';
    payAmountHidden.style.display = 'none';
    document.body.appendChild(payAmountHidden);
    
    function switchPayTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.pay-tab-content').forEach(c => c.style.display = 'none');
        // Show selected tab content
        document.getElementById('tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1)).style.display = 'block';
        
        // Update tab styles
        document.querySelectorAll('.pay-tab').forEach(t => {
            t.style.background = '#f8f9fa';
            t.style.borderBottom = '2px solid transparent';
            t.style.color = '#666';
        });
        const activeTab = document.querySelector(`.pay-tab[data-tab="${tabName}"]`);
        activeTab.style.background = 'white';
        activeTab.style.borderBottom = '2px solid #3395FF';
        activeTab.style.color = '#3395FF';
    }
    </script>

    <!-- MODAL: List to Market -->
    <div class="modal" id="listToMarketModal">
        <div class="modal-content" style="width: 500px;">
            <h3 style="margin-bottom: 15px;">List to Marketplace</h3>
            <form method="POST" action="reading.php">
                <input type="hidden" name="action" value="relist_book">
                <input type="hidden" name="book_id" id="listBookId">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:600;">Title</label>
                        <input type="text" name="title" id="listTitle" class="form-input" required>
                    </div>
                    <div>
                         <label style="display:block; font-size:0.8rem; font-weight:600;">Author</label>
                         <input type="text" name="author" id="listAuthor" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                     <label style="font-size:0.8rem;">Category</label>
                     <input type="text" name="category" id="listCategory" list="categoryList" class="form-input" required placeholder="Select...">
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                     <div>
                        <label style="font-size:0.8rem;">Total Pages</label>
                        <input type="number" name="total_pages" id="listPages" class="form-input" required>
                     </div>
                     <div>
                        <label style="font-size:0.8rem;">Condition</label>
                        <select name="condition" id="listCondition" class="form-input">
                            <option>Good</option><option>Like New</option><option>New</option><option>Fair</option>
                        </select>
                     </div>
                </div>

                <hr style="border:0; border-top:1px dashed #eee; margin:15px 0;">

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 8px;">I want to...</label>
                    <div style="display: flex; gap: 10px;">
                        <label style="flex: 1; border: 1px solid #ddd; padding: 10px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;">
                            <input type="radio" name="type" value="Borrow" checked onchange="togglePriceField(false)"> 🤝 Lend (Free)
                        </label>
                        <label style="flex: 1; border: 1px solid #ddd; padding: 10px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;">
                            <input type="radio" name="type" value="Sell" onchange="togglePriceField(true)"> 💰 Sell
                        </label>
                    </div>
                </div>

                <div id="priceField" style="margin-bottom: 20px; display: none;">
                    <label style="display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 5px;">Price (₹)</label>
                    <input type="number" name="price" id="listPrice" step="0.01" class="form-input" placeholder="0.00">
                </div>

                <div id="durationField" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 5px;">Lending Duration (Days)</label>
                    <input type="number" name="duration" id="listDuration" value="14" min="1" class="form-input">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Confirm Listing</button>
                <button type="button" onclick="closeModal('listToMarketModal')" class="btn" style="width: 100%; margin-top: 10px; color: #888;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- MODAL: Payment Success -->
    <div class="modal" id="successModal">
        <div class="modal-content" style="width: 350px; text-align: center; padding: 40px;">
            <div style="font-size: 4rem; color: var(--success); margin-bottom: 20px;">✅</div>
            <h2 style="margin-bottom: 10px;">Payment Successful!</h2>
            <p style="color: #666; margin-bottom: 20px;">Your purchase has been confirmed.</p>
            <p style="font-size: 0.9rem; color: #888;">Redirecting to your library...</p>
        </div>
    </div>

    <form id="actualBuyForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="buy_book">
        <input type="hidden" name="book_id" id="buyBookId">
    </form>
    <form id="genericForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="genericAction">
        <input type="hidden" name="book_id" id="genericBookId">
    </form>

    <script>
        let currentBookId = null;

        function openListModal(book) {
            document.getElementById('listBookId').value = book.id;
            document.getElementById('listTitle').value = book.title;
            document.getElementById('listAuthor').value = book.author;
            document.getElementById('listPages').value = book.total_pages || 0;
            document.getElementById('listCategory').value = book.category || '';
            document.getElementById('listCondition').value = book.condition || 'Good';
            document.getElementById('listDuration').value = book.lending_duration || 14;
            
            // Reset state
            document.querySelector('input[name="type"][value="Borrow"]').checked = true;
            togglePriceField(false);
            openModal('listToMarketModal');
        }

        function togglePriceField(show) {
            const pField = document.getElementById('priceField');
            const dField = document.getElementById('durationField');
            
            if (show) {
                // Sell
                pField.style.display = 'block';
                dField.style.display = 'none';
                pField.querySelector('input').focus();
            } else {
                // Borrow
                pField.style.display = 'none';
                dField.style.display = 'block';
            }
        }

        function openModal(id) { 
            // Show popup alert for sell/lend modals
            if (id === 'sellBookModal') {
                alert('📚 Note: You are listing a pre-owned/used book for sale. Buyers will be notified that this is a used item.');
            } else if (id === 'lendBookModal') {
                alert('📚 Note: You are listing a pre-owned/used book for lending.');
            }
            document.getElementById(id).classList.add('active'); 
        }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        
        async function updateProgress(bookId, currentPage) {
            const formData = new FormData();
            formData.append('action', 'update_progress');
            formData.append('book_id', bookId);
            formData.append('current_page', currentPage);
            await fetch('reading.php', { method: 'POST', body: formData });
            location.reload();
        }

        async function updateTotalPages(bookId, totalPages) {
            const formData = new FormData();
            formData.append('action', 'set_total_pages');
            formData.append('book_id', bookId);
            formData.append('total_pages', totalPages);
            await fetch('reading.php', { method: 'POST', body: formData });
            location.reload();
        }

        // --- Payment Logic (Razorpay Style Demo) ---
        function openPaymentModal(id, title, price, seller) {
            currentBookId = id;
            document.getElementById('payTitle').innerText = title;
            document.getElementById('paySeller').innerText = seller;
            
            const formattedPrice = '₹' + parseFloat(price).toFixed(2);
            document.getElementById('payAmount').innerText = formattedPrice;
            document.getElementById('payBtnAmount').innerText = formattedPrice;
            
            // Set Razorpay-style header
            document.getElementById('payTitle2').innerText = title;
            document.getElementById('payAmountHeader').innerText = formattedPrice;
            
            // Reset to UPI tab
            switchPayTab('upi');
            
            openModal('paymentModal');
        }

        function processPayment() {
            const btn = document.getElementById('btnPay');
            const originalText = btn.innerHTML;
            
            // 1. Loading State
            btn.innerHTML = 'Processing...';
            btn.disabled = true;
            btn.style.opacity = '0.7';

            // 2. Simulate Payment (1.5s)
            setTimeout(() => {
                closeModal('paymentModal');
                
                // 3. Show Success
                openModal('successModal');
                
                // 4. Submit Form after delay
                setTimeout(() => {
                    document.getElementById('buyBookId').value = currentBookId;
                    document.getElementById('actualBuyForm').submit();
                }, 2000);
                
            }, 1500);
        }

        function openBorrowedDetails(book) {
            document.getElementById('sellerModalTitle').innerText = book.title;
            document.getElementById('sellerModalAuthor').innerText = book.author || 'Unknown';
            document.getElementById('sellerModalPages').innerText = (book.total_pages || '?') + ' pages';
            
            const list = document.getElementById('sellerList');
            list.innerHTML = '';
            
            const row = document.createElement('div');
            row.className = 'history-item';
            
            row.innerHTML = `
                <div>
                    <div style="font-weight:bold;">Lender: ${book.lender_name || 'Library'}</div>
                    <div style="font-size:0.8rem; color:#666;">Due Date: ${book.due_date || 'No Date'}</div>
                </div>
                <div style="text-align:right;">
                     <form method="POST" onsubmit="return confirm('Return this book found?');">
                        <input type="hidden" name="action" value="buy_book">
                        <input type="hidden" name="book_id" value="${book.id}">
                        <button type="submit" class="btn btn-primary" style="background:var(--secondary); border:none;">Return Book</button>
                     </form>
                </div>
            `;
            list.appendChild(row);
            
            openModal('sellerModal');
        }

        function openSellerModal(copies) {
            // Show popup alert for buyers about used book
            alert('📦 Note: This is a pre-owned/used book.');
            
            if (copies.length > 0) {
                document.getElementById('sellerModalTitle').innerText = copies[0].title;
                document.getElementById('sellerModalAuthor').innerText = copies[0].author || 'Unknown';
                document.getElementById('sellerModalPages').innerText = (copies[0].total_pages || '?') + ' pages';
            }
            const list = document.getElementById('sellerList');
            list.innerHTML = '';
            
            copies.forEach(copy => {
                if (copy.status === 'Sold') return; // Filter Sold items

                const row = document.createElement('div');
                row.className = 'history-item'; 
                
                // Define Display Variables
                let actionHtml = '';
                const isSelf = copy.is_mine;
                const isSell = (copy.type && copy.type.toLowerCase() === 'sell');

                let typeLabel = isSell 
                    ? `<span style="color:green; font-weight:bold;">💰 Sell - ₹${parseFloat(copy.price).toFixed(2)}</span>` 
                    : `<span style="color:blue; font-weight:bold;">🤝 Free Borrow</span>`;
                
                if (copy.is_available) {
                     if (isSelf) {
                         actionHtml = `<button onclick="if(confirm('Are you sure you want to remove this listing?')) submitGeneric('delete_book', ${copy.id})" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca;">Remove Listing</button>`;
                     } else if (isSell) {
                         const safeTitle = copy.title.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                         const safeOwner = copy.owner_name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                         actionHtml = `<button onclick="closeModal('sellerModal'); openPaymentModal(${copy.id}, '${safeTitle}', '${copy.price}', '${safeOwner}')" class="btn btn-primary" style="padding: 4px 10px; font-size: 0.8rem;">Buy it</button>`;
                     } else {
                         actionHtml = `<button onclick="submitGeneric('buy_book', ${copy.id})" class="btn btn-primary" style="padding: 4px 10px; font-size: 0.8rem;">Borrow</button>`;
                     }
                } else {
                     // UNAVAILABLE (Lent Out)
                     if (copy.due_date) {
                         // Format Date
                         const dateObj = new Date(copy.due_date);
                         const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                         typeLabel += ` <span style="display:block; font-size:0.75rem; color:#e67e22; margin-top:2px;">📅 Available: ${dateStr}</span>`;
                     }

                     if (copy.is_reserved_by_me) {
                        actionHtml = `<button disabled class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.8rem;">Waitlisted</button>`;
                     } else {
                         actionHtml = `<button onclick="submitGeneric('prebook', ${copy.id})" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.8rem; border:1px solid #ccc; color: #555;">Pre-book</button>`;
                     }
                }

                row.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div>
                            <div style="font-weight:bold;">${copy.owner_name} ${isSelf ? '(You)' : ''}</div>
                            <div style="font-size:0.8rem; color:#666;">Condition: ${copy.condition} • ${typeLabel}</div>
                        </div>
                        <div style="text-align:right;">
                            ${actionHtml}
                        </div>
                    </div>
                `;
                list.appendChild(row);
            });
            
            openModal('sellerModal');
        }

        // Generic Form Submitter
        function submitGeneric(action, bookId) {
            if (action === 'buy_book' && !confirm('Borrow this book for free?')) return;
            // ... (rest of logic handles forms)
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="book_id" value="${bookId}">`;
            document.body.appendChild(form);
            form.submit();
        }

        // Toggle Custom Category Input
        function toggleCustomCat(select, inputId) {
            const input = document.getElementById(inputId);
            if (select.value === 'Other') {
                input.style.display = 'block';
                input.required = true;
            } else {
                input.style.display = 'none';
                input.required = false;
                input.value = ''; 
            }
        }

        // Custom Combobox Logic
        function toggleCustomSelect(input) {
            const options = input.nextElementSibling;
            options.style.display = 'block';
            filterOptions(input);
        }
        
        function closeCustomSelect(input) {
             // Delay closing to allow click event to register
             // setTimeout handled in inline HTML, but safer to rely on document click or tight coupling
             // The inline onblur with timeout handles it.
        }

        function filterOptions(input) {
            const filter = input.value.toLowerCase();
            const options = input.nextElementSibling.children;
            for (let i = 0; i < options.length; i++) {
                const txt = options[i].innerText;
                if (txt.toLowerCase().indexOf(filter) > -1) {
                    options[i].style.display = "";
                } else {
                    options[i].style.display = "none";
                }
            }
        }

        function selectOption(option, val) {
            const wrapper = option.closest('.custom-select-wrapper');
            const input = wrapper.querySelector('.custom-select-trigger');
            input.value = val;
            wrapper.querySelector('.custom-options').style.display = 'none';
        }

        // Close Dropdown when clicking outside manually?
        // Note: The onblur handles closing mostly. 
        
        // Remove old document listener to avoid conflicts if needed, but keeping it simpler.

        // Validate Form on Submit
        function validateForm(form) {
            // Check custom category dropdown
            const catInput = form.querySelector('input[name="category"]');
            if (catInput && !catInput.value) {
                alert('Please select a Category!');
                return false;
            }

            const requiredInputs = form.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            requiredInputs.forEach(input => {
                validateField(input);
                // Check if error message exists after validation
                const parent = input.closest('.form-group') || input.parentElement;
                if (parent.querySelector('.error-message')) {
                    isValid = false;
                }
            });
            
            return isValid;
        }

        // Direct Validation Functions (Called by onblur attributes)
        function validateField(input) {
            // Only validate validatable fields
            if (!input.hasAttribute('required')) return;

            const parent = input.closest('.form-group') || input.parentElement;
            let error = parent.querySelector('.error-message');
            
            if (!input.value.trim()) {
                if (!error) {
                    error = document.createElement('div');
                    error.className = 'error-message';
                    error.style.color = '#e74c3c'; 
                    error.style.fontSize = '0.75rem';
                    error.style.marginTop = '5px';
                    error.style.fontWeight = '600';
                    error.innerText = 'This field is compulsory';
                    parent.appendChild(error);
                    input.style.borderColor = '#e74c3c';
                }
            } else {
                if (error) error.remove();
                input.style.borderColor = '';
            }
        }

        function clearError(input) {
            const parent = input.closest('.form-group') || input.parentElement;
            const error = parent.querySelector('.error-message');
            if (error) {
                error.remove();
                input.style.borderColor = '';
            }
        }

        // Search Suggestions Logic
        const searchInput = document.getElementById('searchInput');
        const suggestionsBox = document.getElementById('searchSuggestions');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value;
                if (term.length < 2) {
                    suggestionsBox.style.display = 'none';
                    return;
                }

                fetch(`reading.php?action=search_suggest&term=${encodeURIComponent(term)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            suggestionsBox.innerHTML = '';
                            data.forEach(item => {
                                // Determine if matched title or author
                                const div = document.createElement('div');
                                div.style.padding = '8px 12px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #eee';
                                div.style.fontSize = '0.9rem';
                                div.onmouseover = () => div.style.background = '#f5f5f5';
                                div.onmouseout = () => div.style.background = 'white';
                                
                                // Highlight match
                                div.innerHTML = `<strong>${item.title}</strong> <span style='color:#888; font-size:0.8rem;'>by ${item.author}</span>`;
                                
                                div.onclick = () => {
                                    searchInput.value = item.title; // Set search to title
                                    suggestionsBox.style.display = 'none';
                                    searchInput.form.submit(); // Auto submit
                                };
                                suggestionsBox.appendChild(div);
                            });
                            suggestionsBox.style.display = 'block';
                        } else {
                            suggestionsBox.style.display = 'none';
                        }
                    });
            });

            // Hide on outside click
            document.addEventListener('click', function(e) {
                if (e.target !== searchInput && e.target !== suggestionsBox) {
                    suggestionsBox.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
