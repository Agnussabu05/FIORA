<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = '';
$error = '';

// Handle New System Music
if (isset($_POST['add_music'])) {
    $title = $_POST['title'];
    $artist = $_POST['artist'];
    $link = $_POST['link'];
    $playlist = $_POST['playlist_name'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO music (user_id, title, artist, link, playlist_name, is_system) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$_SESSION['user_id'], $title, $artist, $link, $playlist]);
        $success = "System music added successfully!";
        
        // Log Activity
        $stmt = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'add_system_music', "Added: $title by $artist"]);
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM music WHERE id = ? AND is_system = 1");
    $stmt->execute([$id]);
}

// Fetch System Music
$system_music = $pdo->query("SELECT * FROM music WHERE is_system = 1 ORDER BY created_at DESC")->fetchAll();

$page = 'music';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Music - Fiora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .music-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-top: 20px; }
        .music-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .delete-btn {
            background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="welcome-section">
                    <h1>System Music Management</h1>
                    <p>Curate playlists that all users can see and use for focus.</p>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if($success): ?>
                    <div style="background: rgba(16, 185, 129, 0.1); color: #10B981; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <div class="music-grid">
                    <div class="glass-card">
                        <h3>Add System Track</h3>
                        <form method="POST" style="margin-top: 20px;">
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Track Title</label>
                                <input type="text" name="title" class="form-input" style="width: 100%;" placeholder="e.g. Deep Focus Beats" required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Artist / Genre</label>
                                <input type="text" name="artist" class="form-input" style="width: 100%;" placeholder="e.g. Lo-fi Girl" required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Spotify/YouTube Link</label>
                                <input type="url" name="link" class="form-input" style="width: 100%;" placeholder="https://..." required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px;">Playlist Category</label>
                                <select name="playlist_name" class="form-input" style="width: 100%;">
                                    <option value="Lo-Fi Beats">Lo-Fi Beats</option>
                                    <option value="Nature Sounds">Nature Sounds</option>
                                    <option value="Ambient Noise">Ambient Noise</option>
                                    <option value="Classical">Classical</option>
                                </select>
                            </div>
                            <button type="submit" name="add_music" class="btn btn-primary" style="width: 100%;">💾 Save to System</button>
                        </form>
                    </div>

                    <div class="glass-card">
                        <h3>Current System Library</h3>
                        <div style="margin-top: 20px;">
                            <?php if(empty($system_music)): ?>
                                <p style="text-align: center; color: var(--text-muted); padding: 40px;">No system tracks added yet.</p>
                            <?php else: ?>
                                <?php foreach ($system_music as $m): ?>
                                <div class="music-card">
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($m['title']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            <?php echo htmlspecialchars($m['artist']); ?> | <?php echo htmlspecialchars($m['playlist_name']); ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 15px; align-items: center;">
                                        <a href="<?php echo htmlspecialchars($m['link']); ?>" target="_blank" style="text-decoration: none; font-size: 1.2rem;">🔗</a>
                                        <form method="POST" onsubmit="return confirm('Remove this track from system library?');" style="margin:0;">
                                            <input type="hidden" name="delete_id" value="<?php echo $m['id']; ?>">
                                            <button type="submit" class="delete-btn">🗑️</button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
