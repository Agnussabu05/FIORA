<?php
session_start();
require_once 'includes/db.php';

$message = '';
$error = '';
$reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']);

    if (empty($identifier)) {
        $error = "Please enter your username or email.";
    } else {
        // Find user
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store in DB
            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $update->execute([$token, $expires, $user['id']]);

            // Send Real Email using PHPMailer
            require_once 'includes/mail_config.php';
            require_once 'includes/PHPMailer/Exception.php';
            require_once 'includes/PHPMailer/PHPMailer.php';
            require_once 'includes/PHPMailer/SMTP.php';

            // Construct Link - Use APP_URL if defined, otherwise fallback to auto-detection
            if (defined('APP_URL')) {
                $reset_link = rtrim(APP_URL, '/') . "/reset_password.php?token=$token";
            } else {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $base_dir = dirname($_SERVER['PHP_SELF']);
                $reset_link = "$protocol://$host$base_dir/reset_password.php?token=$token";
            }

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;

                // Recipients
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($user['email']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - FIORA';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                        <h2 style='color: #111827; text-align: center;'>Reset Your Password</h2>
                        <p>Hello,</p>
                        <p>You requested a password reset for your FIORA account. Click the button below to set a new password. This link will expire in 1 hour.</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='$reset_link' style='background: #111827; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password</a>
                        </div>
                        <p>If you did not request this, please ignore this email.</p>
                        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 12px; color: #6B7280; text-align: center;'>&copy; " . date('Y') . " FIORA. All rights reserved.</p>
                    </div>";

                $mail->send();
                $message = "A password reset link has been sent to your email address.";
                $reset_link = ''; // Hide link from UI since it's emailed
            } catch (Exception $e) {
                $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            // Security best practice: don't reveal if user exists. 
            // But for a personal app/tutorial, a clear message is better.
            $error = "User not found with that username or email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Fiora</title>
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

        .reset-link-box {
            margin-top: 15px;
            padding: 10px;
            background: #f3f4f6;
            border-radius: 8px;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Forgot Password</h2>
                <p>Enter your email or username to reset.</p>
            </div>

            <?php if($message): ?>
                <div class="message"><?php echo $message; ?></div>
                <?php if($reset_link): ?>
                    <div class="reset-link-box">
                        <a href="<?php echo $reset_link; ?>" style="color: var(--accent);"><?php echo $reset_link; ?></a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="identifier" class="form-input" placeholder="Username or Email" required>
                <button type="submit" class="btn-primary">Generate Reset Link</button>
            </form>

            <div style="text-align: center; margin-top: 24px;">
                <a href="login.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
