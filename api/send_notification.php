<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/recipients.php';
require_once __DIR__ . '/../includes/gmail_client.php';

// Sends the "New notification" as real email through the connected Gmail account
// and records each recipient in the Sent log with the real result.

const NOTIFICATION_MAX_RECIPIENTS = 100;

// Real email goes out from here now, so it needs a signed-in registrar and a
// request from this site.
gmailRequireRegistrar();
gmailRequirePostSameOrigin();

try {
    $pdo = getDB();

    $type = trim($_POST['type'] ?? 'general');
    // The page sends recipientMode / applicantId; older callers used mode / individual.
    $mode = trim($_POST['recipientMode'] ?? $_POST['mode'] ?? 'segment');
    $segment = trim($_POST['segment'] ?? 'all');
    $individualId = trim($_POST['applicantId'] ?? $_POST['individual'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        sendError('Subject and message are required.');
    }
    if (mb_strlen($subject) > 200 || mb_strlen($message) > 20000) {
        sendError('The subject or message is too long.');
    }

    // Nothing is recorded as "sent" unless Gmail is actually there to send it.
    if (!gmailIsConfigured()) {
        gmailFail(new GmailException('not_configured', 'Gmail is not set up yet. Add the Google client ID and secret to config/gmail.local.php, then connect Gmail on the Notifications page.'));
    }
    $conn = gmailActiveConnection($pdo);
    if ($conn === null || $conn['status'] !== 'connected') {
        gmailFail(new GmailException(
            $conn === null ? 'not_connected' : 'reauthorize',
            $conn === null
                ? 'Gmail is not connected. Use "Connect Gmail" on the Notifications page first.'
                : 'The Gmail authorization expired. Please reconnect Gmail on the Notifications page.'
        ));
    }

    $recipients = resolveRecipients($pdo, $mode === 'individual' ? 'individual' : 'segment', $segment, $individualId);
    if (empty($recipients)) {
        sendError('There are no recipients for this selection.');
    }
    if (count($recipients) > NOTIFICATION_MAX_RECIPIENTS) {
        sendError('That is ' . count($recipients) . ' recipients. Please send to at most ' . NOTIFICATION_MAX_RECIPIENTS . ' people at a time.');
    }

    @set_time_limit(180);

    $insert = $pdo->prepare("
        INSERT INTO notifications
            (type, recipient_type, recipient_id, recipient_name, recipient_email, subject, message, deadline, status,
             gmail_account, gmail_message_id, gmail_thread_id, error_message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $sent = 0;
    $failed = 0;
    $fatal = null;

    foreach ($recipients as $r) {
        $first = explode(' ', trim($r['name']))[0] ?? '';
        $placeholders = ['{{first_name}}', '{{last_name}}', '{{student_id}}', '{{deadline}}'];
        $values = [$first, trim(substr(trim($r['name']), strlen($first))), $r['studentId'], $deadline ?: 'the due date'];
        $body = str_replace($placeholders, $values, $message);
        $personalSubject = str_replace($placeholders, $values, $subject);

        $result = null;
        $error = '';

        if ($fatal !== null) {
            $error = 'Not sent: ' . $fatal;
        } elseif (!filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'No valid email address on file.';
        } else {
            try {
                $result = gmailSendMessage($pdo, $r['email'], $personalSubject, $body);
            } catch (GmailException $e) {
                $error = $e->getMessage();
                // If Gmail itself is unusable, don't hammer it for every remaining recipient.
                if (in_array($e->kind, ['reauthorize', 'not_connected', 'network'], true)) {
                    $fatal = $error;
                }
            }
        }

        $insert->execute([
            $type, $mode, $r['id'], $r['name'], $r['email'], $personalSubject, $body, $deadline,
            $result ? 'sent' : 'failed',
            $result['account'] ?? $conn['email'], $result['id'] ?? null, $result['threadId'] ?? null, mb_substr($error, 0, 250),
        ]);
        $result ? $sent++ : $failed++;
    }

    // Counts only: no recipients' addresses, subject or message text in History.
    logActivity($pdo, 'Email Sent', 'Notifications', $sent . ' email' . ($sent === 1 ? '' : 's') . ' sent through Gmail' . ($failed ? ' (' . $failed . ' failed)' : '') . '.');

    if ($sent === 0) {
        http_response_code(502);
        sendJson([
            'success' => false,
            'sent' => 0,
            'failed' => $failed,
            'total' => $failed,
            'message' => $fatal ?? 'No email could be sent. See the Sent log for the reason.',
        ], 502);
    }

    sendJson([
        'success' => true,
        'sent' => $sent,
        'failed' => $failed,
        'total' => $sent + $failed,
        'message' => "Sent $sent email" . ($sent === 1 ? '' : 's') . ' through Gmail.' . ($failed ? " $failed could not be sent — see the Sent log." : ''),
    ]);
} catch (GmailException $e) {
    gmailFail($e);
} catch (Exception $e) {
    error_log('send_notification failed: ' . get_class($e));
    sendError('Something went wrong while sending. Please try again.', 500);
}
