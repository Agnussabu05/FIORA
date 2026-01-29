<?php
require_once 'includes/db.php';

function describeTable($pdo, $table) {
    echo "Table: $table\n";
    $stmt = $pdo->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "\n";
}

try {
    describeTable($pdo, 'study_groups');
    describeTable($pdo, 'study_group_members');
    describeTable($pdo, 'study_requests');
    
    // Check distinct statuses
    echo "Distinct statuses in study_groups:\n";
    $stmt = $pdo->query("SELECT DISTINCT status FROM study_groups");
    while ($row = $stmt->fetch()) {
        echo "  " . $row['status'] . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
