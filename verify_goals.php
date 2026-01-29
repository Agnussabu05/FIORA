<?php
require_once 'includes/db.php';

$stmt = $pdo->prepare("SELECT g.*, u.username FROM goals g JOIN users u ON g.user_id = u.id WHERE u.username = ? ORDER BY g.created_at DESC LIMIT 5");
$stmt->execute(['agnussabu2028']);
$goals = $stmt->fetchAll();

echo "Goals for user 'agnussabu2028':\n";
echo "==============================\n\n";

foreach ($goals as $g) {
    echo "ID: {$g['id']}\n";
    echo "Title: {$g['title']}\n";
    echo "Description: {$g['description']}\n";
    echo "Target Date: {$g['target_date']}\n";
    echo "Category: {$g['category']}\n";
    echo "Status: {$g['status']}\n";
    echo "---\n\n";
}

echo "Total goals found: " . count($goals) . "\n";
?>
