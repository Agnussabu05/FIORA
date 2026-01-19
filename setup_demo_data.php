<?php
require_once 'includes/db.php';

// 1. Add due_date column if missing
try {
    $pdo->exec("ALTER TABLE books ADD COLUMN due_date DATE DEFAULT NULL");
    echo "Added 'due_date' column.\n";
} catch (PDOException $e) {
    echo "Column 'due_date' likely exists.\n";
}

// 2. Find or Create a Dummy User (Seller)
// We need a user ID that is NOT the current logged in user (who is likely ID 1)
$seller_id = 999; // Arbitrary ID

// Check if user 999 exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$seller_id]);
if (!$stmt->fetch()) {
    // Attempt to create user 999. Use minimal columns.
    // We try/catch this insertion in case of schema mismatches
    try {
        $pdo->prepare("INSERT INTO users (id, username, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$seller_id, 'FioraLibrary', 'Fiora Library', 'lib@fiora.app', 'dummy', 'user']);
        echo "Created dummy user 'FioraLibrary' (ID: $seller_id)\n";
    } catch (Exception $e) {
        echo "Could not create dummy user: " . $e->getMessage() . "\n";
        // Fallback: Use ID 1 but then the user sees their own books? 
        // No, marketplace query hides own books. 
        // So we MUST have a 2nd user. 
        // If insert failed, maybe we are missing a required column. 
        // Let's assume the previous error was 'role' column missing.
    }
}

// 3. Insert Demo Books
$books = [
    ['The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', 'Good', 'Sell', 12.50],
    ['Atomic Habits', 'James Clear', 'Self-Help', 'New', 'Sell', 18.00],
    ['Clean Code', 'Robert C. Martin', 'Textbook', 'Good', 'Borrow', 0],
    ['Sapiens', 'Yuval Noah Harari', 'Non-Fiction', 'Like New', 'Borrow', 0],
    ['The Alchemist', 'Paulo Coelho', 'Fiction', 'Fair', 'Sell', 8.00],
    ['Introduction to Algorithms', 'CLRS', 'Textbook', 'Fair', 'Borrow', 0],
    ['Thinking, Fast and Slow', 'Daniel Kahneman', 'Non-Fiction', 'Good', 'Borrow', 0]
];

// Clean old demo books first
$pdo->prepare("DELETE FROM books WHERE user_id = ?")->execute([$seller_id]);

$stmt = $pdo->prepare("INSERT INTO books (user_id, title, author, category, `condition`, type, price, status, total_pages, current_page) VALUES (?, ?, ?, ?, ?, ?, ?, 'Available', 300, 0)");

foreach ($books as $b) {
    $stmt->execute([$seller_id, $b[0], $b[1], $b[2], $b[3], $b[4], $b[5]]);
}

echo "Inserted " . count($books) . " demo books for listing.\n";
?>
