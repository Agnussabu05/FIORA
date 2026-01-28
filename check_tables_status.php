<?php
require_once 'api/config.php';

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'fiora_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $expectedTables = [
        'users',
        'tasks',
        'habits',
        'habit_logs',
        'mood_logs',
        'study_sessions',
        'study_groups',
        'group_members',
        'books',
        'notifications',
        'expenses',
        'goals',
        'system_logs',
        'book_transactions',
        'book_reservations' // Mentioned in conversation history
    ];

    $output = "Existing Tables & Row Counts:\n";
    foreach ($tables as $table) {
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $countStmt->fetchColumn();
            $output .= "- $table: $count rows\n";
        } catch (Exception $e) {
            $output .= "- $table: ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    $missing = array_diff($expectedTables, $tables);
    if (!empty($missing)) {
        $output .= "\nMISSING TABLES (From schema list):\n";
        foreach ($missing as $m) {
            $output .= "$m\n";
        }
    }
    file_put_contents('table_check_result.txt', $output);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
