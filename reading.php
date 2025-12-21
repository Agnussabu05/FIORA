<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$page = 'reading';
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading List - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Reading List 📖</h1>
                    <p style="color: var(--text-muted);">Expand your horizons, one page at a time.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="glass-card" style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📚</div>
                    <h2 style="margin-bottom: 10px;">Your Library is Growing</h2>
                    <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 30px;">
                        The Reading module helps you track books you've read, items on your wishlist, and your daily reading streaks.
                    </p>
                    <button class="btn btn-primary">+ Add First Book</button>
                    
                    <div style="margin-top: 50px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div class="glass-card" style="background: rgba(255,255,255,0.1); border: 1px dashed rgba(0,0,0,0.1);">
                            <h4 style="margin: 0; color: var(--text-muted);">Books Read</h4>
                            <div style="font-size: 2rem; font-weight: 700; margin-top: 10px;">0</div>
                        </div>
                        <div class="glass-card" style="background: rgba(255,255,255,0.1); border: 1px dashed rgba(0,0,0,0.1);">
                            <h4 style="margin: 0; color: var(--text-muted);">Target 2024</h4>
                            <div style="font-size: 2rem; font-weight: 700; margin-top: 10px;">12</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
