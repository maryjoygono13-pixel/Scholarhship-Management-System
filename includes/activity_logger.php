<?php
/*
 * Shared History / Audit Trail logger.
 *
 * There is no real per-user account system in this app yet (login.php
 * accepts any email/password and only stores a session identifier), so
 * "user" here means the identifier the person typed in at login rather
 * than a row in a `users` table. user_id is always 0 as a placeholder.
 *
 * Every call is wrapped so a logging failure can NEVER break the
 * actual CRUD operation it's attached to.
 */

function logActivity(PDO $pdo, string $action, string $module, string $description, $recordId = null): void {
    try {
        $userName = trim((string)($_SESSION['user_identifier'] ?? '')) ?: 'Registrar Staff';

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, module, description, record_id, created_at)
            VALUES (0, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $userName,
            $action,
            $module,
            $description,
            $recordId !== null ? (int)$recordId : null,
        ]);
    } catch (Throwable $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}
