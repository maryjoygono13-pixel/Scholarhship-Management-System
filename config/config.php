<?php
if (session_status() === PHP_SESSION_NONE) {

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    /*
     * PHP's default session.gc_maxlifetime is only 1440 seconds (24 minutes). That's how
     * long the session data file survives on the server, separate from the cookie itself —
     * a registrar who leaves a form open longer than that (reading a long applicant record,
     * filling in a scholarship program) gets silently logged out server-side while the
     * browser still shows them as signed in, so saving then fails with "Unauthorized."
     * Eight hours comfortably covers a full work session.
     */
    ini_set('session.gc_maxlifetime', '28800');

    /*
     * This app's own session cookie and session folder. Other PHP apps on the same server
     * (e.g. other copies of this system in htdocs) otherwise share the default PHPSESSID
     * cookie and folder: signing in/out of one replaces or destroys this app's session, and
     * their 24-minute cleanup deletes it — which surfaced as "Unauthorized." on save.
     */
    session_name('SMS_SESSION');
    $smsSessionDir = rtrim((string)(ini_get('session.save_path') ?: sys_get_temp_dir()), '/\\') . DIRECTORY_SEPARATOR . 'sms_sessions';
    if (is_dir($smsSessionDir) || @mkdir($smsSessionDir, 0700, true)) {
        if (is_writable($smsSessionDir)) {
            session_save_path($smsSessionDir);
        }
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
    /*
     * PHP's default session handler holds an exclusive lock on the
     * session file for as long as the request is running. Since
     * almost every page/API call here only READS $_SESSION (for the
     * login check), we release that lock immediately instead of
     * holding it for the request's full duration. Otherwise, one
     * slow request (e.g. an applicant save that waits on the
     * external geocoding API) blocks every other request from the
     * same browser session — including unrelated pages — until it
     * finishes, which is what made the whole site feel like it froze.
     * pages/login.php reacquires the session right before it needs
     * to write to it.
     */
    session_write_close();
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = preg_replace('#/(pages|api|includes)(/.*)?$|/index\.php$#i', '', $scriptName);
$scriptDir = rtrim($scriptDir, '/');

define('SITE_BASE', $scriptDir);
define('SITE_URL', $protocol . '://' . $host . SITE_BASE);

function checkAuth() {
    if (
        empty($_SESSION['user_logged_in']) ||
        empty($_SESSION['user_role']) ||
        strtolower($_SESSION['user_role']) !== 'registrar'
    ) {
        header("Location: " . SITE_BASE . "/login");
        exit();
    }
}
