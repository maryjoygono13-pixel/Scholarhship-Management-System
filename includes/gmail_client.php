<?php
/*
 * Gmail integration for the Notifications (Messaging) page.
 *
 * Gmail is only the mailbox behind the existing messaging feature — it has
 * nothing to do with signing in to the SMS. The connected account is a row in
 * `gmail_connections`, never a constant, so it can be disconnected and replaced
 * (temporary test account -> official registrar account) without touching the
 * Messaging page.
 *
 * Security notes
 *  - No Gmail password is ever handled: OAuth 2.0 (authorization code + PKCE).
 *  - Access/refresh tokens are AES-256-GCM encrypted in the database. The key
 *    lives in a file OUTSIDE the web root (see 'secrets_dir'), so a copy of the
 *    SQLite file alone is not enough to use the tokens.
 *  - Nothing in this file returns tokens or the client secret to callers that
 *    talk to the browser; gmailStatus() is the only thing the frontend sees.
 *  - Tokens and mail bodies are never written to logs.
 */

require_once __DIR__ . '/../config/config.php';

const GMAIL_SCOPE_READ = 'https://www.googleapis.com/auth/gmail.readonly';
const GMAIL_SCOPE_SEND = 'https://www.googleapis.com/auth/gmail.send';

/** Error with a message that is safe to show to the registrar. */
class GmailException extends Exception {
    /** not_configured | not_connected | reauthorize | network | api | config */
    public string $kind;

    public function __construct(string $kind, string $userMessage, int $httpStatus = 0) {
        parent::__construct($userMessage, $httpStatus);
        $this->kind = $kind;
    }
}

/* =========================================================
   CONFIGURATION
   Defaults + config/gmail.local.php (or environment variables).
   Development and production differ only in that local file.
========================================================= */

function gmailConfig(): array {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [
        'client_id' => getenv('GMAIL_CLIENT_ID') ?: '',
        'client_secret' => getenv('GMAIL_CLIENT_SECRET') ?: '',
        // Must match an "Authorized redirect URI" in Google Cloud exactly.
        // In production set it explicitly rather than relying on the Host header.
        'redirect_uri' => (getenv('GMAIL_REDIRECT_URI') ?: '') !== '' ? getenv('GMAIL_REDIRECT_URI') : SITE_URL . '/api/gmail_callback.php',
        // 'development' | 'production' (production requires https)
        'environment' => getenv('GMAIL_ENV') ?: 'development',
        // Folder for the token-encryption key. Keep it outside htdocs.
        'secrets_dir' => dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sms_secrets',
        'scopes' => [GMAIL_SCOPE_READ, GMAIL_SCOPE_SEND],
        'sync_max' => 25,
        'ca_bundle' => '',
        // Google endpoints (only overridden to point at a local test double).
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'revoke_url' => 'https://oauth2.googleapis.com/revoke',
        'api_base' => 'https://gmail.googleapis.com/gmail/v1',
    ];

    $local = __DIR__ . '/../config/gmail.local.php';
    if (is_file($local)) {
        $override = require $local;
        if (is_array($override)) {
            $cfg = array_merge($cfg, $override);
        }
    }

    return $cfg;
}

function gmailIsConfigured(): bool {
    $c = gmailConfig();
    return trim((string)$c['client_id']) !== '' && trim((string)$c['client_secret']) !== '';
}

/* =========================================================
   TOKEN ENCRYPTION (AES-256-GCM)
========================================================= */

function gmailKey(): string {
    static $key = null;
    if ($key !== null) {
        return $key;
    }

    $dir = gmailConfig()['secrets_dir'];
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new GmailException('config', 'The server could not create its secrets folder for Gmail tokens.');
    }

    $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'gmail.key';
    if (!is_file($file)) {
        if (@file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX) === false) {
            throw new GmailException('config', 'The server could not write its Gmail encryption key.');
        }
        @chmod($file, 0600);
    }

    $hex = trim((string)@file_get_contents($file));
    if (strlen($hex) !== 64 || !ctype_xdigit($hex)) {
        throw new GmailException('config', 'The Gmail encryption key file is invalid.');
    }

    return $key = hex2bin($hex);
}

