<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            if ($stmt->execute([$username, $hash])) {
                $success = "Account created! You can now login.";
            } else {
                $error = "Something went wrong.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex; align-items: center; justify-content: center;
        }
        .auth-card {
            width: 400px;
            padding: 40px;
            text-align: center;
        }
        .brand-logo {
            font-size: 3rem; margin-bottom: 20px;
        }
        .auth-link {
            margin-top: 20px; display: block; color: var(--text-muted); text-decoration: none;
        }
        .auth-link:hover { color: var(--primary); }
    </style>
</head>
<body>
    <div class="glass-card auth-card">
        <div class="brand-logo">✨</div>
        <h2 style="margin-bottom: 10px;">Join Fiora</h2>
        <p style="color: var(--text-muted); margin-bottom: 30px;">Start organizing your life today.</p>

        <?php if($error): ?>
            <div style="background: rgba(214,48,49,0.2); color: #ff7675; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div style="background: rgba(0, 184, 148, 0.2); color: #55efc4; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $success; ?> <a href="login.php" style="color: white; font-weight: bold;">Login</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="text" name="username" class="form-input" placeholder="Choose Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" class="form-input" placeholder="Choose Password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign Up</button>
        </form>

        <a href="login.php" class="auth-link">Already use Fiora? Login</a>
    </div>
</body>
</html>
