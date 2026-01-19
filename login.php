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
        // Handle input as potential email or username
        $lower_username = strtolower($username);
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? OR LOWER(email) = ?");
        $stmt->execute([$username, $lower_username]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                header("Location: index.php");
                exit;
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "No account found with that username or email.";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        let tokenClient;

        function handleCredentialResponse(user) {
            // Send the user info to google_auth.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'google_auth.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'access_token';
            input.value = user.access_token;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        window.onload = function() {
            tokenClient = google.accounts.oauth2.initTokenClient({
                client_id: '977190374098-u3n0kph99dbrkkm8a75hgpsbielphi0c.apps.googleusercontent.com',
                scope: 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
                callback: handleCredentialResponse,
            });
        }

        function triggerGoogleLogin() {
            tokenClient.requestAccessToken();
        }
    </script>
    <style>
        :root {
            --primary: #111827;
            --accent: #4F46E5;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.5);
            --text-main: #1F2937;
            --text-muted: #6B7280;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%); /* Fresh Blue Gradient */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 1000px;
            max-width: 95%;
            height: 650px;
            background: #fff;
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
        }

        /* Left Side - Image */
        .left-panel {
            flex: 1;
            background: url('assets/images/login_illustration.png');
            background-color: #eef2ff; /* Fallback/Blend color matching the illustration */
            background-size: cover;
            background-position: center;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            color: var(--primary);
        }

        .left-panel::after {
            display: none;
        }

        .panel-content {
            position: relative;
            z-index: 2;
        }

        .panel-content h1 {
            font-size: 3.5rem;
            line-height: 1.1;
            font-weight: 600;
            margin-bottom: 20px;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .panel-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 500;
            max-width: 80%;
            color: var(--text-muted);
        }

        /* Right Side - Form */
        .right-panel {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            background: white;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: var(--primary);
            letter-spacing: -0.02em;
        }

        .auth-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: #FAFAFA;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--primary);
            transition: all 0.2s ease;
            box-sizing: border-box;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            background: #ffffff;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        
        .form-input::placeholder {
            color: #9CA3AF;
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            background: #000;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }

        .auth-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .auth-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .auth-link:hover {
            text-decoration: underline;
        }

        .error-message {
            background: rgba(220, 38, 38, 0.1);
            color: #DC2626;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            border-left: 3px solid #DC2626;
        }

        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 12px;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 12px;
            color: #3c4043;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(60,64,67,0.3);
            margin: 0 auto;
        }
        .btn-google:hover {
            background: #f8f9fa;
            border-color: #d2e3fc;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,.30), 0 1px 3px 1px rgba(60,64,67,.15);
        }
        .btn-google img {
            width: 20px;
            height: 20px;
        }

        @media (max-width: 900px) {
            .login-wrapper {
                flex-direction: column;
                height: auto;
                width: 100%;
                border-radius: 0;
            }
            .left-panel {
                padding: 60px 40px;
                min-height: 250px;
            }
            .left-panel h1 {
                font-size: 2.5rem;
            }
            body {
                align-items: flex-start;
                background: white;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="left-panel">
            <div class="panel-content">
                <!-- Text removed as requested -->
            </div>
        </div>
        
        <div class="right-panel">
            <div class="auth-header">
                <h2>Welcome Back</h2>
                <p>Focus on what matters most.</p>
            </div>

            <?php if($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" name="username" class="form-input" placeholder="Username or Email" required autocomplete="username">
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <input type="password" name="password" class="form-input" placeholder="Password" required autocomplete="current-password">
                    <div style="text-align: right; margin-top: 8px;">
                        <a href="forgot_password.php" class="auth-link" style="font-size: 0.85rem; font-weight: 500; opacity: 0.8;">Forgot Password?</a>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Sign In</button>
            </form>

            <div style="margin: 20px 0; display: flex; align-items: center; text-align: center; color: var(--text-muted);">
                <hr style="flex: 1; border: none; border-top: 1px solid rgba(0,0,0,0.1);">
                <span style="padding: 0 10px; font-size: 0.8rem;">OR</span>
                <hr style="flex: 1; border: none; border-top: 1px solid rgba(0,0,0,0.1);">
            </div>

            <div style="display: flex; justify-content: center;">
                <button type="button" class="btn-google" onclick="triggerGoogleLogin()">
                    <img src="https://www.gstatic.com/images/branding/product/1x/gsa_512dp.png" alt="Google Logo">
                    Sign in with Google
                </button>
            </div>

            <div class="auth-footer">
                Don't have an account? <a href="register.php" class="auth-link">Create Account</a>
            </div>
        </div>
    </div>
</body>
</html>
