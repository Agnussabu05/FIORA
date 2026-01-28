<?php
require_once 'includes/db.php';

try {
    echo "<h1>Updating Finance Schema 💰</h1>";

    // 1. Finance Groups (for shared expenses)
    echo "Creating 'finance_groups'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_by INT NOT NULL,
        invite_code VARCHAR(10) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "✅<br>";

    // 2. Group Members
    echo "Creating 'finance_group_members'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES finance_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_member (group_id, user_id)
    )");
    echo "✅<br>";

    // 3. Shared Expenses
    echo "Creating 'shared_expenses'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS shared_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        paid_by INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        description VARCHAR(255) NOT NULL,
        expense_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES finance_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "✅<br>";

    // 4. Recurring Bills
    echo "Creating 'recurring_bills'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS recurring_bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        due_day INT NOT NULL CHECK (due_day BETWEEN 1 AND 31),
        category VARCHAR(50) DEFAULT 'Utilities',
        next_due_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "✅<br>";

    echo "<h3>All Finance Pro tables set up! 🚀</h3>";
    
} catch (PDOException $e) {
    die("Setup Error: " . $e->getMessage());
}
?>
