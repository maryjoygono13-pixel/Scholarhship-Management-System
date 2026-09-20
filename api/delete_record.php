<?php
require_once __DIR__ . '/init.php';

try {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

    if ($id <= 0) {
        sendError('Invalid record ID.');
    }

    $pdo = getDB();

    // Get the record first
    $stmtSelect = $pdo->prepare("SELECT * FROM records WHERE id = ?");
    $stmtSelect->execute([$id]);
    $rec = $stmtSelect->fetch();

    if (!$rec) {
        sendError('Record not found.');
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

    logActivity($pdo, 'Record Deleted', 'Records', $title . ' was moved to Trash Bin.', $id);

    sendJson([
        'success' => true,
        'message' => 'Record moved to Trash Bin and removed from the Scholar Map.'
    ]);

} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}   
