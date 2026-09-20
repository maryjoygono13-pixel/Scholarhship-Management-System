<?php
// Output is buffered so that, after saving, the page can redirect (Post/Redirect/Get) even
// though the layout has already started printing.
ob_start();
$page_title = "Account & System Settings";
$page_css = "settings.css";
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/term_helper.php';
$pdo = getDB();

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['portal_config'])) {
        setSetting($pdo, 'history_enabled', isset($_POST['history_enabled']) ? '1' : '0');
        try {
            $term = changeActiveTerm(
                $pdo,
                (string)($_POST['active_semester'] ?? getActiveSemester($pdo)),
                (string)($_POST['academic_year'] ?? getActiveSchoolYear($pdo))
            );
            $success = $term['changed']
                ? 'Active term is now ' . $term['semester'] . ', ' . $term['schoolYear'] . '. ' . $term['moved'] . ' scholar(s) were sent to Renewal & Retention and back to Evaluation.' . (($term['skipped'] ?? 0) > 0 ? ' ' . $term['skipped'] . ' scholar(s) already had a record for this semester and were left as they are.' : '')
                : 'Portal configuration saved.';
        } catch (Throwable $e) {
            $error = 'Could not change the active term: ' . $e->getMessage();
        }
    } else {
        $success = 'Account and security settings updated successfully!';
    }

    // Reload the page instead of leaving it on the form submission: refreshing can no longer
    // re-send the form (and repeat the semester change), and the message shows just once.
    // config.php closes the session right after reading it, so reopen it to store the message.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['settings_flash'] = ['success' => $success, 'error' => $error];
    session_write_close();
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Location: ' . SITE_BASE . '/settings');
    exit;
}

// The message left by the save that just redirected here (shown once, then forgotten).
if (!empty($_SESSION['settings_flash'])) {
    $success = (string)($_SESSION['settings_flash']['success'] ?? '');
    $error = (string)($_SESSION['settings_flash']['error'] ?? '');
    // Forget it so a refresh doesn't show it again (needs the session reopened for writing).
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION['settings_flash']);
    session_write_close();
}

$historyEnabled = getSetting($pdo, 'history_enabled', '1') === '1';
$activeSemester = getActiveSemester($pdo);
$activeSchoolYear = getActiveSchoolYear($pdo);

// Academic years offered in the dropdown: a couple back, a few ahead of the active one.
$startYear = preg_match('/^(\d{4})/', $activeSchoolYear, $m) ? (int)$m[1] : (int)date('Y');
$schoolYears = [];
for ($y = $startYear - 2; $y <= $startYear + 3; $y++) {
    $schoolYears[] = $y . '-' . ($y + 1);
}
?>

<div class="main-content">
    <?php if (!empty($success)): ?>
        <div class="settings-alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="settings-alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="settings-grid">
        <!-- Account & Security Section (All Users) -->
        <div class="settings-card">
            <h3>Profile & Security</h3>
            <p class="settings-subtitle">Update your username, contact email, and password.</p>

            <form method="POST">
                <div class="form-group">
                    <label>Username / Email</label>
                    <input type="text" class="form-input" name="user_identifier" value="<?= htmlspecialchars($_SESSION['user_identifier'] ?? 'admin@scholarship.gov') ?>" required>
                </div>

                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" class="form-input" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" class="form-input" placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" class="form-input" placeholder="••••••••">
                </div>

                <button type="submit" class="btn-save">Update Profile</button>
            </form>
        </div>

        <!-- System Configuration -->
        <div class="settings-card">
            <h3>Portal Configuration</h3>
            <p class="settings-subtitle">Manage portal preferences and administrative settings.</p>

            <form method="POST" id="portalConfigForm" data-current-semester="<?= htmlspecialchars($activeSemester) ?>" data-current-year="<?= htmlspecialchars($activeSchoolYear) ?>">
                <input type="hidden" name="portal_config" value="1">

                <div class="form-group">
                    <label>Academic Year</label>
                    <select class="form-input" name="academic_year" id="academicYearSelect">
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?= htmlspecialchars($sy) ?>" <?= $sy === $activeSchoolYear ? 'selected' : '' ?>><?= htmlspecialchars(str_replace('-', ' - ', $sy)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Active Semester</label>
                    <select class="form-input" name="active_semester" id="activeSemesterSelect">
                        <?php foreach (TERM_SEMESTERS as $sem): ?>
                            <option value="<?= htmlspecialchars($sem) ?>" <?= $sem === $activeSemester ? 'selected' : '' ?>><?= htmlspecialchars($sem) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="settings-subtitle" style="margin-top:6px;">Applicants, Records and Renewal &amp; Retention follow this term. Moving to the next term sends approved scholars to Renewal &amp; Retention and back to Evaluation, unless they already have a record for the semester you switch to (then they are left as they are). Going from Summer Term to 1st Semester starts the next academic year.</p>
                </div>

                <div class="form-group">
                    <label>Email Notifications</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" checked> Automated renewal reminders
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" checked> Email alert on new submissions
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Menu Access</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="history_enabled" <?= $historyEnabled ? 'checked' : '' ?>> Enable History / Audit Trail menu
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-save">Save Portal Config</button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('portalConfigForm');
    const semSelect = document.getElementById('activeSemesterSelect');
    const yearSelect = document.getElementById('academicYearSelect');
    if (!form || !semSelect || !yearSelect) return;

    const currentSem = form.dataset.currentSemester;
    const currentYear = form.dataset.currentYear;

    function nextYear(sy) {
        const m = /^(\d{4})-(\d{4})$/.exec(sy);
        return m ? (parseInt(m[1], 10) + 1) + '-' + (parseInt(m[2], 10) + 1) : sy;
    }

    // Summer -> 1st Semester starts the next academic year, so preselect it.
    semSelect.addEventListener('change', function () {
        const target = (currentSem === 'Summer Term' && semSelect.value === '1st Semester')
            ? nextYear(currentYear)
            : currentYear;
        if (Array.from(yearSelect.options).some(function (o) { return o.value === target; })) {
            yearSelect.value = target;
        }
    });

    form.addEventListener('submit', function (e) {
        if (semSelect.value === currentSem && yearSelect.value === currentYear) return;
        const ok = confirm(
            'Change the active term to ' + semSelect.value + ' ' + yearSelect.value + '?\n\n' +
            'Scholars approved in ' + currentSem + ' ' + currentYear + ' will be sent to Renewal & Retention ' +
            'and back to Evaluation for the new term. Their earlier grades are kept.'
        );
        if (!ok) e.preventDefault();
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
