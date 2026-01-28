<?php
session_start();
require_once 'includes/db.php';

$message = '';
$error = '';
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_id = null;

if (empty($token)) {
    $error = "Token missing from link. Please use the full link in your email.";
} else {
    // Validate token
    $stmt = $pdo->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $expiry = strtotime($user['reset_expires']);
        if ($expiry > time()) {
            $valid_token = true;
            $user_id = $user['id'];
        } else {
            $error = "This reset link has expired (valid for 1 hour). Please request a new one.";
        }
    } else {
        $error = "Token not found or already used. Please request a new reset link.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Update password and clear token
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        
        if ($update->execute([$hash, $user_id])) {
            $message = "Password reset successfully! You can now <a href='login.php' style='color: var(--accent); font-weight: bold;'>Log In</a>.";
            $valid_token = false; // Hide form
        } else {
            $error = "Database update failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary: #111827;
            --accent: #4F46E5;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.5);
            --text-main: #1F2937;
            --text-muted: #6B7280;
        }
        
        body {
            background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .auth-card {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .message {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #10B981;
        }

        .error {
            background: rgba(220, 38, 38, 0.1);
            color: #DC2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #DC2626;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Set New Password</h2>
                <p>Choose a strong password for your account.</p>
            </div>

            <?php if($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <div id="js-error" class="error" style="display: none;"></div>

            <?php if($valid_token): ?>
                <form method="POST">
                    <input type="password" name="password" class="form-input" placeholder="New Password" required minlength="6">
                    <input type="password" name="confirm_password" class="form-input" placeholder="Confirm New Password" required minlength="6">
                    <button type="submit" class="btn-primary">Update Password</button>
                </form>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 24px;">
                <a href="login.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">Back to Login</a>
            </div>
        </div>
    </div>
    <script>
        const form = document.querySelector('form');
        if (form) {
            const passInput = form.querySelector('input[name="password"]');
            const confirmInput = form.querySelector('input[name="confirm_password"]');
            const error = document.getElementById('js-error');

            function validatePassword() {
                if (confirmInput.value && passInput.value !== confirmInput.value) {
                     error.textContent = "Passwords do not match.";
                     error.style.display = 'block';
                } else {
                     error.style.display = 'none';
                }
            }

            passInput.addEventListener('input', validatePassword);
            confirmInput.addEventListener('input', validatePassword);

            form.addEventListener('submit', function(e) {
                if (passInput.value !== confirmInput.value) {
                    e.preventDefault();
                    validatePassword();
                }
            });
        }
    </script>
</body>
</html>
