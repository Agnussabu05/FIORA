<?php
require_once 'includes/db.php';
// Mock User ID for CLI
$user_id = 1; 

echo "<h1>Debug Task JSON</h1>";

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ?");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tasks as $task) {
    echo "Task ID: " . $task['id'] . "\n";
    echo "Title: " . $task['title'] . "\n";
    
    $json = json_encode($task);
    if ($json === false) {
        echo "JSON Encode Failed! Error: " . json_last_error_msg() . "\n";
        echo "Raw Title Bytes: " . bin2hex($task['title']) . "\n";
    } else {
        echo "JSON OK\n";
    }
    echo "----------------\n";
}
?>
