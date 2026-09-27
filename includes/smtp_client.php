<?php
/*
 * A minimal SMTP client for SENDING mail only (password resets, staff notifications) —
 * used as a fallback when the Gmail API connection (includes/gmail_client.php) isn't
 * available, since that needs a Google Cloud OAuth client that can be disabled/revoked
 * outside this app's control. This never reads an inbox; it only opens a TLS socket to
 * an SMTP server, authenticates, and hands off one message.
 *
 * No password is ever logged; SmtpException messages are safe to show a registrar.
 */

require_once __DIR__ . '/../config/config.php';

class SmtpException extends Exception {}

function smtpConfig(): array {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int)(getenv('SMTP_PORT') ?: 465),
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'Scholarship Portal',
    ];

    $local = __DIR__ . '/../config/smtp.local.php';
    if (is_file($local)) {
        $override = require $local;
        if (is_array($override)) {
            $cfg = array_merge($cfg, $override);
        }
    }

    return $cfg;
}

function smtpIsConfigured(): bool {
    $c = smtpConfig();
    return trim((string)$c['username']) !== '' && trim((string)$c['password']) !== '';
}

/** Removes CR/LF so a value can never inject extra mail headers. */
function smtpHeaderSafe(string $v): string {
    return trim(preg_replace('/[\r\n]+/', ' ', $v));
}

/** Reads one full SMTP response (handling multi-line "250-...\r\n250 ..." replies). */
function smtpReadResponse($sock): string {
    $data = '';
    while (($line = fgets($sock, 515)) !== false) {
        $data .= $line;
        // A reply's last line has a space (not a dash) after the 3-digit code.
        if (preg_match('/^\d{3} /', $line)) {
            break;
        }
    }
    if ($data === '') {
        throw new SmtpException('The mail server closed the connection unexpectedly.');
    }
    return $data;
}

function smtpExpect($sock, array $okCodes, string $step): string {
    $resp = smtpReadResponse($sock);
    $code = (int)substr($resp, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new SmtpException("Mail server rejected $step: " . trim($resp));
    }
    return $resp;
}

function smtpCommand($sock, string $command): void {
    if (fwrite($sock, $command . "\r\n") === false) {
        throw new SmtpException('Could not send data to the mail server.');
    }
}

/**
 * Sends one plain-text email. Throws SmtpException with a message safe to show the user.
 */
function smtpSendMessage(string $to, string $subject, string $body): void {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new SmtpException('The recipient email address is not valid.');
    }
    $cfg = smtpConfig();
    if (!smtpIsConfigured()) {
        throw new SmtpException('Email sending is not set up yet. Add SMTP credentials to config/smtp.local.php.');
    }

    $host = (string)$cfg['host'];
    $port = (int)$cfg['port'];
    $username = (string)$cfg['username'];
    $password = (string)$cfg['password'];
    $fromName = (string)$cfg['from_name'];

    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $transport = $port === 465 ? 'ssl://' : 'tcp://';
    $sock = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if ($sock === false) {
        throw new SmtpException('Could not reach the mail server (' . $errstr . ').');
    }
    stream_set_timeout($sock, 15);

    try {
        smtpExpect($sock, [220], 'connection');

        smtpCommand($sock, 'EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost'));
        smtpExpect($sock, [250], 'EHLO');

        // Port 587 would need STARTTLS negotiated here; port 465 (used by default) is
        // already encrypted from the moment the socket connects, so this is skipped.

        smtpCommand($sock, 'AUTH LOGIN');
        smtpExpect($sock, [334], 'AUTH LOGIN');
        smtpCommand($sock, base64_encode($username));
        smtpExpect($sock, [334], 'username');
        smtpCommand($sock, base64_encode($password));
        try {
            smtpExpect($sock, [235], 'password');
        } catch (SmtpException $e) {
            throw new SmtpException('The mail server rejected the login. If this is Gmail, make sure the password is an App Password (not the account password) and that 2-Step Verification is on.');
        }

        smtpCommand($sock, 'MAIL FROM:<' . $username . '>');
        smtpExpect($sock, [250], 'MAIL FROM');
        smtpCommand($sock, 'RCPT TO:<' . $to . '>');
        smtpExpect($sock, [250, 251], 'RCPT TO');

        smtpCommand($sock, 'DATA');
        smtpExpect($sock, [354], 'DATA');

        $headers = [
            'From: ' . smtpHeaderSafe($fromName) . ' <' . $username . '>',
            'To: ' . smtpHeaderSafe($to),
            'Subject: =?UTF-8?B?' . base64_encode(smtpHeaderSafe($subject)) . '?=',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        // Dot-stuffing: a line that is only "." would otherwise be read as the end-of-DATA marker.
        $encodedBody = chunk_split(base64_encode($body));
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;
        $payload = preg_replace('/^\./m', '..', $payload);

        smtpCommand($sock, $payload . "\r\n.");
        smtpExpect($sock, [250], 'message body');

        smtpCommand($sock, 'QUIT');
    } finally {
        fclose($sock);
    }
}
