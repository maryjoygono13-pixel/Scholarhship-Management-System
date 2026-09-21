<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/settings_helper.php';
checkAuth();

// The History menu is a system-wide toggle (Settings > Portal
// Configuration), not a per-user permission — this app has no real
// user/role system to gate on.
if (getSetting(getDB(), 'history_enabled', '1') !== '1') {
    http_response_code(403);
    $current_page = 'history';
    $page_title = 'History';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="page">
        <div class="table-card" style="padding:40px; text-align:center;">
            <h2 style="margin:0 0 8px; color:#134e2a;">History Disabled</h2>
            <p style="color:#6b7280; margin:0;">The History / Audit Trail log is currently turned off. Enable it from Settings &rsaquo; Portal Configuration.</p>
        </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit();
}

$current_page = 'history';
$page_title = "History";
$page_css = "history.css";
$page_js = "history.js";

include __DIR__ . '/../includes/header.php';
?>

<div class="page">

    <!-- Stat Cards -->
    <div class="history-stats">
        <div class="history-stat-card">
            <div class="history-stat-icon total">
                <i data-lucide="activity"></i>
            </div>
            <div>
                <span class="history-stat-label">Total Activity</span>
                <h1 id="statTotal">0</h1>
            </div>
        </div>

        <div class="history-stat-card">
            <div class="history-stat-icon today">
                <i data-lucide="calendar-clock"></i>
            </div>
            <div>
                <span class="history-stat-label">Today's Activity</span>
                <h1 id="statToday">0</h1>
            </div>
        </div>

        <div class="history-stat-card">
            <div class="history-stat-icon user">
                <i data-lucide="user-round"></i>
            </div>
            <div>
                <span class="history-stat-label">Most Active User</span>
                <h1 id="statMostActive" class="history-stat-user">&mdash;</h1>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="table-header-toolbar">
        <div class="toolbar">
            <div class="search-wrap">
                <input id="historySearch" placeholder="Search description, user, action...">
                <i data-lucide="search"></i>
            </div>

            <input type="date" id="historyDateFilter" class="history-date-input">

            <div class="select-wrap">
                <select id="historyActionFilter"><option value="all">Actions</option></select>
                <i data-lucide="chevron-down"></i>
            </div>

            <div class="select-wrap">
                <select id="historyModuleFilter"><option value="all">Modules</option></select>
                <i data-lucide="chevron-down"></i>
            </div>

            <div class="header-actions">
                <button type="button" class="btn-secondary" id="historyClearFiltersBtn">Clear Filters</button>
                <button type="button" class="btn-primary btn-no-anim" id="historyExportBtn">
                    <i data-lucide="download"></i>
                    Export CSV
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <div class="table-card" id="historyTableWrap">
            <!-- Dynamic table rendered by JS -->
        </div>
    </div>

    <!-- Pagination -->
    <div class="history-pagination" id="historyPagination"></div>
</div>

<!-- Detail Modal -->
<div class="custom-modal-overlay" id="historyDetailOverlay">
    <div class="custom-modal-card sm">
        <div class="custom-modal-header">
            <div>
                <h3>Activity Log Details</h3>
                <p>Full record of this system action.</p>
            </div>
            <button type="button" class="custom-modal-close" id="historyDetailCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="custom-modal-body" id="historyDetailBody"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
