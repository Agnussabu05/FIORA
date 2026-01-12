<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/db.php';

try {
    echo "<h1>Study Groups Schema Update 🛠️</h1>";
    
    // Check and add subject column for study_groups
    echo "Checking 'subject' column in 'study_groups'... ";
    $columns = $pdo->query("SHOW COLUMNS FROM study_groups LIKE 'subject'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE study_groups ADD COLUMN subject VARCHAR(100) DEFAULT NULL AFTER name");
        echo "<span style='color:green;'>Added 'subject' column.</span><br>";
    } else {
        echo "<span style='color:orange;'>Already exists.</span><br>";
    }

    echo "<h2 style='color:green;'>✅ Schema Updated Successfully!</h2>";

} catch (Exception $e) {
    echo "<br><br><div style='color:red;'>";
    echo "<b>ERROR:</b> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
