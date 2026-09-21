<?php
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/term_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';
checkAuth();
$pdo = getDB();

// Form handling runs before header.php so the session can still be updated (a session cookie
// can't be set once output has started) and so the page can redirect after saving.
$success = '';
$errors = [];
$profileIdentifier = $_SESSION['user_identifier'] ?? '';

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
            $errors[] = 'Could not change the active term: ' . $e->getMessage();
        }
    }

    if (isset($_POST['profile_update'])) {
        $newEmail = trim($_POST['user_identifier'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $profileIdentifier = $newEmail;

        $stmt = $pdo->prepare("SELECT id, name, email, password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = 'Your account could not be found. Please sign in again.';
        } elseif ($currentPassword === '' || !password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        } else {
            $changeEmail = strcasecmp($newEmail, $user['email']) !== 0;
            $changePassword = $newPassword !== '' || $confirmPassword !== '';

            if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif ($changeEmail) {
                $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?) AND id != ?");
                $dup->execute([$newEmail, $user['id']]);
                if ((int)$dup->fetchColumn() > 0) {
                    $errors[] = 'That email is already used by another account.';
                }
            }

            if ($changePassword) {
                if (strlen($newPassword) < 8) {
                    $errors[] = 'The new password must be at least 8 characters long.';
                } elseif ($newPassword !== $confirmPassword) {
                    $errors[] = 'The new password and its confirmation do not match.';
                } elseif (password_verify($newPassword, $user['password_hash'])) {
                    $errors[] = 'The new password must be different from your current password.';
                }
            }

            if (!$errors) {
                if (!$changeEmail && !$changePassword) {
                    $success = 'No changes to save.';
                } else {
                    if ($changePassword) {
                        $pdo->prepare("UPDATE users SET email = ?, password_hash = ? WHERE id = ?")
                            ->execute([$newEmail, password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
                    } else {
                        $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$newEmail, $user['id']]);
                    }

                    // Reacquire the session for writing (config.php closes it).
                    session_start();
                    if ($changePassword) {
                        session_regenerate_id(true);
                    }
                    $_SESSION['user_identifier'] = $newEmail;
                    session_write_close();

                    if ($changePassword) {
                        logActivity($pdo, 'Password Changed', 'Settings', $user['name'] . ' changed their password.', $user['id']);
                    }
                    if ($changeEmail) {
                        logActivity($pdo, 'Profile Updated', 'Settings', $user['name'] . ' changed their sign-in email to ' . $newEmail . '.', $user['id']);
                    }

                    $profileIdentifier = $newEmail;
                    $success = $changePassword
                        ? 'Your password was changed successfully.'
                        : 'Your profile was updated successfully.';
                }
            }
        }
    }

    // After a successful save, reload the page instead of leaving it on the form submission:
    // refreshing can no longer re-send the form (and repeat the semester change), and the
    // message shows just once. Errors are shown straight away so the typed email is kept.
    if ($success !== '' && !$errors) {
        // config.php closes the session right after reading it, so reopen it to store the message.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['settings_flash'] = ['success' => $success];
        session_write_close();
        header('Location: ' . SITE_BASE . '/settings');
        exit;
    }
}

// The message left by the save that just redirected here (shown once, then forgotten).
if (!empty($_SESSION['settings_flash'])) {
    $success = (string)($_SESSION['settings_flash']['success'] ?? '');
    // Forget it so a refresh doesn't show it again (needs the session reopened for writing).
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION['settings_flash']);
    session_write_close();
}

$page_title = "Account & System Settings";
$page_css = "settings.css";
include __DIR__ . '/../includes/header.php';

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
    <?php foreach ($errors as $err): ?>
        <div class="settings-alert error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <div class="settings-grid">
        <!-- Account & Security Section (All Users) -->
        <div class="settings-card">
            <h3>Profile & Security</h3>
            <p class="settings-subtitle">Update your sign-in email and change your password.</p>

            <form method="POST" id="profileForm" autocomplete="off">
                <input type="hidden" name="profile_update" value="1">

                <div class="form-group">
                    <label for="profileEmail">Sign-in Email</label>
                    <input type="email" class="form-input" id="profileEmail" name="user_identifier" value="<?= htmlspecialchars($profileIdentifier) ?>" required>
                </div>

                <div class="form-group">
                    <label for="currentPassword">Current Password</label>
                    <div class="password-field">
                        <input type="password" class="form-input" id="currentPassword" name="current_password" placeholder="Required to save any change" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-toggle="currentPassword">Show</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <div class="password-field">
                        <input type="password" class="form-input" id="newPassword" name="new_password" placeholder="Leave blank to keep your password" autocomplete="new-password" minlength="8">
                        <button type="button" class="password-toggle" data-toggle="newPassword">Show</button>
                    </div>
                    <p class="field-hint">At least 8 characters.</p>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" class="form-input" id="confirmPassword" name="confirm_password" placeholder="Re-enter the new password" autocomplete="new-password">
                        <button type="button" class="password-toggle" data-toggle="confirmPassword">Show</button>
                    </div>
                    <p class="field-hint" id="confirmHint"></p>
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
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
        });
    });

    var form = document.getElementById('profileForm');
    var np = document.getElementById('newPassword');
    var cp = document.getElementById('confirmPassword');
    var hint = document.getElementById('confirmHint');
    function check() {
        if (!cp.value) { hint.textContent = ''; hint.className = 'field-hint'; return true; }
        var ok = np.value === cp.value;
        hint.textContent = ok ? 'Passwords match.' : 'Passwords do not match.';
        hint.className = 'field-hint ' + (ok ? 'ok' : 'bad');
        return ok;
    }
    np.addEventListener('input', check);
    cp.addEventListener('input', check);
    form.addEventListener('submit', function (e) {
        if ((np.value || cp.value) && (np.value.length < 8 || !check())) {
            e.preventDefault();
            alert(np.value.length < 8 ? 'The new password must be at least 8 characters long.' : 'The new password and its confirmation do not match.');
        }
    });
})();

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
