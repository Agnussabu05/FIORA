<aside class="sidebar">
    <div class="brand">
        <span style="font-size: 24px;">🛡️</span> ADMIN
    </div>
    <ul class="nav-links">
        <li class="nav-item">
            <a href="../index.php" class="nav-link">
                <span>🔙</span> Back to App
            </a>
        </li>
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                <span>📊</span> Overview
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?php echo ($page == 'users') ? 'active' : ''; ?>">
                <span>👥</span> Users
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
            <a href="ai.php" class="nav-link <?php echo ($page == 'ai') ? 'active' : ''; ?>">
                <span>🤖</span> AI Assistant
            </a>
        </li>
        <li class="nav-item">
            <a href="reports.php" class="nav-link <?php echo ($page == 'reports') ? 'active' : ''; ?>">
                <span>📈</span> Reports
            </a>
        </li>
        <li class="nav-item">
            <a href="cms.php" class="nav-link <?php echo ($page == 'cms') ? 'active' : ''; ?>">
                <span>📝</span> CMS (Content)
            </a>
        </li>
        <li class="nav-item">
            <a href="notifications.php" class="nav-link <?php echo ($page == 'notifications') ? 'active' : ''; ?>">
                <span>🔔</span> Notifications
            </a>
        </li>
        <li class="nav-item">
            <a href="music.php" class="nav-link <?php echo ($page == 'music') ? 'active' : ''; ?>">
                <span>🎵</span> System Music
            </a>
        </li>
        <li class="nav-item">
            <a href="security.php" class="nav-link <?php echo ($page == 'security') ? 'active' : ''; ?>">
                <span>🔐</span> Security
            </a>
        </li>
        <li class="nav-item">
            <a href="settings.php" class="nav-link <?php echo ($page == 'settings') ? 'active' : ''; ?>">
                <span>⚙️</span> Settings
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
