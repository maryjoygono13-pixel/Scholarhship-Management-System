<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';

if (!empty($_SESSION['user_logged_in'])) {
    $identifier = trim((string)($_SESSION['user_identifier'] ?? '')) ?: 'Registrar Staff';
    logActivity(getDB(), 'User Logout', 'Authentication', $identifier . ' logged out.');
} elseif (!empty($_SESSION['student_logged_in'])) {
    $studentName = trim((string)($_SESSION['student_name'] ?? '')) ?: (string)($_SESSION['student_id'] ?? 'Student');
    logActivity(getDB(), 'Student Logout', 'Student Portal', $studentName . ' signed out of the Student Portal.');
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header("Location: " . SITE_BASE . "/login");
exit();
