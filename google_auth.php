<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_token'])) {
    $access_token = $_POST['access_token'];
    
    // Fetch user info from Google's userinfo endpoint
    $url = "https://www.googleapis.com/oauth2/v3/userinfo?access_token=" . $access_token;
    $response = @file_get_contents($url);
    $payload = json_decode($response, true);
    
    if ($payload && isset($payload['sub'])) {
        $google_id = $payload['sub'];
        $email = $payload['email'];
        $full_name = $payload['name'];
        $picture = $payload['picture'] ?? '';
        
        // 1. Check if user already exists with this google_id
        $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$google_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // 2. Check if user already exists with this email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Link Google account to existing user
                $stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                $stmt->execute([$google_id, $user['id']]);
            } else {
                // 3. Create new user
                $username = strtolower(explode('@', $email)[0]);
                // Check if username unique, if not add numbers
                $base_username = $username;
                $counter = 1;
                while (true) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if (!$stmt->fetch()) break;
                    $username = $base_username . $counter++;
                }
                
                // Random password hash for Google users (they won't use it directly)
                $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, google_id, password_hash) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $full_name, $email, $google_id, $dummy_password]);
                
                $new_user_id = $pdo->lastInsertId();
                
                // Assign Global Defaults (same as register.php)
                // Check if system_defaults table exists first to avoid errors
                try {
                    $defaults_stmt = $pdo->query("SHOW TABLES LIKE 'system_defaults'");
                    if ($defaults_stmt->fetch()) {
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
                    }
                } catch (Exception $e) {
                    // Ignore defaults errors
                }
                
                // Fetch the newly created user
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$new_user_id]);
                $user = $stmt->fetch();
            }
        }
        
        // Log in the user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['profile_pic'] = $picture; // Store picture for UI
        
        header("Location: index.php");
        exit;
    } else {
        header("Location: login.php?error=" . urlencode("Google Authentication Failed."));
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
?>
