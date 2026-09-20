<?php
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';
checkAuth();
$pdo = getDB();

// Form handling runs before header.php so the session can still be
// updated (a session cookie can't be set once output has started).
$success = '';
$errors = [];
$profileIdentifier = $_SESSION['user_identifier'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['portal_config'])) {
        setSetting($pdo, 'history_enabled', isset($_POST['history_enabled']) ? '1' : '0');
        $success = 'Portal settings saved.';
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
}

$page_title = "Account & System Settings";
$page_css = "settings.css";
include __DIR__ . '/../includes/header.php';

$historyEnabled = getSetting($pdo, 'history_enabled', '1') === '1';
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

            <form method="POST">
                <input type="hidden" name="portal_config" value="1">

                <div class="form-group">
                    <label>Academic Year</label>
                    <select class="form-input">
                        <option value="2025-2026" selected>2025 - 2026</option>
                        <option value="2024-2025">2024 - 2025</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Active Semester</label>
                    <select class="form-input">
                        <option value="1st" selected>1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                        <option value="Summer">Summer Term</option>
                    </select>
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
