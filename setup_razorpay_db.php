<?php
require_once 'includes/db.php';

echo "<h2>Adding Razorpay Payment Columns to book_transactions</h2>";

try {
    // Add payment_id column if not exists
    $pdo->exec("ALTER TABLE book_transactions ADD COLUMN IF NOT EXISTS payment_id VARCHAR(100) DEFAULT NULL");
    echo "✅ Added 'payment_id' column<br>";
} catch (PDOException $e) {
    echo "ℹ️ 'payment_id' column might already exist<br>";
}

try {
    // Add payment_status column if not exists
    $pdo->exec("ALTER TABLE book_transactions ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'pending'");
    echo "✅ Added 'payment_status' column<br>";
} catch (PDOException $e) {
    echo "ℹ️ 'payment_status' column might already exist<br>";
}

echo "<br><strong>✨ Razorpay database setup complete!</strong><br>";
echo "<br><a href='reading.php'>Go to Book Hub</a>";
?>
