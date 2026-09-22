<?php
$current_page = 'dashboard';
$page_title = "Dashboard";
$page_css = "dashboard.css";
$page_js = "dashboard.js";
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/scholarship_distribution.php';
require_once __DIR__ . '/../includes/merit_helper.php';

function timeAgo(string $datetime): string {
    $ts = strtotime($datetime);
    if (!$ts) return $datetime;

    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) { $m = (int)($diff / 60); return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago'; }
    if ($diff < 86400) { $h = (int)($diff / 3600); return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago'; }
    $d = (int)($diff / 86400);
    if ($d < 7) return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    return date('M j, Y', $ts);
}

function activityIcon(string $action): string {
    $a = strtolower($action);
    if (strpos($a, 'rejected') !== false) return 'x-circle';
    if (strpos($a, 'approved') !== false) return 'check-check';
    if (strpos($a, 'deleted') !== false) return 'trash-2';
    if (strpos($a, 'created') !== false || strpos($a, 'added') !== false) return 'user-plus';
    if (strpos($a, 'updated') !== false) return 'pencil';
    if (strpos($a, 'login') !== false) return 'log-in';
    if (strpos($a, 'logout') !== false) return 'log-out';
    if (strpos($a, 'renewal') !== false || strpos($a, 'assignment') !== false) return 'graduation-cap';
    if (strpos($a, 'status') !== false) return 'activity';
    return 'bell';
}

try {
    $pdo = getDB();

    // School year shown in the two charts: the one picked in the dropdown, else the newest year that has data.
    $schoolYears = getDashboardSchoolYears($pdo);
    $selectedYear = trim((string)($_GET['sy'] ?? ''));
    if ($selectedYear !== 'all' && !in_array($selectedYear, $schoolYears, true)) {
        $selectedYear = $schoolYears[0] ?? 'all';
    }
    $yearFilter = $selectedYear === 'all' ? null : $selectedYear;

    // Total applicants, matching the Applicants page: approved/rejected
    // applicants are already decided, so they're excluded here too.
    $total_applicants = (int)$pdo->query("
        SELECT COUNT(*)
        FROM applicants
        WHERE LOWER(status) IN ('pending', 'review', 'interview')
    ")->fetchColumn();

    // Applicants awaiting a decision — pending review or already in the interview stage
    $under_evaluation = (int)$pdo->query("
        SELECT COUNT(*)
        FROM applicants
        WHERE LOWER(status) IN ('pending', 'review', 'interview')
    ")->fetchColumn();

    // Merit-based scholars are added to the roster automatically
    syncMeritScholars($pdo);

    // Active scholars come from the actual scholar roster, not from applicants
    $active_scholars = (int)$pdo->query("
        SELECT COUNT(*)
        FROM scholars
        WHERE LOWER(status) = 'active'
    ")->fetchColumn();

    // Renewal records needing attention
    $renewal_due = (int)$pdo->query("
        SELECT COUNT(*)
        FROM renewal_retention
        WHERE LOWER(status) IN ('pending', 'at-risk')
    ")->fetchColumn();

    // Recent activity, powered by the real audit trail
    $recent_activity = $pdo->query("
        SELECT action, module, description, created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Monthly Applications — real submission counts for the last 6 months,
    // built from applicants.created_at rather than hardcoded sample data.
    $monthly_buckets = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-$i months");
        $monthly_buckets[date('Y-m', $ts)] = ['label' => date('M', $ts), 'count' => 0];
    }

    $monthlyStmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM applicants
        WHERE created_at IS NOT NULL" . ($yearFilter !== null ? " AND TRIM(COALESCE(school_year, '')) = ?" : '') . "
        GROUP BY ym
    ");
    $monthlyStmt->execute($yearFilter !== null ? [$yearFilter] : []);
    foreach ($monthlyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($monthly_buckets[$row['ym']])) {
            $monthly_buckets[$row['ym']]['count'] = (int)$row['cnt'];
        }
    }

    $monthly_labels = array_values(array_map(fn($m) => $m['label'], $monthly_buckets));
    $monthly_counts = array_values(array_map(fn($m) => $m['count'], $monthly_buckets));

    // Scholarship Distribution — approved scholars per scholarship type, live
    // from the records table (see includes/scholarship_distribution.php).
    $distribution = getScholarshipDistribution($pdo, $yearFilter);

} catch (Exception $e) {
    $schoolYears = [];
    $selectedYear = 'all';
    $total_applicants = 0;
    $under_evaluation = 0;
    $active_scholars = 0;
    $renewal_due = 0;
    $recent_activity = [];
    $monthly_labels = [];
    $monthly_counts = [];
    $distribution = ['types' => [], 'totalApproved' => 0, 'totalTypes' => 0, 'mostPopular' => null];
}
?>
<script>
    window.monthlyApplicationsData = {
        labels: <?= json_encode($monthly_labels) ?>,
        counts: <?= json_encode($monthly_counts) ?>
    };
    window.scholarshipDistributionData = <?= json_encode($distribution) ?>;
    window.dashboardSchoolYear = <?= json_encode($selectedYear) ?>;
</script>

<div class="main-content">
    <!-- Stat Cards -->
    <div class="cards">
        <div class="card green">
            <div class="card-header-icon">
                <i data-lucide="user-check"></i>
            </div>
            <div>
                <span class="card-text">Total Applicants</span>
                <h1><?= number_format($total_applicants) ?></h1>
            </div>
        </div>

        <div class="card emerald">
            <div class="card-header-icon">
                <i data-lucide="clock"></i>
            </div>
            <div>
                <span class="card-text">Under Evaluation</span>
                <h1><?= number_format($under_evaluation) ?></h1>
            </div>
        </div>

        <div class="card teal">
            <div class="card-header-icon">
                <i data-lucide="graduation-cap"></i>
            </div>
            <div>
                <span class="card-text">Active Scholars</span>
                <h1><?= number_format($active_scholars) ?></h1>
            </div>
        </div>

        <div class="card amber">
            <div class="card-header-icon">
                <i data-lucide="refresh-cw"></i>
            </div>
            <div>
                <span class="card-text">Renewal Due</span>
                <h1><?= number_format($renewal_due) ?></h1>
            </div>
        </div>
    </div>

    <!-- School year the two charts below show -->
    <div class="chart-year-filter">
        <label for="chartSchoolYear">School Year</label>
        <select id="chartSchoolYear" onchange="window.location.search = '?sy=' + encodeURIComponent(this.value)">
            <?php foreach ($schoolYears as $sy): ?>
                <option value="<?= htmlspecialchars($sy) ?>" <?= $sy === $selectedYear ? 'selected' : '' ?>><?= htmlspecialchars(str_replace('-', ' - ', $sy)) ?></option>
            <?php endforeach; ?>
            <option value="all" <?= $selectedYear === 'all' ? 'selected' : '' ?>>School Years</option>
        </select>
    </div>

    <!-- Charts -->
    <div class="charts-wrapper">
        <div class="dist-summary">
            <div class="dist-stat">
                <span class="dist-stat-label">Total Approved Scholars</span>
                <strong class="dist-stat-value" id="distTotalApproved"><?= number_format($distribution['totalApproved']) ?></strong>
            </div>
            <div class="dist-stat">
                <span class="dist-stat-label">Total Scholarship Types</span>
                <strong class="dist-stat-value" id="distTotalTypes"><?= number_format($distribution['totalTypes']) ?></strong>
            </div>
            <div class="dist-stat">
                <span class="dist-stat-label">Most Populated Scholarship Type</span>
                <strong class="dist-stat-value dist-stat-text" id="distMostPopular"><?= $distribution['mostPopular'] ? htmlspecialchars($distribution['mostPopular']['type']) . ' (' . (int)$distribution['mostPopular']['count'] . ')' : '—' ?></strong>
            </div>
        </div>

        <div class="chart-block">
            <div class="chart-title">Scholarship Distribution</div>
            <div class="chart-scroll">
                <div class="chart-canvas-wrap" id="distChartWrap">
                    <canvas id="scholarshipChart" role="img" aria-label="Vertical bar chart of approved scholars by scholarship type."></canvas>
                </div>
            </div>
        </div>

        <div class="chart-block">
            <div class="chart-title">Monthly Applications</div>
            <div class="chart-scroll">
                <div class="chart-canvas-wrap">
                    <canvas id="monthlyChart" role="img" aria-label="Bar chart of monthly applications."></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="notification-container">
        <h3>Recent Activity & Alerts</h3>
        <?php if (empty($recent_activity)): ?>
            <div class="notification-item">
                <i data-lucide="info"></i>
                <div>
                    <h4>No Recent Activity</h4>
                    <p>System actions will show up here as they happen.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($recent_activity as $entry): ?>
                <div class="notification-item">
                    <i data-lucide="<?= htmlspecialchars(activityIcon($entry['action'])) ?>"></i>
                    <div>
                        <h4><?= htmlspecialchars($entry['action']) ?></h4>
                        <p><?= htmlspecialchars($entry['description']) ?> &middot; <?= htmlspecialchars(timeAgo($entry['created_at'])) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>