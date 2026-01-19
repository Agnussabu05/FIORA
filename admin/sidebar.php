<aside class="sidebar">
    <div class="brand">
        <span style="font-size: 24px;">🛡️</span> ADMIN
    </div>
    <ul class="nav-links">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo ($page == 'dashboard' && ($tab ?? '') !== 'study') ? 'active' : ''; ?>">
                <span>📊</span> Overview
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?php echo ($page == 'users') ? 'active' : ''; ?>">
                <span>👥</span> Users
            </a>
        </li>
        <li class="nav-item">
            <a href="user_activity.php" class="nav-link <?php echo ($page == 'user_activity') ? 'active' : ''; ?>">
                <span>⚡</span> User Activity
            </a>
        </li>
        <li class="nav-item">
            <a href="modules.php" class="nav-link <?php echo ($page == 'modules') ? 'active' : ''; ?>">
                <span>🛠️</span> Modules
            </a>
        </li>
        <li class="nav-item">
            <a href="defaults.php" class="nav-link <?php echo ($page == 'defaults') ? 'active' : ''; ?>">
                <span>📜</span> Global Defaults
            </a>
        </li>
        <li class="nav-item">
            <?php
                // Fetch pending groups count for notification badge
                require_once '../includes/db.php';
                $pending_count = $pdo->query("SELECT COUNT(*) FROM study_groups WHERE status = 'pending_verification'")->fetchColumn();
            ?>
            <a href="index.php?tab=study" class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'study') ? 'active' : ''; ?>">
                <span>🎓</span> Study Groups
                <?php if ($pending_count > 0): ?>
                    <span style="background: #ef4444; color: white; border-radius: 50%; padding: 2px 7px; font-size: 0.7rem; font-weight: 800; margin-left: auto;">
                        <?php echo $pending_count; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="logs.php" class="nav-link <?php echo ($page == 'logs') ? 'active' : ''; ?>">
                <span>📜</span> Activity Logs
            </a>
        </li>

        <li class="nav-item" style="margin-top: 20px;">
            <a href="../logout.php" class="nav-link" style="color: var(--danger);">
                <span>🚪</span> Logout
            </a>
        </li>
    </ul>
    <div class="user-profile glass-card" style="padding: 10px; margin-top: auto;">
        <div class="info">
            <div style="font-size: 0.9rem; font-weight: bold;">Administrator</div>
        </div>
    </div>
</aside>
