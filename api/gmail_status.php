<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/gmail_client.php';

header('Cache-Control: no-store');
gmailRequireRegistrar();

// Everything the frontend may know about Gmail. No tokens, no client secret.
try {
    sendJson(['success' => true] + gmailStatus(getDB()));
} catch (Throwable $e) {
    sendError('Could not read the Gmail status.', 500);
}
