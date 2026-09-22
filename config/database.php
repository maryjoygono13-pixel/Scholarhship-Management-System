<?php
// Database Driver Config
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'scholarship_db');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Returns a PDO MySQL connection instance.
 */
function getMySQLConnection(): PDO {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    /*
     * MySQL's CURRENT_TIMESTAMP/NOW() follow the server's SYSTEM timezone,
     * unlike SQLite's which is always UTC. The app (see api/history.php)
     * assumes every created_at/sent_at/etc. column is UTC, and the data
     * migrated from the old SQLite database is UTC — so every connection
     * is pinned to UTC to keep new rows consistent with that.
     */
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}