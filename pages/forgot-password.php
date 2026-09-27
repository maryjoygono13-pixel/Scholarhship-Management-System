<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!empty($_SESSION['user_logged_in'])) {
    header("Location: " . SITE_URL . "/dashboard");
    exit();
}

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Whether or not the email matches an account, the page says the same thing —
        // so this form can't be used to check who has an account here.
        $sent = true;

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))")
                ->execute([$user['id'], hash('sha256', $token)]);

            $resetLink = SITE_URL . '/reset-password?token=' . $token;
            $body = "Hi " . $user['name'] . ",\n\n"
                . "A password reset was requested for your Registrar account.\n\n"
                . "Reset your password here (this link expires in 30 minutes):\n" . $resetLink . "\n\n"
                . "If you didn't request this, you can ignore this email.";

            try {
                $result = sendAppMail($pdo, $user['email'], 'Reset your Registrar Portal password', $body);
                logActivity($pdo, 'Password Reset Requested', 'Authentication', $user['name'] . ' requested a password reset email (via ' . $result['method'] . ').', $user['id']);
            } catch (Throwable $e) {
                // Mail being down doesn't change what the visitor sees (still "sent" — no account
                // enumeration), but it's worth a record so the registrar knows why no email arrived.
                error_log('Password reset email failed: ' . $e->getMessage());
                $kind = $e instanceof GmailException ? $e->kind : 'smtp';
                logActivity($pdo, 'Password Reset Email Failed', 'Authentication', 'Could not send a reset email for ' . $user['email'] . ' (' . $kind . ').', $user['id']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - Scholarship Portal</title>
<link rel="icon" href="<?= SITE_BASE ?>/assets/img/cmlogoremove.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap">
<style>
    :root { --green-900: #134e2a; --green-800: #1b6336; --green-700: #238f54; }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'DM Sans', -apple-system, sans-serif; }
    html, body { height: 100vh; width: 100vw; overflow: hidden; }
    body { background: #134e2a; position: relative; }
    .bg-overlay {
        position: absolute; inset: 0;
        background: url('<?= SITE_BASE ?>/assets/img/login-bg.webp') center center / cover no-repeat;
        z-index: 0;
    }
    .bg-overlay::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(160deg, rgba(19, 78, 42, 0.82) 0%, rgba(27, 99, 54, 0.78) 45%, rgba(15, 45, 26, 0.88) 100%);
    }
    .viewport-wrapper { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 1; }
    .login-card {
        position: relative;
        background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); width: 100%; max-width: 420px;
        padding: 36px 32px; border-radius: 16px; box-shadow: 0 16px 40px rgba(0,0,0,0.25);
        border: 1px solid rgba(255,255,255,0.5); text-align: center;
        animation: cardRiseIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    @keyframes cardRiseIn {
        from { opacity: 0; transform: translateY(24px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .logo-wrap { margin-bottom: 16px; }
    .logo-wrap img { width: 60px; height: 60px; object-fit: contain; }
    h1 { font-size: 22px; font-weight: 700; color: var(--green-900); margin-bottom: 4px; }
    p.sub { font-size: 13px; color: #6b7280; margin-bottom: 20px; }
    .form-group { margin-bottom: 16px; text-align: left; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .form-input { width: 100%; height: 44px; padding: 0 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.1s ease; }
    .form-input:focus { border-color: var(--green-700); box-shadow: 0 0 0 3px rgba(35,143,84,0.12); transform: translateY(-1px); }
    .btn-submit { width: 100%; height: 46px; background: var(--green-900); color: #fff; font-size: 15px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; margin-top: 8px; transition: background 0.2s ease, transform 0.15s ease; }
    .btn-submit:hover { background: var(--green-800); transform: translateY(-1px); }
    .btn-submit:active { transform: translateY(0); }
    .error-msg { background: #fef2f2; color: #991b1b; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid #fee2e2; }
    .success-msg { background: #ecfdf5; color: #065f46; padding: 14px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; border: 1px solid #a7f3d0; text-align: left; line-height: 1.5; }
    .back-link { display: inline-block; margin-top: 14px; font-size: 13.5px; color: var(--green-800); text-decoration: none; font-weight: 600; }
    .back-link:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="bg-overlay"></div>
<div class="viewport-wrapper">
<div class="login-card">
    <div class="logo-wrap"><img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png" alt="Portal Logo"></div>
    <h1>Forgot Password</h1>
    <p class="sub">Enter your Registrar account email and we'll send you a reset link.</p>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($sent): ?>
        <div class="success-msg">If that email has a Registrar account, a password reset link has been sent to it. The link expires in 30 minutes.</div>
    <?php else: ?>
        <form method="POST" action="<?= SITE_BASE ?>/forgot-password">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="Enter your registered email" autocomplete="username" required>
            </div>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>
    <?php endif; ?>

    <a class="back-link" href="<?= SITE_BASE ?>/login">&larr; Back to Sign In</a>
</div>
</div>
</body>
</html>
