<div class="navbar-actions">
    <!-- Notification Bell Button -->
    <a href="<?= SITE_BASE ?>/notification" class="nav-action-btn <?= ($current_page ?? '') === 'notification' ? 'active' : '' ?>" title="Notifications">
        <i data-lucide="bell"></i>
        <span class="nav-btn-badge" id="navNotifBadge">0</span>
    </a>

    <!-- User Profile Dropdown -->
    <div class="nav-profile-dropdown-wrapper">
        <button type="button" class="nav-profile-btn" id="profileDropdownBtn" aria-haspopup="true" aria-expanded="false">
            <div class="nav-profile-avatar">
                <i data-lucide="user"></i>
            </div>
            <span class="nav-profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Registrar Staff') ?></span>
            <i data-lucide="chevron-down" class="nav-profile-chevron"></i>
        </button>
        <div class="nav-profile-menu" id="profileDropdownMenu">
            <div class="nav-profile-header">
                <p class="user-role-title">Signed in as</p>
                <p class="user-role-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Registrar Staff') ?></p>
            </div>
            <div class="nav-profile-divider"></div>
            <a href="<?= SITE_BASE ?>/settings" class="nav-profile-item">
                <i data-lucide="settings"></i>
                <span>Settings</span>
            </a>
            <a href="<?= SITE_BASE ?>/logout" class="nav-profile-item logout-item">
                <i data-lucide="log-out"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>
</div>