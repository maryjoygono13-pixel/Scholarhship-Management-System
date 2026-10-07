<?php
require_once __DIR__ . '/init.php';

/*
 * Moves one applicant to the Trash Bin (restorable) and removes their Records / Scholars rows so
 * they leave no marks on the Scholar Map. Returns the "Name (Student ID)" title, or null if the
 * applicant no longer exists. Used for a single delete and for the multi-select delete.
 */
function moveApplicantToTrash(PDO $pdo, int $id, string $deletedBy): ?string {
    $stmtSelect = $pdo->prepare("SELECT * FROM applicants WHERE id = ?");
    $stmtSelect->execute([$id]);
    $applicant = $stmtSelect->fetch();
    if (!$applicant) return null;

    $name = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
    $title = $name ? ($name . ' (' . ($applicant['student_id'] ?? '') . ')') : 'Applicant #' . $id;

    $pdo->prepare("INSERT INTO deleted_items (item_type, item_id, title, item_data, deleted_by) VALUES (?, ?, ?, ?, ?)")
        ->execute(['applicant', $id, $title, json_encode($applicant), $deletedBy]);

    // Remove from records and scholars so deleted applicant doesn't leave marks on scholar map
    $studentId = trim($applicant['student_id'] ?? '');
    if ($studentId !== '') {
        $pdo->prepare("DELETE FROM records WHERE student_id = ? OR applicant_id = ?")->execute([$studentId, $id]);
        $pdo->prepare("DELETE FROM scholars WHERE student_id = ?")->execute([$studentId]);
    }
    $pdo->prepare("DELETE FROM applicants WHERE id = ?")->execute([$id]);
    return $title;
}

try {
    $pdo = getDB();
    $deletedBy = $_SESSION['user_name'] ?? 'Registrar Staff';

    // Several at once (Applicants page multi-select): ids[]=1&ids[]=2… or ids=1,2,3.
    $rawIds = $_POST['ids'] ?? null;
    if ($rawIds !== null) {
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($rawIds) ? $rawIds : explode(',', (string)$rawIds)), fn($v) => $v > 0)));
        if (!$ids) sendError('No applicants were selected.');
        if (count($ids) > 500) sendError('Please delete at most 500 applicants at a time.');

        $pdo->beginTransaction();
        $titles = [];
        foreach ($ids as $id) {
            $title = moveApplicantToTrash($pdo, $id, $deletedBy);
            if ($title !== null) $titles[] = $title;
        }
        $pdo->commit();

        $count = count($titles);
        if ($count > 0) {
            logActivity($pdo, 'Applicants Deleted', 'Applicants', $count . ' applicant(s) were moved to Trash Bin: ' . implode(', ', array_slice($titles, 0, 10)) . ($count > 10 ? ', …' : '') . '.');
        }
        sendJson(['success' => true, 'deleted' => $count, 'message' => $count . ' applicant' . ($count === 1 ? '' : 's') . ' moved to Trash Bin. They can be restored anytime.']);
    }

    // One applicant (the row's delete icon).
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        sendError('Invalid applicant ID.');
    }
    $title = moveApplicantToTrash($pdo, $id, $deletedBy);
    if ($title !== null) {
        logActivity($pdo, 'Applicant Deleted', 'Applicants', $title . ' was moved to Trash Bin.', $id);
    }

    sendJson(['success' => true, 'message' => 'Applicant moved to Trash Bin. Can be reverted anytime.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    sendError($e->getMessage(), 500);
}
