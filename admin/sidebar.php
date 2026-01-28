<aside class="sidebar" style="background: rgba(255, 255, 255, 0.65); backdrop-filter: blur(20px); border-right: 1px solid rgba(255, 255, 255, 0.6);">
    <div class="brand" style="margin-bottom: 40px;">
        <span style="font-size: 28px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">🛡️</span> 
        <span style="font-weight: 800; color: #1e293b; letter-spacing: -0.5px;">ADMIN</span>
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
                    <span style="background: #ef4444; color: white; border-radius: 50%; padding: 2px 7px; font-size: 0.7rem; font-weight: 800; margin-left: auto; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);">
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
            <a href="../logout.php" class="nav-link" style="color: #ef4444;">
                <span>🚪</span> Logout
            </a>
        </li>
    </ul>
    <div class="user-profile glass-card" style="padding: 15px; margin-top: auto; background: rgba(255, 255, 255, 0.5); border: 1px solid rgba(255, 255, 255, 0.6);">
        <div class="info" style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 32px; height: 32px; background: #6366f1; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: bold;">A</div>
            <div style="font-size: 0.9rem; font-weight: 700; color: #334155;">Administrator</div>
        </div>
    </div>
</aside>
