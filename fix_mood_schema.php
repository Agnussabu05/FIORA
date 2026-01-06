<?php
require_once 'includes/db.php';

try {
    // Check if columns exist and add them if not
    $pdo->exec("ALTER TABLE mood_logs ADD COLUMN IF NOT EXISTS mood_label VARCHAR(50)");
    $pdo->exec("ALTER TABLE mood_logs ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    // Change log_date to DATE if it's currently DATETIME
    $pdo->exec("ALTER TABLE mood_logs MODIFY COLUMN log_date DATE NOT NULL");
    
    // Add unique constraint if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE mood_logs ADD UNIQUE KEY user_daily_mood (user_id, log_date)");
    } catch (Exception $e) { /* Already exists */ }

    echo "Mood logs schema updated successfully!";
} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage();
}
?>
