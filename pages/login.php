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
 * ever sign in. While that is the case, only the account-setup form is offered (there is nothing
 * to sign in to yet). Once an account exists, creating another Registrar account is done from the
 * command line (database/create_user.php), not from this public page.
 */
$needsSetup = ((int)getDB()->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0);
$setupName = '';
$setupEmail = '';

if ($needsSetup && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_account'])) {

    $setupName = trim($_POST['setup_name'] ?? '');
    $setupEmail = trim($_POST['setup_email'] ?? '');
    $setupPassword = $_POST['setup_password'] ?? '';
    $setupConfirm = $_POST['setup_confirm'] ?? '';

    $pdo = getDB();

    if ($setupName === '') {
        $error = 'Please enter your name.';
    } elseif (!filter_var($setupEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($setupPassword) < 8) {
        $error = 'The password must be at least 8 characters long.';
    } elseif ($setupPassword !== $setupConfirm) {
        $error = 'The password and its confirmation do not match.';
    } else {
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'registrar')")
            ->execute([$setupName, $setupEmail, password_hash($setupPassword, PASSWORD_DEFAULT)]);
        $newId = (int)$pdo->lastInsertId();

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

if (!$needsSetup && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identifier'])) {

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = getDB()->prepare("SELECT id, name, email, password_hash, role FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash']) && strtolower($user['role']) === 'registrar') {

            session_start();
            session_regenerate_id(true);
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_identifier'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            logActivity(getDB(), 'User Login', 'Authentication', $user['name'] . ' logged in.');

            header("Location: " . SITE_URL . "/dashboard");
            exit();
        } else {
            // Keep the error generic so we don't reveal whether an email/account exists.
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
        background: url('<?= SITE_BASE ?>/assets/img/login-bg.webp') center center / cover no-repeat;
        z-index: 0;
    }

    .bg-overlay::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(160deg, rgba(19, 78, 42, 0.82) 0%, rgba(27, 99, 54, 0.78) 45%, rgba(15, 45, 26, 0.88) 100%);
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
        position: relative;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        width: 100%;
        max-width: 420px;
        padding: 36px 32px;
        border-radius: 16px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.5);
        text-align: center;
        animation: cardRiseIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    @keyframes cardRiseIn {
        from { opacity: 0; transform: translateY(24px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* ---------- Split sliding panel (regular sign-in view) ---------- */
    .auth-shell {
        position: relative;
        width: 100%;
        max-width: 760px;
        height: 500px;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
        animation: cardRiseIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
        transform: scale(1);
        transition: transform 0.85s cubic-bezier(0.19, 1, 0.22, 1), box-shadow 0.85s cubic-bezier(0.19, 1, 0.22, 1);
    }

    /* A very slight "settle" dip while the panels glide — barely perceptible on its own, but
       it's what makes the motion read as smooth momentum instead of a flat linear slide. */
    .auth-shell.is-sliding {
        transform: scale(0.99);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.32);
    }

    .auth-track {
        display: flex;
        width: 200%;
        height: 100%;
        transition: transform 0.85s cubic-bezier(0.19, 1, 0.22, 1);
    }

    .auth-shell.show-apply .auth-track {
        transform: translateX(-50%);
    }

    .auth-slide {
        display: flex;
        width: 50%;
        height: 100%;
        flex-shrink: 0;
    }

    .auth-half {
        width: 50%;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 44px 40px;
        flex-shrink: 0;
    }

    .auth-half.is-white {
        background: rgba(255, 255, 255, 0.98);
        text-align: left;
    }

    .auth-half.is-accent {
        background: linear-gradient(160deg, var(--green-900) 0%, var(--green-800) 50%, #0f2d1a 100%);
        color: #fff;
        text-align: center;
        align-items: center;
        position: relative;
        overflow: hidden;
    }

    .auth-half.is-accent::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image:
            radial-gradient(circle at 20% 20%, rgba(255,255,255,0.10) 0, transparent 45%),
            radial-gradient(circle at 85% 80%, rgba(255,255,255,0.08) 0, transparent 40%);
    }

    .auth-half.is-accent > * {
        position: relative;
        z-index: 1;
    }

    .auth-half .logo-wrap {
        margin-bottom: 14px;
    }

    .auth-half.is-white .logo-wrap {
        text-align: left;
    }

    .auth-half h1 {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .auth-half.is-white h1 {
        color: var(--green-900);
    }

    .auth-half.is-accent h1 {
        font-size: 24px;
    }

    .auth-half p.sub {
        font-size: 13px;
        margin-bottom: 20px;
    }

    .auth-half.is-white p.sub {
        color: #6b7280;
    }

    .auth-half.is-accent p.sub {
        color: rgba(255, 255, 255, 0.85);
        font-size: 13.5px;
        line-height: 1.6;
        margin-bottom: 26px;
        max-width: 260px;
    }

    .auth-outline-btn {
        display: inline-block;
        min-width: 200px;
        height: 46px;
        line-height: 44px;
        padding: 0 24px;
        background: transparent;
        color: #fff;
        font-size: 13.5px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        border: 1.5px solid rgba(255, 255, 255, 0.85);
        border-radius: 999px;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.2s ease, transform 0.15s ease;
        font-family: inherit;
    }

    .auth-outline-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-1px);
    }

    @media (max-width: 720px) {
        .auth-shell {
            height: auto;
            max-width: 420px;
            transition: none;
        }
        .auth-shell.is-sliding {
            transform: none;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
        }
        .auth-track {
            width: 100%;
            transition: none;
        }
        .auth-shell.show-apply .auth-track {
            transform: none;
        }
        .auth-slide {
            width: 100%;
            flex-direction: column;
        }
        .auth-slide.slide-apply {
            display: none;
        }
        .auth-shell.show-apply .auth-slide.slide-signin {
            display: none;
        }
        .auth-shell.show-apply .auth-slide.slide-apply {
            display: flex;
        }
        .auth-half {
            width: 100%;
            padding: 32px 28px;
        }
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

    .login-card p.sub {
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
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.1s ease;
    }

    .form-input:focus {
        border-color: var(--green-700);
        box-shadow: 0 0 0 3px rgba(35, 143, 84, 0.12);
        transform: translateY(-1px);
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
        transition: background 0.2s ease, transform 0.15s ease;
        margin-top: 8px;
    }

    .btn-submit:hover {
        background: var(--green-800);
        transform: translateY(-1px);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    .forgot-link {
        display: block;
        text-align: center;
        margin-top: 14px;
        font-size: 13px;
        color: var(--green-800);
        text-decoration: none;
        font-weight: 600;
    }

    .forgot-link:hover {
        text-decoration: underline;
    }

    .apply-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 22px 0 16px;
        color: #9ca3af;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .apply-divider::before,
    .apply-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }

    .apply-link-btn {
        display: block;
        width: 100%;
        height: 46px;
        line-height: 46px;
        text-align: center;
        background: #ffffff;
        color: var(--green-800);
        font-size: 14px;
        font-weight: 700;
        border: 1.5px solid var(--green-700);
        border-radius: 8px;
        text-decoration: none;
        transition: background 0.2s ease, transform 0.15s ease;
    }

    .apply-link-btn:hover {
        background: #f0f9f2;
        transform: translateY(-1px);
    }

</style>

</head>

<body>

<div class="bg-overlay"></div>

<div class="viewport-wrapper">

<?php if ($needsSetup): ?>

<div class="login-card">

    <div class="logo-wrap">
        <img
            src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png"
            alt="Portal Logo"
        >
    </div>

    <h1>Registrar Portal</h1>

    <p class="sub">No account exists yet. Create the first Registrar account to get started.</p>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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

</div>

<?php else: ?>

<div class="auth-shell" id="authShell">
    <div class="auth-track" id="authTrack">

        <!-- Slide 1: Sign In (white) | Apply teaser (accent) -->
        <div class="auth-slide slide-signin">

            <div class="auth-half is-white">
                <div class="logo-wrap">
                    <img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png" alt="Portal Logo" style="width:52px; height:52px; object-fit:contain;">
                </div>

                <h1>Registrar Portal</h1>
                <p class="sub">Sign in to manage the scholarship system</p>

                <?php if (!empty($error)): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= SITE_BASE ?>/login" id="loginForm">

                    <div class="form-group">
                        <label for="identifier">Email Address</label>
                        <input type="email" id="identifier" name="identifier" class="form-input" placeholder="Enter your registered email" autocomplete="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" autocomplete="current-password" required>
                            <button type="button" class="eye-toggle" onclick="togglePassword()" aria-label="Toggle Password Visibility">
                                <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Sign In</button>

                    <a href="<?= SITE_BASE ?>/forgot-password" class="forgot-link">Forgot Password?</a>

                </form>
            </div>

            <div class="auth-half is-accent">
                <div class="logo-wrap">
                    <img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png" alt="" style="width:48px; height:48px; object-fit:contain;">
                </div>
                <h1>New Here?</h1>
                <p class="sub">Apply for a scholarship using your Student ID — no account or password needed.</p>
                <button type="button" class="auth-outline-btn" id="goApplyBtn">Apply for a Scholarship</button>
            </div>

        </div>

        <!-- Slide 2: Welcome-back teaser (accent) | Apply CTA (white) -->
        <div class="auth-slide slide-apply">

            <div class="auth-half is-accent">
                <div class="logo-wrap">
                    <img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png" alt="" style="width:48px; height:48px; object-fit:contain;">
                </div>
                <h1>Welcome Back</h1>
                <p class="sub">Already a registrar? Sign in to manage the scholarship system.</p>
                <button type="button" class="auth-outline-btn" id="goSignInBtn">Sign In</button>
            </div>

            <div class="auth-half is-white">
                <div class="logo-wrap">
                    <img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png" alt="Portal Logo" style="width:52px; height:52px; object-fit:contain;">
                </div>
                <h1>Scholarship Application</h1>
                <p class="sub">Ready to apply? You'll need your Student ID to get started — the form takes just a few minutes.</p>
                <a href="<?= SITE_BASE ?>/apply" class="btn-submit" style="display:block; text-decoration:none; line-height:46px; text-align:center;">Continue to Application</a>
            </div>

        </div>

    </div>
</div>

<?php endif; ?>

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

const authShell = document.getElementById("authShell");
const goApplyBtn = document.getElementById("goApplyBtn");
const goSignInBtn = document.getElementById("goSignInBtn");
let slideTimer = null;

// The slide itself is the .show-apply transform transition (see CSS); this just layers on
// the brief "is-sliding" dip (a hair of scale + deeper shadow) for the whole 0.85s glide, so
// the motion reads as smooth momentum rather than a flat, mechanical slide.
function goToSlide(showApply) {
    if (!authShell) return;
    authShell.classList.toggle("show-apply", showApply);
    authShell.classList.add("is-sliding");
    clearTimeout(slideTimer);
    slideTimer = setTimeout(() => authShell.classList.remove("is-sliding"), 850);
}

if (authShell && goApplyBtn) {
    goApplyBtn.addEventListener("click", () => goToSlide(true));
}
if (authShell && goSignInBtn) {
    goSignInBtn.addEventListener("click", () => goToSlide(false));
}

</script>

</body>

</html>
