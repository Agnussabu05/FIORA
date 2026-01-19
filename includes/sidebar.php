<?php
// Fetch Unread Notification Count
$notif_count = 0;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $nStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $nStmt->execute([$_SESSION['user_id']]);
    $notif_count = $nStmt->fetchColumn();
}
?>
<aside class="sidebar">
    <div class="brand">
        <span style="font-size: 24px;">✨</span> FIORA
    </div>
    <ul class="nav-links">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>" style="justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;"><span>🏠</span> Dashboard</div>
                <?php if($notif_count > 0): ?>
                    <span style="background: var(--danger); color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px;"><?php echo $notif_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="tasks.php" class="nav-link <?php echo ($page == 'tasks') ? 'active' : ''; ?>">
                <span>✅</span> Tasks
            </a>
        </li>
        <li class="nav-item">
            <a href="habits.php" class="nav-link <?php echo ($page == 'habits') ? 'active' : ''; ?>">
                <span>🔥</span> Habits
            </a>
        </li>
        <li class="nav-item">
            <a href="finance.php" class="nav-link <?php echo ($page == 'finance') ? 'active' : ''; ?>">
                <span>💰</span> Finance
            </a>
        </li>
        <li class="nav-item">
            <a href="study.php" class="nav-link <?php echo ($page == 'study') ? 'active' : ''; ?>">
                <span>📚</span> Study
            </a>
        </li>
        
        <li class="nav-item">
            <a href="reading.php" class="nav-link <?php echo ($page == 'reading') ? 'active' : ''; ?>">
                <span>📖</span> Reading
            </a>
        </li>
        <li class="nav-item">
            <a href="mood.php" class="nav-link <?php echo ($page == 'mood') ? 'active' : ''; ?>">
                <span>😊</span> Mood
            </a>
        </li>
        <li class="nav-item">
            <a href="goals.php" class="nav-link <?php echo ($page == 'goals') ? 'active' : ''; ?>">
                <span>🎯</span> Goals
            </a>
        </li>
        <li class="nav-item">
            <a href="music.php" class="nav-link <?php echo ($page == 'music') ? 'active' : ''; ?>">
                <span>🎵</span> Music
            </a>
        </li>

        
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <li class="nav-item">
            <a href="admin/index.php" class="nav-link" style="color: var(--accent);">
                <span>🛡️</span> Admin Panel
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item" style="margin-top: 20px;">
            <a href="logout.php" class="nav-link" style="color: var(--danger);">
                <span>🚪</span> Logout
            </a>
        </li>
    </ul>
    <div class="user-profile glass-card" style="padding: 10px; margin-top: auto;">
        <div class="avatar"><?php echo strtoupper(substr($username ?? 'U', 0, 1)); ?></div>
        <div class="info">
            <div style="font-size: 0.9rem; font-weight: bold;"><?php echo htmlspecialchars($username ?? 'User'); ?></div>
            <div style="font-size: 0.7rem; color: var(--text-muted);">Pro Member</div>
        </div>
    </div>
</aside>
