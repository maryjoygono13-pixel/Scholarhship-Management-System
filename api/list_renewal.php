<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $action = trim($_POST['action'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($id <= 0) {
            sendError('Invalid action request.');
        }

        if (!in_array($action, ['renew', 'terminate'])) {
            sendError('Invalid action request.');
        }

        $rowStmt = $pdo->prepare("SELECT * FROM renewal_retention WHERE id = ?");
        $rowStmt->execute([$id]);
        $cand = $rowStmt->fetch();
        if (!$cand) {
            sendError('This renewal entry no longer exists.', 404);
        }

        // View-only while the Active Semester is still the row's own term.
        if (isRenewalLocked($pdo, $cand)) {
            sendError('Locked: this entry can only be viewed until the next semester begins (change the Active Semester in Settings > Portal Configuration).', 423);
        }
        if (!in_array(strtolower(trim((string)$cand['status'])), ['pending', 'at-risk'], true)) {
            sendError('This entry has already been decided (' . $cand['status'] . ').', 422);
        }

        $origin = strtolower((string)($cand['origin'] ?? 'approved'));
        $required = resolveGwaRequirement($pdo, (string)$cand['scholarship_type']);
        $figures = liveRenewalFigures($cand, getSemesterGradeStats($pdo, [$cand['student_id']]));

        if ($action === 'renew') {
            // Only approved scholars whose GWA still meets the scholarship's requirement are renewed.
            if ($origin === 'rejected') {
                sendError('Cannot renew: this applicant was rejected in Evaluation.', 422);
            }
            if ($figures['gwa'] <= 0) {
                sendError('Cannot renew: no grades are recorded for ' . normalizeSemesterName($cand['semester']) . ' yet. Import the academic records in Data Management first.', 422);
            }
            if ($figures['gwa'] > $required) {
                sendError('Cannot renew: GWA ' . number_format($figures['gwa'], 2) . ' does not meet the required ' . number_format($required, 2) . ' for ' . $cand['scholarship_type'] . '.', 422);
            }
            // Keep the row's stored GWA in step with what was just checked.
            $pdo->prepare("UPDATE renewal_retention SET gwa = ?, failing_grades = ? WHERE id = ?")->execute([$figures['gwa'], $figures['failing'], $id]);
        } else {
            // Termination is for those who did not meet the required GWA, or who were rejected.
            if ($origin !== 'rejected') {
                if ($figures['gwa'] <= 0) {
                    sendError('Cannot terminate yet: no grades are recorded for ' . normalizeSemesterName($cand['semester']) . '. Import the academic records in Data Management first.', 422);
                }
                if ($figures['gwa'] <= $required) {
                    sendError('Cannot terminate: GWA ' . number_format($figures['gwa'], 2) . ' meets the required ' . number_format($required, 2) . ' for ' . $cand['scholarship_type'] . '. Renew this scholar instead.', 422);
                }
            }
            $pdo->prepare("UPDATE renewal_retention SET gwa = ?, failing_grades = ? WHERE id = ?")->execute([$figures['gwa'], $figures['failing'], $id]);
        }

        $newStatus = $action === 'renew' ? 'eligible' : 'terminated';
        $stmt = $pdo->prepare("UPDATE renewal_retention SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $remarks ?: "Status updated to $newStatus", $id]);

        $stmtWho = $pdo->prepare("SELECT student_id, name FROM renewal_retention WHERE id = ?");
        $stmtWho->execute([$id]);
        $who = $stmtWho->fetch();
        $whoName = $who ? ($who['name'] . ' (Student ID: ' . $who['student_id'] . ')') : ('Scholar #' . $id);

        // Terminating a scholar who held the scholarship bars them from applying for that same
        // scholarship again (they may still apply for another). A rejected applicant's entry is
        // only closed, and MERIT-BASED Academic is never barred.
        $barred = false;
        if ($action === 'terminate' && $origin !== 'rejected') {
            $barred = recordScholarshipTermination($pdo, (string)$cand['student_id'], (string)$cand['scholarship_type'], $id,
                'GWA ' . number_format($figures['gwa'], 2) . ' did not meet the required ' . number_format($required, 2) . ' (' . normalizeSemesterName($cand['semester']) . ' ' . $cand['school_year'] . ').');
        }

        // The Record follows the decision: renewed = approved, terminated = rejected.
        $renRow = $pdo->prepare("SELECT * FROM renewal_retention WHERE id = ?");
        $renRow->execute([$id]);
        $recordStatus = ($row = $renRow->fetch()) ? syncRecordWithRenewal($pdo, $row, $action) : null;

        if ($action === 'renew') {
            logActivity($pdo, 'Scholarship Renewal', 'Renewal & Retention', $whoName . ' scholarship was renewed (eligible)' . ($recordStatus ? '; their record is now ' . $recordStatus . '.' : '.'), $id);
        } else {
            logActivity($pdo, 'Scholarship Terminated', 'Renewal & Retention', $whoName . ' was terminated' . ($recordStatus ? '; their record is now ' . $recordStatus : '') . ($barred ? '; they can no longer apply for ' . $cand['scholarship_type'] . '.' : '.'), $id);
        }

        $message = $action === 'renew'
            ? 'Scholarship renewed.'
            : ($barred ? 'Scholar terminated. They can no longer apply for ' . $cand['scholarship_type'] . ' but may apply for a different scholarship.' : 'Entry terminated.');
        sendJson(['success' => true, 'message' => $message, 'recordStatus' => $recordStatus]);
    }

    // Every record of the active term has its (locked) row here, including older ones and rejections.
    backfillCurrentTermRenewals($pdo);

    $statusFilter = trim($_GET['status'] ?? '');
    $syFilter = trim($_GET['sy'] ?? '');
    $semFilter = trim($_GET['sem'] ?? '');
    $typeFilter = trim($_GET['type'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $query = "SELECT * FROM renewal_retention WHERE 1=1";
    $params = [];

    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
    }
    if ($syFilter !== '' && $syFilter !== 'all') {
        $query .= " AND school_year LIKE ?";
        $params[] = "%$syFilter%";
    }
    if ($semFilter !== '' && $semFilter !== 'all') {
        $query .= " AND LOWER(semester) LIKE LOWER(?)";
        $params[] = "%$semFilter%";
    }
    if ($typeFilter !== '' && $typeFilter !== 'all') {
        $query .= " AND LOWER(scholarship_type) LIKE LOWER(?)";
        $params[] = "%$typeFilter%";
    }
    if ($search !== '') {
        $query .= " AND (name LIKE ? OR student_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // GWA and failing grades are read live from the imported grades of each row's own semester.
    $gradeStats = getSemesterGradeStats($pdo, array_column($rows, 'student_id'));

    $programStmt = $pdo->prepare("SELECT program FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");

    $data = array_map(function($r) use ($pdo, $gradeStats, $programStmt) {
        $programStmt->execute([$r['student_id']]);
        $programName = (string)$programStmt->fetchColumn();
        $locked = isRenewalLocked($pdo, $r);
        $decidable = !$locked && in_array(strtolower(trim((string)$r['status'])), ['pending', 'at-risk'], true);
        $originVal = strtolower((string)($r['origin'] ?? 'approved'));
        $required = resolveGwaRequirement($pdo, (string)$r['scholarship_type']);
        $live = liveRenewalFigures($r, $gradeStats);
        $r['gwa'] = $live['gwa'];
        $r['failing_grades'] = $live['failing'];
        return [
            'gwaRequirement' => $required,
            'meetsGwa' => (float)$r['gwa'] > 0 && (float)$r['gwa'] <= $required,
            'id' => (int)$r['id'],
            'studentId' => $r['student_id'],
            'student_id' => $r['student_id'],
            'name' => $r['name'],
            'gwa' => (float)$r['gwa'],
            'failingGrades' => (int)$r['failing_grades'],
            'failing_grades' => (int)$r['failing_grades'],
            'enrolled' => (bool)$r['enrolled'],
            'status' => $r['status'],
            'schoolYear' => $r['school_year'],
            'school_year' => $r['school_year'],
            'semester' => $r['semester'],
            'scholarshipType' => $r['scholarship_type'],
            'scholarship_type' => $r['scholarship_type'],
            'remarks' => $r['remarks'],
            'program' => $programName,
            'programCode' => programAcronym($programName),
            'origin' => $originVal,
            'locked' => $locked,
            'canRenew' => $decidable && $originVal !== 'rejected' && (float)$r['gwa'] > 0 && (float)$r['gwa'] <= $required,
            'canTerminate' => $decidable && ($originVal === 'rejected' || ((float)$r['gwa'] > 0 && (float)$r['gwa'] > $required)),
        ];
    }, $rows);

    $activeTerm = getActiveSemester($pdo) . ' ' . getActiveSchoolYear($pdo);

    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status IN ('pending', 'at-risk')")->fetchColumn();
    $eligibleCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'eligible'")->fetchColumn();
    $atRiskCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'at-risk'")->fetchColumn();
    $terminatedCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'terminated'")->fetchColumn();
    $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention")->fetchColumn();

    sendJson([
        'success' => true,
        'data' => $data,
        'activeTerm' => $activeTerm,
        'summary' => [
            'pending' => $pendingCount,
            'eligible' => $eligibleCount,
            'at_risk' => $atRiskCount,
            'terminated' => $terminatedCount,
            'total' => $totalCount
        ]
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
