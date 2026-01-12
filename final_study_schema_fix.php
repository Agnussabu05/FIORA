<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

try {
    echo "<h1>Study Collective Schema Fix 🛠️</h1>";
    
    // Check and add group_name
    echo "Checking 'group_name' in 'study_requests'... ";
    $columns = $pdo->query("SHOW COLUMNS FROM study_requests LIKE 'group_name'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE study_requests ADD COLUMN group_name VARCHAR(100) DEFAULT NULL");
        echo "<span style='color:green;'>Added.</span><br>";
    } else {
        echo "<span style='color:orange;'>Already exists.</span><br>";
    }

    // Check and add subject_name
    echo "Checking 'subject_name' in 'study_requests'... ";
    $columns = $pdo->query("SHOW COLUMNS FROM study_requests LIKE 'subject_name'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE study_requests ADD COLUMN subject_name VARCHAR(100) DEFAULT NULL");
        echo "<span style='color:green;'>Added.</span><br>";
    } else {
        echo "<span style='color:orange;'>Already exists.</span><br>";
    }

    echo "<h2 style='color:green;'>✅ Schema Fixed!</h2>";
    echo "<p><a href='study.php'>Return to Study Planner</a></p>";

} catch (Exception $e) {
    echo "<br><br><div style='color:red;'>";
    echo "<b>ERROR:</b> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
