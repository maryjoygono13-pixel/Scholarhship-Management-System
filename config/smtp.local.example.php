<?php
/*
 * SMTP settings used to SEND mail (password resets, staff notifications) when Gmail's
 * OAuth connection isn't available. This does not read/sync an inbox — it only sends.
 *
 * Copy this file to  config/smtp.local.php  and fill it in.
 * smtp.local.php is git-ignored: it holds a mailbox password, so never commit it.
 *
 * For a Gmail account: turn on 2-Step Verification, then create an "App Password" at
 * https://myaccount.google.com/apppasswords (choose "Mail" / "Other") — use that
 * 16-character app password below, NOT the account's normal login password.
 *
 * Environment variables (SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_NAME)
 * work too and are used when a key is missing here.
 */
return [
    'host' => 'smtp.gmail.com',
    'port' => 465, // 465 = implicit TLS (used here); 587 would need STARTTLS instead.
    'username' => 'your-account@gmail.com',
    'password' => 'PASTE_16_CHARACTER_APP_PASSWORD',
    // The "From" name shown to recipients; the address is always the username above.
    'from_name' => 'Scholarship Portal',
];
