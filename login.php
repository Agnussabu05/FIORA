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
        
        body {
            /* Task Management / Productivity Theme Background */
            background: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.3)),
                        url('https://images.unsplash.com/photo-1484480974693-6ca0a78fb36b?q=80&w=2572&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }

        .brand-watermark {
            position: absolute;
            top: 40px;
            left: 50px;
            color: white;
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            opacity: 0.9;
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .auth-card {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                0 2px 4px -1px rgba(0, 0, 0, 0.06),
                0 20px 25px -5px rgba(0, 0, 0, 0.1),
                inset 0 0 0 1px rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .auth-card:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 20px 25px -5px rgba(0, 0, 0, 0.1), 
                0 10px 10px -5px rgba(0, 0, 0, 0.04),
                0 0 0 1px rgba(255,255,255,0.3);
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
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--primary);
            transition: all 0.2s ease;
            box-sizing: border-box; /* Fix padding causing width overflow */
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
        
        /* Subtle Shine Effect */
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
            pointer-events: none;
        }
        .auth-card:hover::before {
            left: 100%;
        }

        /* Loading Overlay */
        #auth-loading {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
            gap: 15px;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(79, 70, 229, 0.1);
            border-left-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Custom Google Button Style */
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            max-width: 340px;
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
    </style>
</head>
<body>
    <div class="brand-watermark">FIORA</div>
    
    <div class="auth-container">
        <div class="auth-card">
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
