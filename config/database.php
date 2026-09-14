<?php
// Database Driver Config ('sqlite' | 'mysql')
define('DB_DRIVER', 'sqlite');
define('SQLITE_PATH', __DIR__ . '/../database/scholarship.sqlite');

/**
 * Returns a PDO SQLite connection instance when needed.
 */
function getSQLiteConnection(string $path = SQLITE_PATH): PDO {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $pdo = new PDO("sqlite:" . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    /*
     * Every request opens its own SQLite connection, and by default
     * SQLite's rollback-journal mode lets a writer block every
     * reader (and vice versa) for the life of a transaction, with no
     * wait/retry — a query just fails (or on some builds, hangs)
     * immediately if the file is locked. Switching quickly between
     * pages like Applicants and Evaluation fires several overlapping
     * requests at once (each page's own data fetch, the 3 parallel
     * calls in updateNavCounts(), notifications, etc.), so they
     * routinely contend for the same database file — this is a
     * well-known source of "everything hangs" stalls on Windows in
     * particular. WAL mode lets readers and a writer proceed without
     * blocking each other, and busy_timeout makes any remaining
     * contention wait and retry for up to 5s instead of failing or
     * hanging with no bound.
     */
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA busy_timeout = 5000;");

    return $pdo;
}

// MySQL fallback connection (uncomment if switching back to MySQL)
/*
$host = "localhost";
$user = "root";
$password = "";
$db = "applicant_db";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection Failed");
}
*/