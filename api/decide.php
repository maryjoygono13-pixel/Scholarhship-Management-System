<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/records_helper.php';

try {
    $id = (int)($_POST['id'] ?? 0);
    $decision = strtolower(trim($_POST['decision'] ?? ''));
    $remarks = trim($_POST['remarks'] ?? '');

    if ($id <= 0 || !in_array($decision, ['approved', 'rejected', 'review'])) {
        sendError('Invalid decision request.');
    }

    $pdo = getDB();

    // A student terminated from this scholarship can't be approved for it again.
    if ($decision === 'approved') {
        $chk = $pdo->prepare("SELECT student_id, scholarship_type FROM applicants WHERE id = ?");
        $chk->execute([$id]);
        $cand = $chk->fetch();
        if ($cand && findScholarshipTermination($pdo, (string)$cand['student_id'], (string)$cand['scholarship_type'])) {
            sendError(terminationBlockMessage((string)$cand['scholarship_type']), 422);
        }
    }

    $stmt = $pdo->prepare("UPDATE applicants SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$decision, $remarks, $id]);

    // Also get applicant info to update records table
    $stmtApp = $pdo->prepare("SELECT * FROM applicants WHERE id = ?");
    $stmtApp->execute([$id]);
    $app = $stmtApp->fetch();

    if ($app && in_array($decision, ['approved', 'rejected'])) {
        // One record per student / scholarship / school year / semester: deciding again in the same
        // term updates that record instead of adding another (includes/records_helper.php).
        $saved = saveTermRecord($pdo, [
            'applicant_id' => $app['id'],
            'student_id' => $app['student_id'],
            'name' => trim($app['first_name'] . ' ' . $app['last_name']),
            'scholarship_type' => $app['scholarship_type'],
            'status' => $decision,   // the record carries the same status as the decision
            'semester' => normalizeSemesterName($app['semester'] ?? getActiveSemester($pdo)),
            'sy' => trim((string)($app['school_year'] ?? '')) !== '' ? trim($app['school_year']) : getActiveSchoolYear($pdo),
            'remarks' => $remarks ?: ($decision === 'approved' ? 'Approved by committee' : 'Rejected by committee'),
            'origin' => 'evaluation',
        ], true);

        // Approved and rejected alike go to Renewal & Retention as pending (locked until the next semester).
        $recordRow = $saved['created'] ? $saved['record'] : null;
        if ($recordRow) {
            sendRecordToRenewal($pdo, $recordRow, 'pending', null, $decision);
        }
    }

    sendJson(['success' => true, 'message' => "Applicant marked as $decision."]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
