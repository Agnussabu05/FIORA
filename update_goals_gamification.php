<?php
require_once 'includes/db.php';

try {
    echo "<h1>Updating Goals Schema for Gamification 🎯</h1>";

    // 1. Add 'points' to users table
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS points INT DEFAULT 0");
        echo "✅ Added 'points' column to 'users' table.<br>";
    } catch (PDOException $e) {
        echo "ℹ️ 'points' column might already exist in 'users'.<br>";
    }

    // 2. Add 'points' and 'completion_date' to goals table
    try {
        $pdo->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS points INT DEFAULT 0");
        echo "✅ Added 'points' column to 'goals' table.<br>";
    } catch (PDOException $e) {
        echo "ℹ️ 'points' column might already exist in 'goals'.<br>";
    }

    try {
        $pdo->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS completion_date DATE DEFAULT NULL");
        echo "✅ Added 'completion_date' column to 'goals' table.<br>";
    } catch (PDOException $e) {
        echo "ℹ️ 'completion_date' column might already exist in 'goals'.<br>";
    }

    echo "<h3>🚀 Schema Update Complete!</h3>";
    echo "<a href='goals.php'>Back to Goals</a>";

} catch (PDOException $e) {
    die("❌ Error updating schema: " . $e->getMessage());
}
?>
