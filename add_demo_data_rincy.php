<?php
// add_demo_data_rincy.php - Add demo data for user rincyjoseph2028
require_once 'includes/db.php';

if (!$pdo) {
    die("<h2 style='color:red'>Database connection failed!</h2>");
}

echo "<h2>Adding Demo Data for rincyjoseph2028</h2>";

// Find user ID
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute(['rincyjoseph2028']);
$user = $stmt->fetch();

if (!$user) {
    die("<h2 style='color:red'>User 'rincyjoseph2028' not found! Please create this user first.</h2>");
}

$user_id = $user['id'];
echo "<p>Found user ID: <strong>$user_id</strong></p>";

$success = 0;
$errors = [];

// =====================
// 1. ADD TASKS (Matching schema: id, user_id, title, description, deadline, priority, status, is_admin_pushed, created_at)
// =====================
echo "<h3>Adding Tasks...</h3>";
$tasks = [
    ['Complete Quarterly Report', 'Finish the quarterly project report with all data analysis', '2026-02-05 17:00:00', 'high'],
    ['Buy Weekly Groceries', 'Shopping - fruits, vegetables, milk, bread', '2026-02-01 12:00:00', 'medium'],
    ['Call Family', 'Weekly call to check on parents', '2026-02-02 18:00:00', 'low'],
    ['Prepare Team Presentation', 'Create slides for Monday team meeting', '2026-02-03 09:00:00', 'high'],
    ['Weekend Room Cleaning', 'Deep cleaning and organizing the room', '2026-02-08 10:00:00', 'medium']
];

$taskStmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, deadline, priority, status) VALUES (?, ?, ?, ?, ?, 'pending')");
foreach ($tasks as $task) {
    try {
        $taskStmt->execute([$user_id, $task[0], $task[1], $task[2], $task[3]]);
        echo "<p>✅ Task: {$task[0]}</p>";
        $success++;
    } catch (Exception $e) {
        $errors[] = "Task '{$task[0]}': " . $e->getMessage();
        echo "<p style='color:orange'>⚠️ {$task[0]}: " . $e->getMessage() . "</p>";
    }
}

// =====================
// 2. ADD HABITS (Matching schema: id, user_id, name, emoji, frequency, is_admin_pushed, created_at)
// =====================
echo "<h3>Adding Habits...</h3>";
$habits = [
    ['Morning Exercise', '🏃', 'daily'],
    ['Read 30 Minutes', '📚', 'daily'],
    ['Evening Meditation', '🧘', 'daily'],
    ['Drink Water', '💧', 'daily']
];

$habitStmt = $pdo->prepare("INSERT INTO habits (user_id, name, emoji, frequency) VALUES (?, ?, ?, ?)");
foreach ($habits as $habit) {
    try {
        $habitStmt->execute([$user_id, $habit[0], $habit[1], $habit[2]]);
        echo "<p>✅ Habit: {$habit[0]}</p>";
        $success++;
    } catch (Exception $e) {
        $errors[] = "Habit '{$habit[0]}': " . $e->getMessage();
        echo "<p style='color:orange'>⚠️ {$habit[0]}: " . $e->getMessage() . "</p>";
    }
}

// =====================
// 3. ADD FINANCE (Matching schema: id, user_id, amount, category, type, transaction_date, description)
// =====================
echo "<h3>Adding Finance Entries...</h3>";
$finances = [
    [50000, 'Salary', 'income', '2026-01-01', 'Monthly salary'],
    [15000, 'Freelance', 'income', '2026-01-15', 'Web project'],
    [5000, 'Investment', 'income', '2026-01-20', 'Dividends'],
    [12000, 'Rent', 'expense', '2026-01-05', 'Monthly rent'],
    [5000, 'Groceries', 'expense', '2026-01-10', 'Monthly groceries'],
    [1500, 'Utilities', 'expense', '2026-01-12', 'Electricity bill'],
    [800, 'Internet', 'expense', '2026-01-15', 'Broadband'],
    [2000, 'Entertainment', 'expense', '2026-01-22', 'Dinner out']
];

$financeStmt = $pdo->prepare("INSERT INTO expenses (user_id, amount, category, type, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($finances as $f) {
    try {
        $financeStmt->execute([$user_id, $f[0], $f[1], $f[2], $f[3], $f[4]]);
        $icon = $f[2] === 'income' ? '💰' : '💸';
        echo "<p>✅ {$icon} {$f[1]}: ₹{$f[0]}</p>";
        $success++;
    } catch (Exception $e) {
        $errors[] = "Finance '{$f[1]}': " . $e->getMessage();
        echo "<p style='color:orange'>⚠️ {$f[1]}: " . $e->getMessage() . "</p>";
    }
}

// =====================
// 4. ADD BOOKS (Matching schema: id, user_id, title, author, category, condition_text, type, price, image_url, status, created_at)
// =====================
echo "<h3>Adding Books...</h3>";
$books = [
    ['Atomic Habits', 'James Clear', 'Self-Help', 'Good', 'sell', 350],
    ['The Alchemist', 'Paulo Coelho', 'Fiction', 'New', 'borrow', 0]
];

$bookStmt = $pdo->prepare("INSERT INTO books (user_id, title, author, category, condition_text, type, price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'available')");
foreach ($books as $book) {
    try {
        $bookStmt->execute([$user_id, $book[0], $book[1], $book[2], $book[3], $book[4], $book[5]]);
        echo "<p>✅ Book: {$book[0]} by {$book[1]}</p>";
        $success++;
    } catch (Exception $e) {
        $errors[] = "Book '{$book[0]}': " . $e->getMessage();
        echo "<p style='color:orange'>⚠️ {$book[0]}: " . $e->getMessage() . "</p>";
    }
}

// =====================
// 5. ADD GOALS (Matching schema: id, user_id, title, description, target_date, category, progress, status, created_at)
// =====================
echo "<h3>Adding Goals...</h3>";
$goals = [
    ['Learn Spanish', 'Reach conversational level in Spanish', '2026-12-31', 'learning', 20],
    ['Save ₹1 Lakh', 'Build emergency fund', '2026-06-30', 'finance', 35],
    ['Run Marathon', 'Complete 42km marathon', '2026-11-30', 'health', 10]
];

$goalStmt = $pdo->prepare("INSERT INTO goals (user_id, title, description, target_date, category, progress, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
foreach ($goals as $goal) {
    try {
        $goalStmt->execute([$user_id, $goal[0], $goal[1], $goal[2], $goal[3], $goal[4]]);
        echo "<p>✅ Goal: {$goal[0]}</p>";
        $success++;
    } catch (Exception $e) {
        $errors[] = "Goal '{$goal[0]}': " . $e->getMessage();
        echo "<p style='color:orange'>⚠️ {$goal[0]}: " . $e->getMessage() . "</p>";
    }
}

// Summary
echo "<hr>";
if (count($errors) == 0) {
    echo "<h2 style='color:green'>✅ All $success items added successfully!</h2>";
} else {
    echo "<h2 style='color:orange'>⚠️ Added $success items with " . count($errors) . " errors</h2>";
    echo "<details><summary>View Errors</summary><pre>" . implode("\n", $errors) . "</pre></details>";
}
echo "<p>Login as <strong>rincyjoseph2028</strong> to see all the data.</p>";
echo "<p><a href='index.php' style='color:blue; font-size:1.2em'>→ Go to Dashboard</a></p>";
?>
