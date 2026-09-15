<?php
if (!isset($current_page) || empty($current_page) || $current_page === 'index') {
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $foundPage = false;
    foreach ($backtrace as $trace) {
        if (!empty($trace['file'])) {
            $base = basename($trace['file'], '.php');
            if ($base !== 'header' && $base !== 'sidebar' && $base !== 'footer' && $base !== 'index') {
                $current_page = $base;
                $foundPage = true;
                break;
            }
        }
    }
    if (!$foundPage) {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $cleanPath = trim(str_replace('/sms', '', $uriPath), '/');
        if (!empty($cleanPath) && $cleanPath !== 'index.php') {
            $current_page = basename($cleanPath, '.php');
        } else {
            $current_page = 'dashboard';
        }
    }
}
?>
<nav class="sidebar">
    <header>
    <div class="image-text">
        <span class="image">
            <img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png"
                 alt="logo"
                 class="logo-image">
        </span>

        <div class="text header-text">
            <span class="name">Scholarship Management System</span>
        </div>
    </div>
</header>

    <div class="menu-bar">
        <div class="menu">
            <ul class="menu-links">
                <li class="nav-link <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/dashboard">
                        <i data-lucide="layout-dashboard"></i>
                        <span class="text nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'scholars') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/scholars">
                        <i data-lucide="award"></i>
                        <span class="text nav-text">Scholars</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'applicants') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/applicants">
                        <i data-lucide="users"></i>
                        <span class="text nav-text">Applicants</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'evaluation') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/evaluation">
                        <i data-lucide="clipboard-check"></i>
                        <span class="text nav-text">Evaluation</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'records') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/records">
                        <i data-lucide="folder"></i>
                        <span class="text nav-text">Records</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'scholar-map') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/scholar-map">
                        <i data-lucide="map-pin"></i>
                        <span class="text nav-text">Scholar Map</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'scholarships') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/scholarships">
                        <i data-lucide="graduation-cap"></i>
                        <span class="text nav-text">Scholarships</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'renewal-retention') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/renewal-retention">
                        <i data-lucide="bar-chart-3"></i>
                        <span class="text nav-text">Renewal &amp; Retention</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'notification') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/notification">
                        <i data-lucide="bell"></i>
                        <span class="text nav-text">Notifications</span>
                    </a>
                </li>

                <li class="nav-link <?= ($current_page === 'data-management') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/data-management">
                        <i data-lucide="database"></i>
                        <span class="text nav-text">Data Management</span>
                    </a>
                </li>

                <?php
                require_once __DIR__ . '/../config/db_helper.php';
                require_once __DIR__ . '/settings_helper.php';
                $historyMenuEnabled = getSetting(getDB(), 'history_enabled', '1') === '1';
                ?>
                <?php if ($historyMenuEnabled): ?>
                <li class="nav-link <?= ($current_page === 'history') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/history">
                        <i data-lucide="clock"></i>
                        <span class="text nav-text">History</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-link <?= ($current_page === 'trash-bin') ? 'active' : '' ?>">
                    <a href="<?= SITE_BASE ?>/trash-bin">
                        <i data-lucide="rotate-ccw"></i>
                        <span class="text nav-text">Trash Bin / Revert</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>