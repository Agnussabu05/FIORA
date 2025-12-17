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
    } elseif (!$pdo) {
         $error = "Database unavailable. UI Mode Only.";
         // Logic to allow "demo" login if DB is down? 
         // For now, just show error but keep page visible.
         // Or strictly follow "match image": user just wants to see the page.
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
// Show global DB error if exists
if (isset($db_connection_error)) {
    $error = $db_connection_error;
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
        :root {
            --primary: #222222;
            --bg-dark: #E3DAC9;
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: rgba(255, 255, 255, 0.8);
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        body {
            background-color: var(--bg-dark);
            background: linear-gradient(135deg, #E3DAC9 0%, #D6CDBF 100%);
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--primary);
        }
        .auth-card {
            width: 400px;
            padding: 48px;
            text-align: center;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-lg);
            border-radius: 24px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: transform 0.3s ease;
        }
        .auth-card:hover { transform: translateY(-2px); }
        h2 {
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 32px;
            color: #1a1a1a;
        }
        .form-input {
            width: 100%;
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 12px;
            background: rgba(255,255,255,0.7);
            font-size: 0.95rem;
            outline: none;
            color: #1a1a1a;
            transition: all 0.2s ease;
        }
        .form-input::placeholder { color: #888; font-weight: 500; }
        .form-input:focus {
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(0,0,0,0.05);
            transform: translateY(-1px);
        }
        .btn-primary {
            background: #1a1a1a;
            color: white;
            border: none;
            padding: 16px;
            width: 100%;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 1rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-primary:hover {
            background: #000;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        .btn-primary:active { transform: translateY(0); }
        .auth-link {
            display: block;
            margin-top: 24px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .auth-link:hover { color: #000; }
    </style>
</head>
<body>
    <div style="position: absolute; top: 40px; left: 60px; font-weight: bold; font-size: 2rem; color: #888; text-transform: uppercase;">FIORA</div>
    <div class="glass-card auth-card" style="box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 2px solid white; background: rgba(255,255,255,0.7);">
        <h2 style="margin-bottom: 25px; font-weight: 700; color: var(--primary);">Welcome Back</h2>

        <?php if($error): ?>
            <div style="background: rgba(192, 108, 108, 0.1); color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
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
            <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 8px; font-weight: bold;">login</button>
        </form>

        <a href="register.php" class="auth-link" style="font-size: 0.9rem;">Don't have an account? Signup</a>
    </div>
</body>
</html>
