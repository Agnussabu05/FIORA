<?php
require_once 'includes/db.php';

try {
    // Update Goals Table
    $pdo->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS description TEXT");
    $pdo->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS target_date DATE");
    $pdo->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS category VARCHAR(50) DEFAULT 'personal'");
    $pdo->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS status ENUM('active', 'completed') DEFAULT 'active'");
    $pdo->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    // Migrate data from deadline to target_date if exists
    try {
        $pdo->exec("UPDATE goals SET target_date = deadline WHERE target_date IS NULL AND deadline IS NOT NULL");
    } catch (Exception $e) { /* deadline might not exist or already migrated */ }

    echo "Goals schema updated successfully!";
} catch (PDOException $e) {
    echo "Error updating goals schema: " . $e->getMessage();
}
?>
