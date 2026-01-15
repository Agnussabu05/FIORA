<?php
$host = 'localhost';
$dbname = 'fiora_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected. Setting up Book Exchange tables...<br>";

    // 1. Create 'books' table
    $sqlBooks = "CREATE TABLE IF NOT EXISTS books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT,
        condition_state ENUM('New', 'Like New', 'Good', 'Fair', 'Poor') DEFAULT 'Good',
        type ENUM('Sell', 'Borrow', 'Both') DEFAULT 'Sell',
        price DECIMAL(10, 2) DEFAULT 0.00,
        image_path VARCHAR(255),
        status ENUM('Available', 'Sold', 'Borrowed', 'Reserved') DEFAULT 'Available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (category),
        INDEX (status)
    )";
    $pdo->exec($sqlBooks);
    echo "Table 'books' created/checked.<br>";

    // 2. Create 'book_transactions' table
    $sqlTrans = "CREATE TABLE IF NOT EXISTS book_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        buyer_id INT NOT NULL,  -- The borrower or buyer
        seller_id INT NOT NULL, -- The owner
        transaction_type ENUM('Purchase', 'Loan') NOT NULL,
        status ENUM('Pending', 'Completed', 'Returned', 'Overdue') DEFAULT 'Pending',
        due_date DATE DEFAULT NULL,
        transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlTrans);
    echo "Table 'book_transactions' created/checked.<br>";

    echo "<b>Success! Database is ready for the Library. 📚</b>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
