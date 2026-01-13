<!DOCTYPE html>
<html>
<head><title>PDO Diagnostic</title></head>
<body>
<h1>Step-by-Step Diagnostic</h1>
<p>1. Static HTML loaded.</p>

<?php
flush(); // Force output
echo "<p>2. PHP Started.</p>";
flush();

if (class_exists('PDO')) {
    echo "<p style='color:green'>3. PDO Class exists.</p>";
} else {
    echo "<p style='color:red'>3. PDO Class MISSING.</p>";
}
flush();

echo "<p>4. Checking Drivers...</p>";
$drivers = PDO::getAvailableDrivers();
echo "<pre>" . print_r($drivers, true) . "</pre>";
flush();

echo "<p>5. Attempting Connection...</p>";
try {
    $dsn = "mysql:host=127.0.0.1;dbname=fiora_db;charset=utf8";
    echo "<p>DSN: $dsn</p>";
    $pdo = new PDO($dsn, 'root', '');
    echo "<p style='color:green'>6. SUCCESS: Connected to DB.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>6. FAILURE: " . $e->getMessage() . "</p>";
}
?>
<p>7. End of Script.</p>
</body>
</html>
