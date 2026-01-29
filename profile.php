<?php
// session_start(); // Handling by auth.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch current user details
$stmt = $pdo->prepare("SELECT full_name, username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name) || empty($new_username) || empty($new_email)) {
        $error = "Name, Username, and Email are required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check availability if username/email changed
        if ($new_username !== $user['username'] || $new_email !== $user['email']) {
            $check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check->execute([$new_username, $new_email, $user_id]);
            if ($check->fetch()) {
                $error = "Username or Email already taken by another account.";
            }
        }
    }

    // Password Update Logic
    $password_update = false;
    $password_hash = null;
    if (!$error && !empty($new_password)) {
        if (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $password_update = true;
        }
    }

    // Process Update
    if (!$error) {
        try {
            if ($password_update) {
                $upd = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, password_hash = ? WHERE id = ?");
                $upd->execute([$full_name, $new_username, $new_email, $password_hash, $user_id]);
            } else {
                $upd = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ? WHERE id = ?");
                $upd->execute([$full_name, $new_username, $new_email, $user_id]);
            }

            // Update Session
            $_SESSION['username'] = $new_username;
            
            // Refresh User Data and UI Variable
            $user['full_name'] = $full_name;
            $user['username'] = $new_username;
            $user['email'] = $new_email;
            
            // Critical: Update the variable used by sidebar.php
            $username = $new_username; 

            $success = "Profile updated successfully!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$page = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Edit Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="glass-card" style="max-width: 600px; margin: 0 auto; padding: 40px;">
                <h1 style="margin-bottom: 20px;">Edit Profile</h1>
                
                <?php if ($success): ?>
                    <div style="background: rgba(16, 185, 129, 0.2); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div style="background: rgba(239, 68, 68, 0.2); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Full Name</label>
                        <input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Username</label>
                        <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Email</label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div style="margin-top: 30px; margin-bottom: 20px; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 20px;">
                        <h3 style="margin-bottom: 15px;">Change Password <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 400;">(Optional)</span></h3>
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">New Password</label>
                            <input type="password" name="new_password" class="form-input" placeholder="Leave blank to keep current">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Save Changes</button>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="index.php" style="color: var(--text-muted); text-decoration: none;">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
