<?php
/*
 * Deletes Records (moved to the Trash Bin).
 *   POST id=5             -> that record (the row's delete icon)
 *   POST ids[]=5&ids[]=6  -> several at once (multi-select delete on the Records page)
 */
require_once __DIR__ . '/init.php';

/*
 * Moves one record to the Trash Bin, with its own application and (when it was the student's last
 * record) their Scholars entry / map pin. Returns its title, or null when the record doesn't exist.
 */
function trashRecord(PDO $pdo, int $id): ?string {
    // Get the record first
    $stmtSelect = $pdo->prepare("SELECT * FROM records WHERE id = ?");
    $stmtSelect->execute([$id]);
    $rec = $stmtSelect->fetch();

    if (!$rec) {
        return null;
    }

    // Get student ID so we can remove the map location
    $studentId = trim($rec['student_id'] ?? '');

    // Save record to Trash Bin first
    $title = ($rec['name'] ?? 'Record') . ' (' . ($rec['student_id'] ?? '') . ')';

    $stmtTrash = $pdo->prepare("
        INSERT INTO deleted_items
        (
            item_type,
            item_id,
            title,
            item_data,
            deleted_by
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmtTrash->execute([
        'record',
        $id,
        $title,
        json_encode($rec),
        'Registrar Staff'
    ]);

    /*
     * Remove the student's map data.
     *
     * The Scholar Map currently gets locations from:
     * 1. scholars
     * 2. applicants
     *
     * Therefore we remove the matching student from both.
     */
    if ($studentId !== '') {

        // A student can hold more than one scholarship type, so only remove what belongs
        // to THIS record: its own application (same scholarship type), not the student's others.
        if ((int)($rec['applicant_id'] ?? 0) > 0) {
            $stmtApplicant = $pdo->prepare("DELETE FROM applicants WHERE id = ?");
            $stmtApplicant->execute([(int)$rec['applicant_id']]);
        } else {
            $stmtApplicant = $pdo->prepare("
                DELETE FROM applicants
                WHERE student_id = ? AND LOWER(TRIM(scholarship_type)) = LOWER(TRIM(?))
            ");
            $stmtApplicant->execute([$studentId, (string)$rec['scholarship_type']]);
        }

        // The scholar entry (and their map pin) goes only when this was their last record.
        $stmtOthers = $pdo->prepare("SELECT COUNT(*) FROM records WHERE student_id = ? AND id != ?");
        $stmtOthers->execute([$studentId, $id]);
        if ((int)$stmtOthers->fetchColumn() === 0) {
            $stmtScholar = $pdo->prepare("DELETE FROM scholars WHERE student_id = ?");
            $stmtScholar->execute([$studentId]);
        }
    }

    // Finally delete the record
    $stmt = $pdo->prepare("
        DELETE FROM records
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    return $title;
}

try {
    $raw = $_POST['ids'] ?? null;
    $bulk = $raw !== null;
    $ids = $bulk
        ? array_values(array_unique(array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', (string)$raw)), fn($v) => $v > 0)))
        : array_filter([(int)($_POST['id'] ?? $_GET['id'] ?? 0)], fn($v) => $v > 0);
    if (!$ids) {
        sendError($bulk ? 'No records were selected.' : 'Invalid record ID.');
    }
    if (count($ids) > 1000) {
        sendError('Please delete at most 1,000 records at a time.');
    }

    $pdo = getDB();
    $titles = [];
    $pdo->beginTransaction();
    foreach ($ids as $id) {
        $title = trashRecord($pdo, $id);
        if ($title !== null) $titles[$id] = $title;
    }
    $pdo->commit();

    if (!$bulk && !$titles) {
        sendError('Record not found.');
    }
    if (count($titles) === 1) {
        logActivity($pdo, 'Record Deleted', 'Records', reset($titles) . ' was moved to Trash Bin.', (int)array_key_first($titles));
    } elseif ($titles) {
        $preview = implode(', ', array_slice($titles, 0, 10)) . (count($titles) > 10 ? ', and ' . (count($titles) - 10) . ' more' : '');
        logActivity($pdo, 'Record Deleted', 'Records', count($titles) . ' records were moved to Trash Bin: ' . $preview . '.');
    }

    $n = count($titles);
    sendJson([
        'success' => true,
        'deleted' => $n,
        'message' => $bulk ? "$n record" . ($n === 1 ? '' : 's') . ' moved to Trash Bin.' : 'Record moved to Trash Bin and removed from the Scholar Map.',
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    sendError($e->getMessage(), 500);
}
