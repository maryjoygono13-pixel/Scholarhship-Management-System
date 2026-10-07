<?php
/*
 * Safeguards for the public scholarship application (pages/apply.php -> api/student_apply.php),
 * against spam, scripted submissions and floods that would slow the system down:
 *   - a signed form token: submissions must come from the Apply page, for that Student ID
 *   - a minimum fill time (a person can't finish 6 steps in seconds) and a maximum token age
 *   - a hidden "honeypot" field that only bots fill in
 *   - rate limits per connection (IP) and per Student ID
 *   - document size limits
 * Cheap checks run first, so rejected requests cost almost nothing.
 */

// Limits — adjust here.
const APPLY_MIN_FILL_SECONDS = 15;           // faster than this is a script, not a person
const APPLY_TOKEN_MAX_AGE = 3 * 3600;        // the form must be submitted within 3 hours of opening
const APPLY_ATTEMPTS_PER_IP = 120;           // any submission attempts, per connection… (campus Wi-Fi shares one address)
const APPLY_ATTEMPTS_WINDOW = 600;           // …per 10 minutes
const APPLY_SUBMISSIONS_PER_IP = 40;         // accepted applications per connection… (many students may share one address)
const APPLY_SUBMISSIONS_IP_WINDOW = 3600;    // …per hour
const APPLY_SUBMISSIONS_PER_STUDENT = 3;     // accepted applications per Student ID…
const APPLY_SUBMISSIONS_STUDENT_WINDOW = 86400; // …per day
const APPLY_MAX_FILE_BYTES = 5 * 1024 * 1024;   // per document
const APPLY_MAX_TOTAL_BYTES = 25 * 1024 * 1024; // all documents together
const APPLY_HONEYPOT_FIELD = 'website';      // hidden field; people never fill it in

// The visitor's address. Behind a proxy/load balancer, the proxy's header would be needed here.
function applyClientIp(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

// A per-install secret for signing form tokens, kept in storage/ (not publicly reachable).
function applyAppSecret(): string {
    static $secret = null;
    if ($secret !== null) return $secret;
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    if (!is_file($dir . '/.htaccess')) file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    $file = $dir . '/app_secret.key';
    if (!is_file($file)) file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX);
    $secret = trim((string)file_get_contents($file));
    return $secret;
}

// "<issued time>.<signature>" for this Student ID.
function applyFormToken(string $studentId): string {
    $ts = (string)time();
    return $ts . '.' . hash_hmac('sha256', $ts . '|' . trim($studentId), applyAppSecret());
}

// null when the token is valid for this Student ID, otherwise the message to show.
function applyCheckFormToken(string $token, string $studentId): ?string {
    if (!preg_match('/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m)) {
        return 'Please submit the application from the Apply page.';
    }
    if (!hash_equals(hash_hmac('sha256', $m[1] . '|' . trim($studentId), applyAppSecret()), $m[2])) {
        return 'Please submit the application from the Apply page.';
    }
    $age = time() - (int)$m[1];
    if ($age < APPLY_MIN_FILL_SECONDS) {
        return 'That was submitted too quickly. Please review your answers and submit again.';
    }
    if ($age > APPLY_TOKEN_MAX_AGE) {
        return 'This form has been open too long. Please reload the Apply page and fill it in again.';
    }
    return null;
}

/*
 * Rate limit: how many seconds until another request in $bucket for $key is allowed (0 = allowed
 * now). Keys are stored only as hashes. Old rows are pruned now and then.
 */
function applyThrottleWait(PDO $pdo, string $bucket, string $key, int $max, int $windowSeconds): int {
    if (mt_rand(1, 50) === 1) {
        $pdo->exec("DELETE FROM request_throttle WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 2 DAY)");
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n, MIN(created_at) AS oldest FROM request_throttle WHERE bucket = ? AND key_hash = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL ? SECOND)");
    $stmt->execute([$bucket, hash('sha256', $key), $windowSeconds]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ((int)$row['n'] < $max) return 0;
    $oldest = strtotime($row['oldest'] . ' UTC');
    return max(60, $oldest + $windowSeconds - time());
}

function applyThrottleRecord(PDO $pdo, string $bucket, string $key): void {
    $pdo->prepare("INSERT INTO request_throttle (bucket, key_hash, created_at) VALUES (?, ?, UTC_TIMESTAMP())")
        ->execute([$bucket, hash('sha256', $key)]);
}

// "about 45 minutes" / "about 2 hours"
function applyWaitText(int $seconds): string {
    $minutes = (int)ceil($seconds / 60);
    if ($minutes < 60) return 'about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    $hours = (int)ceil($minutes / 60);
    return 'about ' . $hours . ' hour' . ($hours === 1 ? '' : 's');
}

// null when the uploaded documents are within the size limits, otherwise the message to show.
function applyCheckUploadSizes(): ?string {
    $total = 0;
    foreach ((array)($_FILES['documents']['size'] ?? []) as $type => $size) {
        $err = $_FILES['documents']['error'][$type] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int)$size > APPLY_MAX_FILE_BYTES) {
            return 'Each document must be 5 MB or smaller. Please upload a smaller image.';
        }
        $total += (int)$size;
    }
    return $total > APPLY_MAX_TOTAL_BYTES ? 'Your documents are too large together (25 MB at most). Please upload smaller images.' : null;
}
