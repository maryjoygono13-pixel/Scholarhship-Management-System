<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';

if (!empty($_SESSION['user_logged_in'])) {
    header("Location: " . SITE_URL . "/dashboard");
    exit();
}

$error = '';

/*
 * First run: a brand-new database has no accounts (the `users` table is empty), so nobody could
 * ever sign in. While that is the case, this page offers a form to create the first Registrar
 * account. As soon as one exists the form is gone and only the normal sign-in remains.
 */
$needsSetup = ((int)getDB()->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0);
$setupName = '';
$setupEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_account'])) {

    $setupName = trim($_POST['setup_name'] ?? '');
    $setupEmail = trim($_POST['setup_email'] ?? '');
    $setupPassword = $_POST['setup_password'] ?? '';
    $setupConfirm = $_POST['setup_confirm'] ?? '';

    // Checked again on the server: this only ever works while there are no accounts.
    if (!$needsSetup) {
        $error = 'An account already exists. Please sign in.';
    } elseif ($setupName === '') {
        $error = 'Please enter your name.';
    } elseif (!filter_var($setupEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($setupPassword) < 8) {
        $error = 'The password must be at least 8 characters long.';
    } elseif ($setupPassword !== $setupConfirm) {
        $error = 'The password and its confirmation do not match.';
    } else {
        $pdo = getDB();
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'registrar')")
            ->execute([$setupName, $setupEmail, password_hash($setupPassword, PASSWORD_DEFAULT)]);
        $newId = (int)$pdo->lastInsertId();

        // Sign in straight away (config.php closes the session, so reopen it to write).
        session_start();
        session_regenerate_id(true);
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $newId;
        $_SESSION['user_name'] = $setupName;
        $_SESSION['user_identifier'] = $setupEmail;
        $_SESSION['user_role'] = 'registrar';

        logActivity($pdo, 'Account Created', 'Authentication', $setupName . ' created the first Registrar account.', $newId);

        header("Location: " . SITE_URL . "/dashboard");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['setup_account'])) {

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {

        $error = 'Please fill in all required fields.';

    } else {

        // Find the Registrar account by email.
        $stmt = getDB()->prepare("
            SELECT id, name, email, password_hash, role
            FROM users
            WHERE LOWER(email) = LOWER(?)
            LIMIT 1
        ");

        $stmt->execute([$identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify the password and make sure the account is a Registrar.
        if (
            $user &&
            password_verify($password, $user['password_hash']) &&
            strtolower($user['role']) === 'registrar'
        ) {

            /*
             * Reacquire the session for writing.
             * config.php closes the session immediately after
             * reading it to avoid blocking other requests.
             */
            session_start();

            // Prevent session fixation after successful login.
            session_regenerate_id(true);

            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_identifier'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            logActivity(
                getDB(),
                'User Login',
                'Authentication',
                $user['name'] . ' logged in.'
            );

            header("Location: " . SITE_URL . "/dashboard");
            exit();

        } else {

            // Keep the error generic so we don't reveal
            // whether an email/account exists.
            $error = 'Invalid email or password.';
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Scholarship Portal - Sign In</title>

<link rel="icon" href="<?= SITE_BASE ?>/assets/img/cmlogoremove.png">

<link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap"
>

<style>

    :root {
        --green-900: #134e2a;
        --green-800: #1b6336;
        --green-700: #238f54;
        --bg-main: #f4f7f5;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'DM Sans', -apple-system, sans-serif;
    }

    html,
    body {
        height: 100vh;
        width: 100vw;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }

    body {
        background: #134e2a;
        position: relative;
    }

    .bg-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image:
            linear-gradient(
                rgba(19, 78, 42, 0.45),
                rgba(15, 45, 26, 0.60)
            ),
            url("<?= SITE_BASE ?>/assets/img/cm2.jpg");
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        filter: blur(8px);
        transform: scale(1.05);
        z-index: 0;
    }

    .viewport-wrapper {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
        padding: 20px;
    }

    .login-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        width: 100%;
        max-width: 420px;
        padding: 36px 32px;
        border-radius: 16px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.5);
        text-align: center;
    }

    .logo-wrap {
        margin-bottom: 16px;
    }

    .logo-wrap img {
        width: 60px;
        height: 60px;
        object-fit: contain;
    }

    .login-card h1 {
        font-size: 22px;
        font-weight: 700;
        color: var(--green-900);
        margin-bottom: 4px;
    }

    .login-card p {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 20px;
    }

    .error-msg {
        background: #fef2f2;
        color: #991b1b;
        padding: 10px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 16px;
        border: 1px solid #fee2e2;
    }

    .form-group {
        margin-bottom: 16px;
        text-align: left;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }

    .input-wrapper {
        position: relative;
    }

    .form-input {
        width: 100%;
        height: 44px;
        padding: 0 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s ease;
    }

    .form-input:focus {
        border-color: var(--green-700);
        box-shadow: 0 0 0 3px rgba(35, 143, 84, 0.12);
    }

    .eye-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #6b7280;
        display: flex;
        align-items: center;
    }

    .btn-submit {
        width: 100%;
        height: 46px;
        background: var(--green-900);
        color: #ffffff;
        font-size: 15px;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.2s ease;
        margin-top: 8px;
    }

    .btn-submit:hover {
        background: var(--green-800);
    }

</style>

</head>

<body>

<div class="bg-overlay"></div>

<div class="viewport-wrapper">

<div class="login-card">

    <div class="logo-wrap">
        <img
            src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png"
            alt="Portal Logo"
        >
    </div>

    <h1>Registrar Portal</h1>

    <p><?= $needsSetup ? 'No account exists yet. Create the first Registrar account to get started.' : 'Sign in to access the Registrar Management System' ?></p>

    <?php if (!empty($error)): ?>

        <div class="error-msg">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($needsSetup): ?>

    <form method="POST" action="<?= SITE_BASE ?>/login" id="setupForm" autocomplete="off">

        <input type="hidden" name="setup_account" value="1">

        <div class="form-group">
            <label for="setup_name">Full Name</label>
            <input type="text" id="setup_name" name="setup_name" class="form-input" placeholder="Registrar Staff" value="<?= htmlspecialchars($setupName) ?>" required>
        </div>

        <div class="form-group">
            <label for="setup_email">Email Address</label>
            <input type="email" id="setup_email" name="setup_email" class="form-input" placeholder="registrar@sms.local" value="<?= htmlspecialchars($setupEmail) ?>" autocomplete="username" required>
        </div>

        <div class="form-group">
            <label for="setup_password">Password (at least 8 characters)</label>
            <input type="password" id="setup_password" name="setup_password" class="form-input" placeholder="••••••••" autocomplete="new-password" minlength="8" required>
        </div>

        <div class="form-group">
            <label for="setup_confirm">Confirm Password</label>
            <input type="password" id="setup_confirm" name="setup_confirm" class="form-input" placeholder="••••••••" autocomplete="new-password" minlength="8" required>
        </div>

        <button type="submit" class="btn-submit">Create Account</button>

    </form>

    <?php else: ?>

    <form
        method="POST"
        action="<?= SITE_BASE ?>/login"
        id="loginForm"
    >

        <div class="form-group">

            <label for="identifier">
                Email Address
            </label>

            <input
                type="email"
                id="identifier"
                name="identifier"
                class="form-input"
                placeholder="registrar@sms.local"
                autocomplete="username"
                required
            >

        </div>

        <div class="form-group">

            <label for="password">
                Password
            </label>

            <div class="input-wrapper">

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="eye-toggle"
                    onclick="togglePassword()"
                    aria-label="Toggle Password Visibility"
                >

                    <svg
                        id="eyeIcon"
                        xmlns="http://www.w3.org/2000/svg"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>

                </button>

            </div>

        </div>

        <button
            type="submit"
            class="btn-submit"
        >
            Sign In
        </button>

    </form>

    <?php endif; ?>

</div>

</div>

<script>

function togglePassword() {

    const input = document.getElementById("password");
    const icon = document.getElementById("eyeIcon");

    if (input.type === "password") {

        input.type = "text";

        icon.innerHTML = `
            <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>
            <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
            <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/>
            <path d="m2 2 20 20"/>
        `;

    } else {

        input.type = "password";

        icon.innerHTML = `
            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
            <circle cx="12" cy="12" r="3"/>
        `;

    }

}

</script>

</body>

</html>
