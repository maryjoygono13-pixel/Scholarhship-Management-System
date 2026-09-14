<?php
if (session_status() === PHP_SESSION_NONE) {
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
    if (empty($_SESSION['user_logged_in'])) {
        header("Location: " . SITE_BASE . "/login");
        exit();
    }
}
