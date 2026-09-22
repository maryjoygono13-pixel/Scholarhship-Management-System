<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/gmail_client.php';

gmailRequireRegistrar();
gmailRequirePostSameOrigin();

// Revokes access at Google (best effort) and deletes the stored tokens. Messages
// already downloaded stay in the inbox, labelled with the account they came from.
try {
    $pdo = getDB();
    $email = gmailDisconnect($pdo);

    if ($email === null) {
        sendJson(['success' => true, 'message' => 'Gmail was not connected.']);
    }

    logActivity($pdo, 'Gmail Disconnected', 'Notifications', 'Gmail account ' . $email . ' was disconnected.');
    sendJson(['success' => true, 'message' => 'Gmail account disconnected.']);
} catch (Throwable $e) {
    sendError('Could not disconnect Gmail.', 500);
}
