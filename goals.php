<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$page = 'goals';
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goals & Ambitions - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Goals & Ambitions 🎯</h1>
                    <p style="color: var(--text-muted);">Turn your dreams into actionable steps.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="glass-card" style="padding: 40px; border-left: 6px solid var(--primary);">
                    <h3>Your Active Goals</h3>
                    <div style="margin-top: 30px; text-align: center; color: var(--text-muted);">
                        <div style="font-size: 3rem; margin-bottom: 15px;">🏁</div>
                        <p>No active goals found. Ready to start something new?</p>
                        <button class="btn btn-primary" style="margin-top: 20px;">+ Create New Goal</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
