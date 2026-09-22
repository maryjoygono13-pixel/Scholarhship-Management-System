<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/gmail_client.php';

// Starts the Google OAuth flow. This is a normal browser navigation (a link on the
// Notifications page), so it redirects instead of returning JSON.

$back = SITE_URL . '/notification';

if (empty($_SESSION['user_logged_in']) || strtolower((string)($_SESSION['user_role'] ?? '')) !== 'registrar') {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

try {
    header('Location: ' . gmailBeginAuth());
} catch (GmailException $e) {
    $code = $e->kind === 'not_configured' ? 'not_configured' : 'config';
    header('Location: ' . $back . '?gmail_error=' . $code);
}
exit();
