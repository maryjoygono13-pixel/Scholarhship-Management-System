<?php
$page_title = "Account & System Settings";
$page_css = "settings.css";
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/settings_helper.php';
$pdo = getDB();

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['portal_config'])) {
        setSetting($pdo, 'history_enabled', isset($_POST['history_enabled']) ? '1' : '0');
    }
    $success = 'Account and security settings updated successfully!';
}

$historyEnabled = getSetting($pdo, 'history_enabled', '1') === '1';
?>

<div class="main-content">
    <?php if (!empty($success)): ?>
        <div class="settings-alert success"><?= htmlspecialchars($success) ?></div>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
