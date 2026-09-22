<?php
/*
 * Gmail (Google OAuth 2.0) settings for the Notifications / Messaging page.
 *
 * Copy this file to  config/gmail.local.php  and fill it in.
 * gmail.local.php is git-ignored: it holds the OAuth client secret, so never commit it.
 *
 * Environment variables (GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_REDIRECT_URI,
 * GMAIL_ENV) work too and are used when a key is missing here.
 *
 * The Gmail *account* (temporary test account now, official registrar account later)
 * is NOT configured here. It is chosen by pressing "Connect Gmail" on the Notifications
 * page, and can be replaced any time with Disconnect -> Connect Gmail.
 * These values identify the SMS *application* to Google, not a mailbox.
 */
return [
    // From Google Cloud Console > APIs & Services > Credentials > OAuth 2.0 Client ID (Web application)
    'client_id'     => 'PASTE_CLIENT_ID.apps.googleusercontent.com',
    'client_secret' => 'PASTE_CLIENT_SECRET',

    // Must EXACTLY match an "Authorized redirect URI" in Google Cloud.
    // Local XAMPP (this project's callback):
    'redirect_uri'  => 'http://localhost/sms/api/gmail_callback.php',
    // Production example (https is required):
    // 'redirect_uri' => 'https://sms.your-school.edu.ph/api/gmail_callback.php',

    // 'development' while testing locally, 'production' when live (enforces https).
    'environment'   => 'development',

    // Where the token-encryption key is kept. Should be OUTSIDE the web folder (htdocs).
    // Default: C:\xampp\sms_secrets
    // 'secrets_dir' => 'C:\\xampp\\sms_secrets',
];
