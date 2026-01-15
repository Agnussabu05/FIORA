<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_book') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category = $_POST['category'];
    $condition = $_POST['condition'];
    $type = $_POST['type'];
    $price = ($type === 'Borrow') ? 0.00 : (float)$_POST['price'];
    
    // Image Upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/books/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'book_' . time() . '_' . uniqid() . '.' . $ext;
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = 'uploads/books/' . $filename; // Store relative path for DB
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO books (user_id, title, author, category, condition_state, type, price, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $author, $category, $condition, $type, $price, $imagePath]);
        
        header("Location: ../books.php?success=1");
        exit;
    } catch (PDOException $e) {
        die("Error adding book: " . $e->getMessage());
    }
}

// Future: Handle 'buy_book' action here
header("Location: ../books.php");
?>
