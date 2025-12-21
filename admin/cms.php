<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = '';

// Handle New Content
if (isset($_POST['add_content'])) {
    $type = $_POST['type'];
    $text = $_POST['text'];
    $cat = $_POST['category'];
    
    $stmt = $pdo->prepare("INSERT INTO admin_content (content_type, content_text, category) VALUES (?, ?, ?)");
    $stmt->execute([$type, $text, $cat]);
    $success = "Content added successfully!";
    
    // Log Activity
    $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'add_content', "Added new $type"]);
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM admin_content WHERE id = ?");
    $stmt->execute([$id]);
}

// Fetch Content
$contents = $pdo->query("SELECT * FROM admin_content ORDER BY created_at DESC LIMIT 50")->fetchAll();

$page = 'cms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Management - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .cms-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-top: 20px; }
        .content-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            position: relative;
        }
        .delete-btn {
            position: absolute;
            top: 10px; right: 10px;
            background: none; border: none; color: #ef4444; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>Content Management (CMS)</h1>
                    <p>Manage quotes, tips, and shared productivity content.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="cms-grid">
                    <div class="glass-card">
                        <h3>Add New Content</h3>
                        <form method="POST" style="margin-top: 20px;">
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Type</label>
                                <select name="type" class="form-input" style="width: 100%;">
                                    <option value="quote">Motivational Quote</option>
                                    <option value="tip">Productivity Tip</option>
                                    <option value="suggestion">Habit Suggestion</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Category</label>
                                <input type="text" name="category" class="form-input" placeholder="e.g., Focus, Health" style="width: 100%;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Content Text</label>
                                <textarea name="text" class="form-input" style="width: 100%; height: 100px;" required></textarea>
                            </div>
                            <button type="submit" name="add_content" class="btn btn-primary" style="width: 100%;">Post Content</button>
                        </form>
                    </div>

                    <div class="glass-card">
                        <h3>Live Content Feed</h3>
                        <div style="margin-top: 20px;">
                            <?php foreach ($contents as $c): ?>
                            <div class="content-card">
                                <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--accent);">
                                    <?php echo $c['content_type']; ?> (<?php echo htmlspecialchars($c['category'] ?: 'General'); ?>)
                                </span>
                                <p style="margin: 8px 0 0 0;"><?php echo htmlspecialchars($c['content_text']); ?></p>
                                <form method="POST" onsubmit="return confirm('Delete this?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="delete-btn">🗑️</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
