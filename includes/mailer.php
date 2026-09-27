<?php
/*
 * Single entry point for sending app mail (password resets, staff notifications): Gmail
 * when it's connected and working, SMTP (config/smtp.local.php) when it isn't. Gmail
 * depends on a Google Cloud OAuth client that can be disabled/revoked outside this app's
 * control (see includes/gmail_client.php); SMTP only needs a mailbox password, so it's
 * kept as the fallback that keeps mail actually going out when that happens.
 */

require_once __DIR__ . '/gmail_client.php';
require_once __DIR__ . '/smtp_client.php';

/**
 * @return array{method:string,id?:string,threadId?:string,account?:string}
 * @throws GmailException|SmtpException when neither Gmail nor SMTP could send it.
 */
function sendAppMail(PDO $pdo, string $to, string $subject, string $body): array {
    $conn = gmailActiveConnection($pdo);
    if ($conn !== null && $conn['status'] === 'connected') {
        try {
            return ['method' => 'gmail'] + gmailSendMessage($pdo, $to, $subject, $body);
        } catch (GmailException $e) {
            if (!smtpIsConfigured()) {
                throw $e;
            }
            // Fall through to SMTP below.
        }
    }

    smtpSendMessage($to, $subject, $body);
    return ['method' => 'smtp', 'account' => smtpConfig()['username']];
}

function appMailIsConfigured(PDO $pdo): bool {
    $conn = gmailActiveConnection($pdo);
    return ($conn !== null && $conn['status'] === 'connected') || smtpIsConfigured();
}
