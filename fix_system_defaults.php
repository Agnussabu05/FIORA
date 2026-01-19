<?php
require_once 'includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_defaults (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('task', 'habit') NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium'
    )");

    // Check if empty
    $count = $pdo->query("SELECT COUNT(*) FROM system_defaults")->fetchColumn();
    
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO system_defaults (type, title, description, priority) VALUES (?, ?, ?, ?)");
        $stmt->execute(['task', 'Complete Profile', 'Add your details and goals', 'high']);
        $stmt->execute(['task', 'Explore Fiora', 'Check out the new Mood and Tribe features', 'medium']);
        $stmt->execute(['habit', 'Drink Water', '', 'medium']);
    }
    
    echo "System defaults table fixed.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
