<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$page = 'music';
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$success = '';

// Handle New Personal Music
if (isset($_POST['add_personal'])) {
    $title = $_POST['title'];
    $artist = $_POST['artist'];
    $link = $_POST['link'];
    $playlist = $_POST['playlist_name'];
    
    $stmt = $pdo->prepare("INSERT INTO music (user_id, title, artist, link, playlist_name, is_system) VALUES (?, ?, ?, ?, ?, 0)");
    $stmt->execute([$user_id, $title, $artist, $link, $playlist]);
    $success = "Personal track added!";
}

// Handle Delete (Only Personal)
if (isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM music WHERE id = ? AND user_id = ? AND is_system = 0");
    $stmt->execute([$id, $user_id]);
}

// Fetch System Music
$system_music = $pdo->query("SELECT * FROM music WHERE is_system = 1 ORDER BY created_at DESC")->fetchAll();

// Fetch Personal Music
$stmt = $pdo->prepare("SELECT * FROM music WHERE user_id = ? AND is_system = 0 ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$personal_music = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Focus Music - Fiora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .music-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .track-card {
            background: var(--glass-bg);
            padding: 18px;
            border-radius: 18px;
            border: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s;
        }
        .track-card:hover { transform: translateY(-3px); }
        .tag {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .tag-system { background: var(--secondary); color: white; }
        .tag-personal { background: var(--success); color: white; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Focus Music 🎵</h1>
                    <p style="color: var(--text-muted);">Curated by Fiora and your own collection.</p>
                </div>
                <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ Add Track</button>
            </header>

            <div class="content-wrapper">
                <!-- System Tracks -->
                <h3 style="margin-bottom: 20px;">System Playlists ✨</h3>
                <div class="music-grid" style="margin-bottom: 40px;">
                    <?php if(empty($system_music)): ?>
                        <div class="glass-card" style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">
                            No system tracks available yet.
                        </div>
                    <?php else: ?>
                        <?php foreach($system_music as $m): ?>
                        <div class="track-card">
                            <div>
                                <span class="tag tag-system">System</span>
                                <div style="font-weight: 600; margin-top: 8px;"><?php echo htmlspecialchars($m['title']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($m['artist']); ?></div>
                            </div>
                            <a href="<?php echo htmlspecialchars($m['link']); ?>" target="_blank" style="font-size: 1.5rem; text-decoration: none;">▶️</a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Personal Tracks -->
                <h3 style="margin-bottom: 20px;">My Collection ❤️</h3>
                <div class="music-grid">
                    <?php if(empty($personal_music)): ?>
                        <div class="glass-card" style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 50px;">
                            Your collection is empty. Add your favorite focus tracks!
                        </div>
                    <?php else: ?>
                        <?php foreach($personal_music as $m): ?>
                        <div class="track-card">
                            <div>
                                <span class="tag tag-personal">Personal</span>
                                <div style="font-weight: 600; margin-top: 8px;"><?php echo htmlspecialchars($m['title']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($m['artist']); ?></div>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <a href="<?php echo htmlspecialchars($m['link']); ?>" target="_blank" style="font-size: 1.5rem; text-decoration: none;">▶️</a>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete track?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" style="background:none; border:none; cursor:pointer; font-size: 1.2rem;">🗑️</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Simple Modal for Adding Track -->
    <div id="addModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
        <div class="glass-card" style="width: 400px; padding: 30px;">
            <h3>Add Focus Track</h3>
            <form method="POST" style="margin-top: 20px;">
                <input type="text" name="title" class="form-input" style="width: 100%; margin-bottom: 15px;" placeholder="Track Title" required>
                <input type="text" name="artist" class="form-input" style="width: 100%; margin-bottom: 15px;" placeholder="Artist / Genre" required>
                <input type="url" name="link" class="form-input" style="width: 100%; margin-bottom: 15px;" placeholder="Streaming Link (YouTube/Spotify)" required>
                <input type="text" name="playlist_name" class="form-input" style="width: 100%; margin-bottom: 20px;" placeholder="Playlist Name (e.g. My Focus)">
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="add_personal" class="btn btn-primary" style="flex: 1;">Add Track</button>
                    <button type="button" class="btn btn-secondary" style="flex: 1; color: #000 !important; font-weight: 800;" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
