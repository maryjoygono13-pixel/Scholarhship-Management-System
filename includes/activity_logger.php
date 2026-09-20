<?php

/*
 * Shared History / Audit Trail logger.
 *
 * Uses the authenticated Registrar account stored in the session.
 *
 * Logging failures are intentionally ignored so that a history/audit
 * problem can never break the actual CRUD operation being performed.
 */

function logActivity(
    PDO $pdo,
    string $action,
    string $module,
    string $description,
    $recordId = null
): void {
    try {

        $userId = isset($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : 0;

        $userName = trim(
            (string) ($_SESSION['user_name'] ?? '')
        );

        if ($userName === '') {
            $userName = 'Registrar Staff';
        }

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (
                user_id,
                user_name,
                action,
                module,
                description,
                record_id,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $userId,
            $userName,
            $action,
            $module,
            $description,
            $recordId !== null ? (int) $recordId : null,
        ]);

    } catch (Throwable $e) {

        error_log(
            'Failed to log activity: ' . $e->getMessage()
        );
    }
}
