<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

$name = trim($_GET['name'] ?? '');

if ($name === '') {
    echo json_encode(['taken' => false]);
    exit;
}

// Check if name exists (case insensitive usually depends on collation, strict match here)
$stmt = $pdo->prepare("SELECT 1 FROM study_groups WHERE name = ?");
$stmt->execute([$name]);
$taken = (bool) $stmt->fetch();

echo json_encode(['taken' => $taken]);
?>
