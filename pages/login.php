<?php
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['user_logged_in'])) {
    header("Location: " . SITE_URL . "/dashboard");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($identifier) && !empty($password)) {
        // Reacquire the session for writing — config.php closes it
        // immediately after reading it to avoid blocking other requests.
        session_start();
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_role'] = 'registrar';
        $_SESSION['user_identifier'] = $identifier;     
        
        header("Location: " . SITE_URL . "/dashboard");
        exit();
    } else {
        $error = 'Please fill in all required fields.';
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap">
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

        html, body {
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
            background-image: linear-gradient(rgba(19, 78, 42, 0.45), rgba(15, 45, 26, 0.60)), url("<?= SITE_BASE ?>/assets/img/cm2.jpg");
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
            <img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png" alt="Portal Logo">
        </div>
        <h1>Registrar Portal</h1>
        <p>Sign in to access the Registrar Management System</p>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= SITE_BASE ?>/login" id="loginForm">
            <div class="form-group">
                <label for="identifier" id="labelIdentifier">Email Address</label>
                <input type="email" id="identifier" name="identifier" class="form-input" placeholder="admin@scholarship.gov" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                    <button type="button" class="eye-toggle" onclick="togglePassword()" aria-label="Toggle Password Visibility">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">Sign In</button>
        </form>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById("password");
    const icon = document.getElementById("eyeIcon");
    if (input.type === "password") {
        input.type = "text";
        icon.innerHTML = `<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/>`;
    } else {
        input.type = "password";
        icon.innerHTML = `<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>`;
    }
}
</script>
</body>
</html>
