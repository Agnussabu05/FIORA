<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['username']) && !isset($_GET['email'])) {
    echo json_encode(['error' => 'No input provided']);
    exit;
}

$response = ['available' => true];

if (isset($_GET['username'])) {
    $username = trim($_GET['username']);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $response['available'] = false;
        $response['message'] = 'Username is already taken';
    }
} elseif (isset($_GET['email'])) {
    $email = trim($_GET['email']);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $response['available'] = false;
        $response['message'] = 'Email is already registered';
    }
}

echo json_encode($response);