function gmailEncrypt(string $plain): string {
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', gmailKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new GmailException('config', 'Could not encrypt the Gmail token.');
    }
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function gmailDecrypt(?string $stored): ?string {
    if ($stored === null || $stored === '' || strpos($stored, 'v1:') !== 0) {
        return null;
    }
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', gmailKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? null : $plain;
}

/* =========================================================
   HTTP
========================================================= */

/**
 * @return array{0:int,1:?array,2:string} [http status, decoded JSON body, raw body]
 */
function gmailHttp(string $method, string $url, array $headers = [], $body = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    $ca = gmailConfig()['ca_bundle'];
    if ($ca !== '') {
        $opts[CURLOPT_CAINFO] = $ca;
    }
    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $errno !== 0) {
        throw new GmailException('network', 'Could not reach Google. Check the internet connection and try again.');
    }

    $json = json_decode((string)$raw, true);
    return [$status, is_array($json) ? $json : null, (string)$raw];
}

/* =========================================================
   CONNECTION STORAGE
========================================================= */

/** The connection currently in use (connected, or needing re-authorization). */
function gmailActiveConnection(PDO $pdo): ?array {
    $stmt = $pdo->query("SELECT * FROM gmail_connections WHERE status IN ('connected', 'needs_reauth') ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Safe view for the frontend: never contains tokens or secrets. */
function gmailStatus(PDO $pdo): array {
    $cfg = gmailConfig();
    $conn = gmailActiveConnection($pdo);

    return [
        'configured' => gmailIsConfigured(),
        'environment' => $cfg['environment'],
        'redirectUri' => $cfg['redirect_uri'],
        'connected' => $conn !== null && $conn['status'] === 'connected',
        'needsReauth' => $conn !== null && $conn['status'] === 'needs_reauth',
        'email' => $conn['email'] ?? null,
        'connectedAt' => $conn['connected_at'] ?? null,
        'lastSyncAt' => $conn['last_sync_at'] ?? null,
        'lastError' => $conn['last_error'] ?? '',
    ];
}

function gmailMarkError(PDO $pdo, int $id, string $message): void {
    $pdo->prepare("UPDATE gmail_connections SET last_error = ? WHERE id = ?")->execute([mb_substr($message, 0, 250), $id]);
}

/** Store a new connection; any previous account is disconnected first. */
function gmailStoreConnection(PDO $pdo, string $email, array $tokens, string $by): int {
    $pdo->prepare("UPDATE gmail_connections SET status = 'disconnected', access_token_enc = NULL, refresh_token_enc = NULL, disconnected_at = CURRENT_TIMESTAMP WHERE status != 'disconnected'")->execute();

    $stmt = $pdo->prepare("
        INSERT INTO gmail_connections (email, status, access_token_enc, refresh_token_enc, token_expires_at, scope, connected_by)
        VALUES (?, 'connected', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $email,
        gmailEncrypt($tokens['access_token']),
        gmailEncrypt($tokens['refresh_token']),
        time() + (int)($tokens['expires_in'] ?? 3600),
        (string)($tokens['scope'] ?? ''),
        $by,
    ]);
    return (int)$pdo->lastInsertId();
}

/* =========================================================
   OAUTH 2.0 (authorization code + PKCE)
========================================================= */

function gmailBase64Url(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/** Creates state + PKCE verifier in the session and returns Google's consent URL. */
function gmailBeginAuth(): string {
    if (!gmailIsConfigured()) {
        throw new GmailException('not_configured', 'Gmail is not set up yet. Add the Google client ID and secret to config/gmail.local.php.');
    }
    $cfg = gmailConfig();
    if ($cfg['environment'] === 'production' && stripos($cfg['redirect_uri'], 'https://') !== 0) {
        throw new GmailException('config', 'In production the Gmail redirect URI must use https.');
    }

    $state = bin2hex(random_bytes(24));
    $verifier = gmailBase64Url(random_bytes(48));

    session_start();
    $_SESSION['gmail_oauth'] = ['state' => $state, 'verifier' => $verifier, 'expires' => time() + 600];
    session_write_close();

    return $cfg['auth_url'] . '?' . http_build_query([
        'client_id' => $cfg['client_id'],
        'redirect_uri' => $cfg['redirect_uri'],
        'response_type' => 'code',
        'scope' => implode(' ', $cfg['scopes']),
        'state' => $state,
        'access_type' => 'offline',          // ask for a refresh token
        'prompt' => 'consent select_account', // always issue a refresh token, let the user pick the account
        'include_granted_scopes' => 'false',
        'code_challenge' => gmailBase64Url(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
    ], '', '&', PHP_QUERY_RFC3986);
}

/** Validates the state from the callback and consumes it (single use). */
function gmailConsumeState(string $state): string {
    $data = $_SESSION['gmail_oauth'] ?? null;

    session_start();
    unset($_SESSION['gmail_oauth']);
    session_write_close();

    if (!is_array($data) || $state === '' || !hash_equals((string)$data['state'], $state) || (int)$data['expires'] < time()) {
        throw new GmailException('reauthorize', 'The Gmail authorization request expired or was not recognised. Please try connecting again.');
    }
    return (string)$data['verifier'];
}

function gmailTokenRequest(array $params): array {
    $cfg = gmailConfig();
    $params['client_id'] = $cfg['client_id'];
    $params['client_secret'] = $cfg['client_secret'];

    [$status, $json] = gmailHttp('POST', $cfg['token_url'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query($params));
    return [$status, $json ?? []];
}

function gmailExchangeCode(string $code, string $verifier): array {
    [$status, $json] = gmailTokenRequest([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => gmailConfig()['redirect_uri'],
        'code_verifier' => $verifier,
    ]);

    if ($status !== 200 || empty($json['access_token'])) {
        $err = (string)($json['error'] ?? '');
        if ($err === 'invalid_client' || $err === 'unauthorized_client') {
            throw new GmailException('config', 'Google rejected the client ID/secret. Check config/gmail.local.php.');
        }
        throw new GmailException('reauthorize', 'Google did not accept the authorization code. Please try connecting again.');
    }
    if (empty($json['refresh_token'])) {
        throw new GmailException('reauthorize', 'Google did not return a refresh token. Remove this app from the Google account\'s connected apps and connect again.');
    }
    return $json;
}

/** Token for one Gmail call. Refreshes automatically when close to expiry. */
function gmailAccessToken(PDO $pdo, array $conn, bool $force = false): string {
    if ($conn['status'] !== 'connected') {
        throw new GmailException('reauthorize', 'The Gmail authorization is no longer valid. Please reconnect Gmail.');
    }

    $access = gmailDecrypt($conn['access_token_enc']);
    if (!$force && $access !== null && (int)$conn['token_expires_at'] > time() + 60) {
        return $access;
    }

    $refresh = gmailDecrypt($conn['refresh_token_enc']);
    if ($refresh === null) {
        gmailMarkNeedsReauth($pdo, (int)$conn['id'], 'Stored Gmail authorization is unreadable.');
        throw new GmailException('reauthorize', 'The Gmail authorization is no longer valid. Please reconnect Gmail.');
    }

    [$status, $json] = gmailTokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]);

    if ($status === 200 && !empty($json['access_token'])) {
        $pdo->prepare("UPDATE gmail_connections SET access_token_enc = ?, token_expires_at = ?, refresh_token_enc = COALESCE(?, refresh_token_enc), last_error = '' WHERE id = ?")
            ->execute([
                gmailEncrypt($json['access_token']),
                time() + (int)($json['expires_in'] ?? 3600),
                !empty($json['refresh_token']) ? gmailEncrypt($json['refresh_token']) : null,
                $conn['id'],
            ]);
        return $json['access_token'];
    }

    // Revoked / expired refresh token: the account must be re-authorized.
    if (in_array($json['error'] ?? '', ['invalid_grant', 'invalid_client', 'unauthorized_client'], true)) {
        gmailMarkNeedsReauth($pdo, (int)$conn['id'], 'Google reports the authorization was revoked or expired.');
        throw new GmailException('reauthorize', 'Google says the Gmail authorization was revoked or has expired. Please reconnect Gmail.');
    }

    throw new GmailException('api', 'Could not refresh the Gmail access. Please try again in a moment.', $status);
}

function gmailMarkNeedsReauth(PDO $pdo, int $id, string $why): void {
    $pdo->prepare("UPDATE gmail_connections SET status = 'needs_reauth', access_token_enc = NULL, refresh_token_enc = NULL, token_expires_at = NULL, last_error = ? WHERE id = ?")
        ->execute([$why, $id]);
}

/* =========================================================
   GMAIL API
========================================================= */

/** Calls the Gmail API as the connected account. */
function gmailApi(PDO $pdo, string $method, string $path, array $query = [], ?array $jsonBody = null): array {
    if (!gmailIsConfigured()) {
        throw new GmailException('not_configured', 'Gmail is not set up yet. Add the Google client ID and secret to config/gmail.local.php.');
    }
    $conn = gmailActiveConnection($pdo);
    if ($conn === null) {
        throw new GmailException('not_connected', 'Gmail is not connected. Use "Connect Gmail" first.');
    }

    $url = rtrim(gmailConfig()['api_base'], '/') . $path . ($query ? '?' . http_build_query($query) : '');

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $token = gmailAccessToken($pdo, $conn, $attempt > 0);
        $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
        $body = null;
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($jsonBody);
        }

        [$status, $json] = gmailHttp($method, $url, $headers, $body);

        if ($status === 401 && $attempt === 0) {
            $conn = gmailActiveConnection($pdo) ?? $conn;
            continue; // token rejected: force a refresh and retry once
        }
        if ($status === 401) {
            gmailMarkNeedsReauth($pdo, (int)$conn['id'], 'Gmail rejected the access token.');
            throw new GmailException('reauthorize', 'Gmail rejected the saved authorization. Please reconnect Gmail.', 401);
        }
        if ($status >= 200 && $status < 300) {
            return $json ?? [];
        }

        $reason = (string)($json['error']['message'] ?? 'Unexpected response');
        if ($status === 403) {
            throw new GmailException('api', 'Gmail refused the request (' . mb_substr($reason, 0, 160) . '). Check that the Gmail API is enabled and the permissions were granted.', 403);
        }
        if ($status === 429 || $status >= 500) {
            throw new GmailException('api', 'Gmail is temporarily unavailable. Please try again shortly.', $status);
        }
        throw new GmailException('api', 'Gmail returned an error (' . mb_substr($reason, 0, 160) . ').', $status);
    }

    throw new GmailException('api', 'Gmail could not be reached.');
}

/** Profile of a token that is not stored yet (used once, right after authorization). */
function gmailProfileWithToken(string $accessToken): array {
    $url = rtrim(gmailConfig()['api_base'], '/') . '/users/me/profile';
    [$status, $json] = gmailHttp('GET', $url, ['Authorization: Bearer ' . $accessToken, 'Accept: application/json']);
    if ($status !== 200 || empty($json['emailAddress'])) {
        throw new GmailException('api', 'Connected to Google, but the Gmail account details could not be read. Make sure the Gmail API is enabled for the project.', $status);
    }
    return $json;
}

/* ---------- message parsing ---------- */

function gmailHeader(array $payload, string $name): string {
    foreach ($payload['headers'] ?? [] as $h) {
        if (strcasecmp($h['name'] ?? '', $name) === 0) {
            return trim((string)$h['value']);
        }
    }
    return '';
}

/** "Maria Santos <maria@x.com>" -> [name, email] */
function gmailParseAddress(string $raw): array {
    if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $raw, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    $raw = trim($raw);
    return ['', $raw];
}

function gmailDecodeBody(string $data): string {
    return (string)base64_decode(strtr($data, '-_', '+/'));
}

/** Prefers text/plain; falls back to a stripped text/html part. */
function gmailExtractText(array $payload): string {
    $plain = $html = '';
    $walk = function (array $part) use (&$walk, &$plain, &$html) {
        $mime = strtolower($part['mimeType'] ?? '');
        $data = $part['body']['data'] ?? '';
        if ($data !== '' && empty($part['filename'])) {
            if ($mime === 'text/plain' && $plain === '') $plain = gmailDecodeBody($data);
            if ($mime === 'text/html' && $html === '') $html = gmailDecodeBody($data);
        }
        foreach ($part['parts'] ?? [] as $child) {
            $walk($child);
        }
    };
    $walk($payload);

    if ($plain !== '') {
        return trim($plain);
    }
    if ($html !== '') {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>|</p>|</div>|</tr>|</li>#i', "\n", $html);
        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
    return '';
}

function gmailParseMessage(array $msg): array {
    $payload = $msg['payload'] ?? [];
    [$name, $email] = gmailParseAddress(gmailHeader($payload, 'From'));
    $subject = gmailHeader($payload, 'Subject');
    $ts = isset($msg['internalDate']) ? (int)floor(((float)$msg['internalDate']) / 1000) : time();

    return [
        'id' => (string)($msg['id'] ?? ''),
        'threadId' => (string)($msg['threadId'] ?? ''),
        'fromName' => $name,
        'fromEmail' => $email,
        'to' => gmailHeader($payload, 'To'),
        'subject' => $subject !== '' ? $subject : '(No subject)',
        'rfcMessageId' => gmailHeader($payload, 'Message-ID') ?: gmailHeader($payload, 'Message-Id'),
        'body' => mb_substr(gmailExtractText($payload) ?: (string)($msg['snippet'] ?? ''), 0, 20000),
        'receivedAt' => gmdate('Y-m-d H:i:s', $ts),
    ];
}

/* ---------- sync ---------- */

/** Pulls the latest inbox messages into inbox_messages. Returns how many were new. */
function gmailSyncInbox(PDO $pdo): int {
    $conn = gmailActiveConnection($pdo);
    if ($conn === null) {
        throw new GmailException('not_connected', 'Gmail is not connected. Use "Connect Gmail" first.');
    }
    $account = $conn['email'];

    try {
        $list = gmailApi($pdo, 'GET', '/users/me/messages', ['labelIds' => 'INBOX', 'maxResults' => (int)gmailConfig()['sync_max']]);

        $exists = $pdo->prepare("SELECT 1 FROM inbox_messages WHERE gmail_account = ? AND gmail_message_id = ?");
        $findApplicant = $pdo->prepare("SELECT student_id FROM applicants WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $insert = $pdo->prepare("
            INSERT INTO inbox_messages
                (sender_name, sender_email, student_id, subject, message, source, is_read, received_at,
                 gmail_account, gmail_message_id, gmail_thread_id, rfc_message_id, to_email)
            VALUES (?, ?, ?, ?, ?, 'gmail', 0, ?, ?, ?, ?, ?, ?)
        ");

        $new = 0;
        foreach ($list['messages'] ?? [] as $ref) {
            $exists->execute([$account, $ref['id']]);
            if ($exists->fetchColumn()) {
                continue;
            }

            $m = gmailParseMessage(gmailApi($pdo, 'GET', '/users/me/messages/' . rawurlencode($ref['id']), ['format' => 'full']));
            if ($m['id'] === '') {
                continue;
            }
            $findApplicant->execute([$m['fromEmail']]);
            $studentId = (string)($findApplicant->fetchColumn() ?: '');

            $insert->execute([
                $m['fromName'], $m['fromEmail'], $studentId, $m['subject'], $m['body'], $m['receivedAt'],
                $account, $m['id'], $m['threadId'], $m['rfcMessageId'], $m['to'],
            ]);
            $new++;
        }

        $pdo->prepare("UPDATE gmail_connections SET last_sync_at = CURRENT_TIMESTAMP, last_error = '' WHERE id = ?")->execute([$conn['id']]);
        return $new;
    } catch (GmailException $e) {
        // Reauthorize already recorded its own state; keep other errors visible in the status bar.
        if ($e->kind !== 'reauthorize') {
            gmailMarkError($pdo, (int)$conn['id'], $e->getMessage());
        }
        throw $e;
    }
}

/* ---------- send ---------- */

/** Removes CR/LF so a value can never inject extra mail headers. */
function gmailHeaderSafe(string $v): string {
    return trim(preg_replace('/[\r\n]+/', ' ', $v));
}

function gmailBuildMime(string $fromEmail, string $to, string $subject, string $body, string $inReplyTo = '', string $references = ''): string {
    $to = gmailHeaderSafe($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new GmailException('api', 'The recipient email address is not valid.');
    }

    $headers = [
        'From: ' . gmailHeaderSafe($fromEmail),
        'To: ' . $to,
        'Subject: =?UTF-8?B?' . base64_encode(gmailHeaderSafe($subject)) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($inReplyTo !== '') {
        $headers[] = 'In-Reply-To: ' . gmailHeaderSafe($inReplyTo);
        $headers[] = 'References: ' . gmailHeaderSafe(trim($references . ' ' . $inReplyTo));
    }

    return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
}

/**
 * Sends one message. When $threadId is given (a reply) Gmail keeps it in the same
 * conversation; the In-Reply-To/References headers make other mail clients do the same.
 *
 * @return array{id:string,threadId:string,account:string}
 */
function gmailSendMessage(PDO $pdo, string $to, string $subject, string $body, string $threadId = '', string $inReplyTo = ''): array {
    $conn = gmailActiveConnection($pdo);
    if ($conn === null) {
        throw new GmailException('not_connected', 'Gmail is not connected. Use "Connect Gmail" first.');
    }

    $mime = gmailBuildMime($conn['email'], $to, $subject, $body, $inReplyTo);
    $payload = ['raw' => gmailBase64Url($mime)];
    if ($threadId !== '') {
        $payload['threadId'] = $threadId;
    }

    $res = gmailApi($pdo, 'POST', '/users/me/messages/send', [], $payload);
    return ['id' => (string)($res['id'] ?? ''), 'threadId' => (string)($res['threadId'] ?? $threadId), 'account' => $conn['email']];
}

/* ---------- disconnect ---------- */

/** Revokes the grant at Google (best effort) and wipes the stored tokens. */
function gmailDisconnect(PDO $pdo): ?string {
    $conn = gmailActiveConnection($pdo);
    if ($conn === null) {
        return null;
    }

    $token = gmailDecrypt($conn['refresh_token_enc']) ?? gmailDecrypt($conn['access_token_enc']);
    if ($token !== null && gmailIsConfigured()) {
        try {
            gmailHttp('POST', gmailConfig()['revoke_url'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['token' => $token]));
        } catch (GmailException $e) {
            // Offline or already revoked: we still remove our copy below.
        }
    }

    $pdo->prepare("UPDATE gmail_connections SET status = 'disconnected', access_token_enc = NULL, refresh_token_enc = NULL, token_expires_at = NULL, disconnected_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$conn['id']]);
    return $conn['email'];
}

/* =========================================================
   REQUEST GUARDS FOR THE JSON ENDPOINTS
========================================================= */

/** Registrar session required (JSON 401 instead of a redirect). */
function gmailRequireRegistrar(): void {
    if (empty($_SESSION['user_logged_in']) || strtolower((string)($_SESSION['user_role'] ?? '')) !== 'registrar') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Please sign in again.']);
        exit();
    }
}

/** State-changing requests must be POST and come from this site. */
function gmailRequirePostSameOrigin(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit();
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        $ownHost = parse_url(SITE_URL, PHP_URL_HOST);
        $ownPort = parse_url(SITE_URL, PHP_URL_PORT);
        if (strcasecmp((string)$originHost, (string)$ownHost) !== 0 || (int)$originPort !== (int)$ownPort) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Request blocked.']);
            exit();
        }
    }
}

/** Maps a GmailException to the JSON error the frontend expects. */
function gmailFail(GmailException $e): void {
    $status = ['not_configured' => 503, 'not_connected' => 409, 'reauthorize' => 401, 'network' => 502, 'api' => 502, 'config' => 500][$e->kind] ?? 500;
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'code' => $e->kind]);
    exit();
}
