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
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or Email already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $full_name, $email, $hash])) {
                $new_user_id = $pdo->lastInsertId();
                
                // Assign Global Defaults
                $defaults = $pdo->query("SELECT * FROM system_defaults")->fetchAll();
                foreach ($defaults as $d) {
                    if ($d['type'] === 'task') {
                        $p_stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, priority, is_admin_pushed) VALUES (?, ?, ?, ?, 1)");
                        $p_stmt->execute([$new_user_id, $d['title'], $d['description'], $d['priority']]);
                    } else {
                        $p_stmt = $pdo->prepare("INSERT INTO habits (user_id, name, is_admin_pushed) VALUES (?, ?, 1)");
                        $p_stmt->execute([$new_user_id, $d['title']]);
                    }
                }
                
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
            <div style="background: rgba(239, 68, 68, 0.2); color: var(--danger); padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div style="background: rgba(16, 185, 129, 0.2); color: var(--success); padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $success; ?> <a href="login.php" style="color: white; font-weight: bold;">Login</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <input type="text" name="full_name" class="form-input" placeholder="Full Name" required>
            </div>
            <div class="form-group">
                <input type="text" name="username" id="username" class="form-input" placeholder="Username" required>
                <div id="username-feedback" class="feedback-msg"></div>
            </div>
            <div class="form-group">
                <input type="email" name="email" id="email" class="form-input" placeholder="Email Address" required>
                <div id="email-feedback" class="feedback-msg"></div>
            </div>
            <div class="form-group" style="display: flex; gap: 10px;">
                <input type="password" name="password" id="password" class="form-input" placeholder="Password" required>
                <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Confirm Password" required>
            </div>
            <div id="password-feedback" class="feedback-msg" style="margin-top: -10px; margin-bottom: 20px;"></div>
            <button type="submit" id="submitBtn" class="btn btn-primary" style="width: 100%;">Sign Up</button>
        </form>

        <a href="login.php" class="auth-link">Already use Fiora? Login</a>
    </div>

    <script>
        const usernameInput = document.getElementById('username');
        const emailInput = document.getElementById('email');
        const usernameFeedback = document.getElementById('username-feedback');
        const emailFeedback = document.getElementById('email-feedback');
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const passwordFeedback = document.getElementById('password-feedback');
        const submitBtn = document.getElementById('submitBtn');

        let usernameValid = false;
        let emailValid = false;
        let passwordValid = false;
        let confirmValid = false;

        async function checkAvailability(type, value, feedbackElement) {
            if (!value) {
                feedbackElement.innerText = '';
                return false;
            }

            feedbackElement.innerText = 'Checking...';
            feedbackElement.style.color = 'var(--text-muted)';

            try {
                const response = await fetch(`api/check_user.php?${type}=${encodeURIComponent(value)}`);
                const data = await response.json();

                if (data.available) {
                    feedbackElement.innerText = ''; // Don't show success message
                    // feedbackElement.style.color = 'var(--success)';
                    return true;
                } else {
                    feedbackElement.innerText = '✗ ' + data.message;
                    feedbackElement.style.color = 'var(--danger)';
                    return false;
                }
            } catch (error) {
                console.error('Error checking availability:', error);
                return true; 
            }
        }

        function validatePasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            // Length Check
            if (password.length < 6) {
                passwordFeedback.innerText = '⚠️ Password must be at least 6 characters';
                passwordFeedback.style.color = 'var(--text-muted)'; // Warning color
                passwordValid = false;
            } else {
                passwordValid = true;
            }

            if (confirm === '') {
                if (passwordValid) passwordFeedback.innerText = ''; 
                confirmValid = false;
                updateSubmitButton();
                return;
            }

            if (password !== confirm) {
                passwordFeedback.innerText = '✗ Passwords do not match';
                passwordFeedback.style.color = 'var(--danger)';
                confirmValid = false;
            } else {
                passwordFeedback.innerText = ''; // Don't show success message, just clear error
                // passwordFeedback.style.color = 'var(--success)';
                confirmValid = true;
            }
            updateSubmitButton();
        }

        let debounceTimer;
        usernameInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                usernameValid = await checkAvailability('username', usernameInput.value, usernameFeedback);
                updateSubmitButton();
            }, 500);
        });

        emailInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const email = emailInput.value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!emailRegex.test(email)) {
                    emailFeedback.innerText = '⚠️ Invalid email format';
                    emailFeedback.style.color = 'var(--text-muted)';
                    emailValid = false;
                    updateSubmitButton();
                    return;
                }

                emailValid = await checkAvailability('email', email, emailFeedback);
                updateSubmitButton();
            }, 500);
        });

        passwordInput.addEventListener('input', validatePasswordMatch);
        confirmInput.addEventListener('input', validatePasswordMatch);

        function updateSubmitButton() {
            if (usernameValid && emailValid && passwordValid && confirmValid) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.innerText = 'Sign Up';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.innerText = 'Please Fix Errors';
            }
        }
        
        // Initial state
        updateSubmitButton();
    </script>
    <style>
        .feedback-msg {
            font-size: 0.75rem;
            text-align: left;
            margin-top: 4px;
            padding-left: 5px;
            height: 1rem;
        }
    </style>
</body>
</html>
