<?php
require_once __DIR__ . '/init.php';

try {
    $id = (int)($_POST['id'] ?? 0);
    $decision = strtolower(trim($_POST['decision'] ?? ''));
    $remarks = trim($_POST['remarks'] ?? '');

    if ($id <= 0 || !in_array($decision, ['approved', 'rejected', 'review', 'interview'])) {
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
        $stmtRec = $pdo->prepare("INSERT INTO records (applicant_id, student_id, name, scholarship_type, status, semester, sy, date_evaluated, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)");
        $stmtRec->execute([
            $app['id'],
            $app['student_id'],
            trim($app['first_name'] . ' ' . $app['last_name']),
            $app['scholarship_type'],
            $decision,   // the record carries the same status as the decision
            normalizeSemesterName($app['semester'] ?? getActiveSemester($pdo)),
            trim((string)($app['school_year'] ?? '')) !== '' ? trim($app['school_year']) : getActiveSchoolYear($pdo),
            $remarks ?: ($decision === 'approved' ? 'Approved by committee' : 'Rejected by committee')
        ]);

        // Approved and rejected alike go to Renewal & Retention as pending (locked until the next semester).
        $newRecord = $pdo->prepare("SELECT * FROM records WHERE id = ?");
        $newRecord->execute([(int)$pdo->lastInsertId()]);
        $recordRow = $newRecord->fetch(PDO::FETCH_ASSOC);
        if ($recordRow) {
            sendRecordToRenewal($pdo, $recordRow, 'pending', null, $decision);
        }
    }

    sendJson(['success' => true, 'message' => "Applicant marked as $decision."]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
