<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/gmail_client.php';

header('Cache-Control: no-store');
gmailRequireRegistrar();
gmailRequirePostSameOrigin();

// Pulls new mail from the connected Gmail inbox into the existing Inbox.
try {
    $pdo = getDB();
    $new = gmailSyncInbox($pdo);

    // Only log when something arrived, so the automatic refresh doesn't flood History.
    // Counts only: no senders, subjects or message text.
    if ($new > 0) {
        logActivity($pdo, 'Gmail Synchronized', 'Notifications', $new . ' new email' . ($new === 1 ? '' : 's') . ' synchronized from Gmail.');
    }

    sendJson(['success' => true, 'new' => $new, 'lastSyncAt' => gmailStatus($pdo)['lastSyncAt']]);
} catch (GmailException $e) {
    try {
        if ($e->kind !== 'not_connected' && $e->kind !== 'not_configured') {
            logActivity(getDB(), 'Gmail Error', 'Notifications', 'Gmail synchronization failed (' . $e->kind . ').');
        }
    } catch (Throwable $ignored) {
    }
    gmailFail($e);
} catch (Throwable $e) {
    error_log('Gmail sync failed: ' . get_class($e));
    sendError('Could not synchronize Gmail.', 500);
}
