<?php
require_once __DIR__ . '/init.php';

try {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        sendError('Invalid item ID.');
    }

    $pdo = getDB();

    $stmtSelect = $pdo->prepare("SELECT * FROM renewal_retention WHERE id = ?");
    $stmtSelect->execute([$id]);
    $row = $stmtSelect->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendError('This renewal entry no longer exists.', 404);
    }

    // Deletable regardless of the row's lock status — locking only ever governed whether a
    // Renew/Terminate decision could be made, never whether the entry could be removed.
    $title = ($row['name'] ?: 'Scholar') . ' (' . $row['student_id'] . ') — ' . $row['scholarship_type'];
    $pdo->prepare("INSERT INTO deleted_items (item_type, item_id, title, item_data, deleted_by) VALUES ('renewal', ?, ?, ?, ?)")
        ->execute([$id, $title, json_encode($row), $_SESSION['user_name'] ?? 'Registrar Staff']);

    $pdo->prepare("DELETE FROM renewal_retention WHERE id = ?")->execute([$id]);

    // Scholars is for those currently active/approved — once a student has no Renewal &
    // Retention standing left at all, they don't belong there either.
    $studentId = trim((string)$row['student_id']);
    $removedFromScholars = false;
    if ($studentId !== '') {
        $others = $pdo->prepare("SELECT COUNT(*) FROM renewal_retention WHERE student_id = ?");
        $others->execute([$studentId]);
        if ((int)$others->fetchColumn() === 0) {
            $delScholar = $pdo->prepare("DELETE FROM scholars WHERE student_id = ?");
            $delScholar->execute([$studentId]);
            $removedFromScholars = $delScholar->rowCount() > 0;
        }
    }

    logActivity($pdo, 'Renewal Entry Deleted', 'Renewal & Retention', $title . ' was moved to Trash Bin.' . ($removedFromScholars ? ' Also removed from Scholars (no remaining scholarship standing).' : ''), $id);

    sendJson([
        'success' => true,
        'message' => 'Scholar renewal entry deleted.' . ($removedFromScholars ? ' Also removed from Scholars.' : ''),
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
