<?php
require_once __DIR__ . '/init.php';

try {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        sendError('Invalid import ID.');
    }

    $pdo = getDB();

    $stmtSelect = $pdo->prepare("SELECT * FROM imported_files WHERE id = ?");
    $stmtSelect->execute([$id]);
    $imp = $stmtSelect->fetch();

    $removedApplicants = 0;
    $removedGrades = 0;

    if ($imp) {
        // Carry along the applicants this import created, so Trash Bin restore
        // can bring them back too, and so we can remove them now.
        $createdIds = [];
        if (!empty($imp['created_applicant_ids'])) {
            $decoded = json_decode($imp['created_applicant_ids'], true);
            if (is_array($decoded)) {
                $createdIds = array_map('intval', $decoded);
            }
        }

        $applicantSnapshots = [];
        if (!empty($createdIds)) {
            $placeholders = implode(',', array_fill(0, count($createdIds), '?'));
            $snapStmt = $pdo->prepare("SELECT * FROM applicants WHERE id IN ($placeholders)");
            $snapStmt->execute($createdIds);
            $applicantSnapshots = $snapStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $imp['created_applicant_snapshots'] = $applicantSnapshots;

        // Same idea for the individual subject grades this import added —
        // only rows it newly created, not ones it merely overwrote.
        $createdGradeIds = [];
        if (!empty($imp['created_grade_ids'])) {
            $decoded = json_decode($imp['created_grade_ids'], true);
            if (is_array($decoded)) {
                $createdGradeIds = array_map('intval', $decoded);
            }
        }

        $gradeSnapshots = [];
        if (!empty($createdGradeIds)) {
            $placeholders = implode(',', array_fill(0, count($createdGradeIds), '?'));
            $gradeSnapStmt = $pdo->prepare("SELECT * FROM student_grades WHERE id IN ($placeholders)");
            $gradeSnapStmt->execute($createdGradeIds);
            $gradeSnapshots = $gradeSnapStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $imp['created_grade_snapshots'] = $gradeSnapshots;

        $title = ($imp['file_name'] ?? 'Imported File');
        $stmtTrash = $pdo->prepare("INSERT INTO deleted_items (item_type, item_id, title, item_data, deleted_by) VALUES (?, ?, ?, ?, ?)");
        $stmtTrash->execute(['import', $id, $title, json_encode($imp), 'Registrar Staff']);

        if (!empty($createdIds)) {
            $placeholders = implode(',', array_fill(0, count($createdIds), '?'));
            $delApplicants = $pdo->prepare("DELETE FROM applicants WHERE id IN ($placeholders)");
            $delApplicants->execute($createdIds);
            $removedApplicants = $delApplicants->rowCount();
        }

        $affectedStudentIds = [];
        if (!empty($createdGradeIds)) {
            $placeholders = implode(',', array_fill(0, count($createdGradeIds), '?'));
            $affectedStudentIds = array_unique(array_column($gradeSnapshots, 'student_id'));

            $delGrades = $pdo->prepare("DELETE FROM student_grades WHERE id IN ($placeholders)");
            $delGrades->execute($createdGradeIds);
            $removedGrades = $delGrades->rowCount();

            // Recompute GWA / failing count for anyone who lost a grade.
            foreach ($affectedStudentIds as $sid) {
                $avgStmt = $pdo->prepare("SELECT AVG(grade) AS avg_grade, SUM(CASE WHEN grade > 3.00 THEN 1 ELSE 0 END) AS failing FROM student_grades WHERE student_id = ?");
                $avgStmt->execute([$sid]);
                $avgRow = $avgStmt->fetch(PDO::FETCH_ASSOC);
                $newGwa = ($avgRow && $avgRow['avg_grade'] !== null) ? round((float)$avgRow['avg_grade'], 2) : 0;
                $newFailing = ($avgRow && $avgRow['failing'] !== null) ? (int)$avgRow['failing'] : 0;
                $pdo->prepare("UPDATE applicants SET gwa = ?, failing_grades = ?, updated_at = CURRENT_TIMESTAMP WHERE student_id = ?")
                    ->execute([$newGwa, $newFailing, $sid]);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM imported_files WHERE id = ?");
        $stmt->execute([$id]);
    }

    $message = 'Imported file record moved to Trash Bin.';
    if ($removedApplicants > 0) {
        $message .= " $removedApplicants applicant(s) added by this import were also removed from Evaluation.";
    }
    if ($removedGrades > 0) {
        $message .= " $removedGrades grade(s) added by this import were also removed, and affected GWAs were recalculated.";
    }

    sendJson(['success' => true, 'message' => $message, 'removedApplicants' => $removedApplicants, 'removedGrades' => $removedGrades]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
