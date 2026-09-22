<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/gmail_client.php';

gmailRequireRegistrar();
gmailRequirePostSameOrigin();

// Reply to a message that is in the Inbox. The reply is sent into the same Gmail
// thread (threadId + In-Reply-To/References) and recorded in the Sent log.
//   POST reply_to_id = inbox_messages.id, body = reply text
try {
    $pdo = getDB();

    $replyToId = (int)($_POST['reply_to_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');

    if ($replyToId <= 0) {
        sendError('Choose the message you are replying to.');
    }
    if ($body === '' || mb_strlen($body) > 20000) {
        sendError('Please write a reply (up to 20,000 characters).');
    }

    $stmt = $pdo->prepare("SELECT * FROM inbox_messages WHERE id = ?");
    $stmt->execute([$replyToId]);
    $orig = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$orig) {
        sendError('That message no longer exists.', 404);
    }
    if (trim((string)$orig['sender_email']) === '') {
        sendError('This message has no sender email address to reply to.');
    }

    $conn = gmailActiveConnection($pdo);
    // A reply to a thread that belongs to another (previous) Gmail account can't stay in that thread.
    $sameAccount = $conn && strcasecmp((string)$orig['gmail_account'], $conn['email']) === 0;

    $subject = (string)$orig['subject'];
    if (!preg_match('/^\s*re:/i', $subject)) {
        $subject = 'Re: ' . $subject;
    }

    $res = gmailSendMessage(
        $pdo,
        $orig['sender_email'],
        $subject,
        $body,
        $sameAccount ? (string)$orig['gmail_thread_id'] : '',
        $sameAccount ? (string)$orig['rfc_message_id'] : ''
    );

    $pdo->prepare("
        INSERT INTO notifications
            (type, recipient_type, recipient_id, recipient_name, recipient_email, subject, message, status,
             gmail_account, gmail_message_id, gmail_thread_id)
        VALUES ('reply', 'individual', NULL, ?, ?, ?, ?, 'sent', ?, ?, ?)
    ")->execute([$orig['sender_name'], $orig['sender_email'], $subject, $body, $res['account'], $res['id'], $res['threadId']]);

    // No addresses, subject or reply text in History.
    logActivity($pdo, 'Email Reply Sent', 'Notifications', 'A reply was sent through Gmail.', $replyToId);

    sendJson(['success' => true, 'message' => 'Reply sent.', 'threadId' => $res['threadId']]);
} catch (GmailException $e) {
    try {
        if (!in_array($e->kind, ['not_connected', 'not_configured'], true)) {
            logActivity(getDB(), 'Gmail Error', 'Notifications', 'Sending a reply failed (' . $e->kind . ').');
        }
    } catch (Throwable $ignored) {
    }
    gmailFail($e);
} catch (Throwable $e) {
    error_log('Gmail reply failed: ' . get_class($e));
    sendError('Could not send the reply.', 500);
}
