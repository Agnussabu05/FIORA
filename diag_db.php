<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=fiora_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connection successful. Checking columns...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM habits");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);

    $emojiExists = false;
    foreach ($columns as $c) {
        if ($c['Field'] === 'emoji') $emojiExists = true;
    }

    if (!$emojiExists) {
        echo "Attempting to add 'emoji' column...\n";
        $pdo->exec("SET sql_mode = '';");
        $pdo->exec("ALTER TABLE habits ADD emoji VARCHAR(191) NULL");
        echo "Column 'emoji' added successfully.\n";
    } else {
        echo "Column 'emoji' already exists.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
