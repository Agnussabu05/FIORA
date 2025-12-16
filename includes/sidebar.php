<aside class="sidebar">
    <div class="brand">
        <span style="font-size: 24px;">✨</span> FIORA
    </div>
    <ul class="nav-links">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                <span>🏠</span> Dashboard
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
