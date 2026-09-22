<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/gmail_client.php';

// OAuth redirect target ("Authorized redirect URI" in Google Cloud).
// The SMS login is separate: only a signed-in Registrar can complete this, and the
// random `state` created in gmail_connect.php must come back unchanged.

$back = SITE_URL . '/notification';

function gmailBack(string $query): void {
    global $back;
    header('Location: ' . $back . '?' . $query);
    exit();
}

if (empty($_SESSION['user_logged_in']) || strtolower((string)($_SESSION['user_role'] ?? '')) !== 'registrar') {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

try {
    // The user pressed "Cancel" (or Google refused) on the consent screen.
    if (isset($_GET['error'])) {
        // Consume the state so it can't be replayed.
        session_start();
        unset($_SESSION['gmail_oauth']);
        session_write_close();
        gmailBack('gmail_error=' . ($_GET['error'] === 'access_denied' ? 'denied' : 'google'));
    }

    $code = (string)($_GET['code'] ?? '');
    $state = (string)($_GET['state'] ?? '');
    if ($code === '' || $state === '') {
        gmailBack('gmail_error=state');
    }

    $verifier = gmailConsumeState($state);           // validates + consumes state
    $tokens = gmailExchangeCode($code, $verifier);   // code -> access + refresh token
    $profile = gmailProfileWithToken($tokens['access_token']);

    $pdo = getDB();
    $previous = gmailActiveConnection($pdo);
    $by = (string)($_SESSION['user_name'] ?? 'Registrar');

    gmailStoreConnection($pdo, $profile['emailAddress'], $tokens, $by);

    // A previously connected different account is replaced by this one.
    $note = ($previous && strcasecmp($previous['email'], $profile['emailAddress']) !== 0)
        ? ' (replaced ' . $previous['email'] . ')'
        : '';
    logActivity($pdo, 'Gmail Connected', 'Notifications', 'Gmail account ' . $profile['emailAddress'] . ' was connected' . $note . '.');

    gmailBack('gmail=connected');
} catch (GmailException $e) {
    $map = ['config' => 'config', 'reauthorize' => 'state', 'network' => 'network', 'api' => 'google', 'not_configured' => 'not_configured'];
    try {
        logActivity(getDB(), 'Gmail Error', 'Notifications', 'Connecting Gmail failed (' . $e->kind . ').');
    } catch (Throwable $ignored) {
    }
    gmailBack('gmail_error=' . ($map[$e->kind] ?? 'google'));
} catch (Throwable $e) {
    error_log('Gmail callback failed: ' . get_class($e));
    gmailBack('gmail_error=google');
}
