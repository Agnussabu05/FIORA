<?php
require_once 'includes/db.php';

try {
    // 1. Update status ENUM
    $pdo->exec("ALTER TABLE books MODIFY COLUMN status ENUM('reading', 'completed', 'wishlist', 'Available', 'Sold', 'Borrowed') DEFAULT 'reading'");
    echo "Updated status ENUM.\n";

    // 2. Add Marketplace Columns if they don't exist
    $columns = [
        "ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00",
        "ADD COLUMN type ENUM('Sell', 'Borrow', 'Both') DEFAULT 'Sell'",
        "ADD COLUMN `condition` ENUM('New', 'Like New', 'Good', 'Fair') DEFAULT 'Good'",
        "ADD COLUMN category VARCHAR(100) DEFAULT 'Generic'",
        "ADD COLUMN image_path VARCHAR(255) DEFAULT NULL",
        "ADD COLUMN description TEXT"
    ];

    foreach ($columns as $col) {
        try {
            $pdo->exec("ALTER TABLE books $col");
            echo "Executed: ALTER TABLE books $col\n";
        } catch (PDOException $e) {
            // Ignore if column exists
            echo "Skipped (or error): " . $e->getMessage() . "\n";
        }
    }

    echo "Schema update complete.";

} catch (PDOException $e) {
    echo "Fatal Error: " . $e->getMessage();
}
?>
