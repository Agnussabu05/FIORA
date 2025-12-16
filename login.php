<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Login</title>
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
        <h2 style="margin-bottom: 10px;">Welcome Back</h2>
        <p style="color: var(--text-muted); margin-bottom: 30px;">Login to continue your journey.</p>

        <?php if($error): ?>
            <div style="background: rgba(214,48,49,0.2); color: #ff7675; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="text" name="username" class="form-input" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" class="form-input" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>

        <a href="register.php" class="auth-link">Don't have an account? Sign up</a>
    </div>
</body>
</html>
