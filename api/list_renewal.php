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

        if (!in_array($action, ['renew', 'flag', 'terminate'])) {
            sendError('Invalid action request.');
        }

        // Renewal is only allowed when the scholar's current GWA still meets
        // the requirement of their scholarship type/sub-type.
        if ($action === 'renew') {
            $chk = $pdo->prepare("SELECT * FROM renewal_retention WHERE id = ?");
            $chk->execute([$id]);
            $cand = $chk->fetch();
            if ($cand) {
                $required = resolveGwaRequirement($pdo, (string)$cand['scholarship_type']);
                $figures = liveRenewalFigures($cand, getSemesterGradeStats($pdo, [$cand['student_id']]));
                if ($figures['gwa'] <= 0) {
                    sendError('Cannot renew: no grades are recorded for ' . normalizeSemesterName($cand['semester']) . ' yet. Import the academic records in Data Management first.', 422);
                }
                if ($figures['gwa'] > $required) {
                    sendError('Cannot renew: GWA ' . number_format($figures['gwa'], 2) . ' does not meet the required ' . number_format($required, 2) . ' for ' . $cand['scholarship_type'] . '.', 422);
                }
                // Keep the row's stored GWA in step with what was just checked.
                $pdo->prepare("UPDATE renewal_retention SET gwa = ?, failing_grades = ? WHERE id = ?")->execute([$figures['gwa'], $figures['failing'], $id]);
            }
        }

        $newStatus = $action === 'renew' ? 'eligible' : ($action === 'flag' ? 'at-risk' : 'terminated');
        $stmt = $pdo->prepare("UPDATE renewal_retention SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $remarks ?: "Status updated to $newStatus", $id]);

        $stmtWho = $pdo->prepare("SELECT student_id, name FROM renewal_retention WHERE id = ?");
        $stmtWho->execute([$id]);
        $who = $stmtWho->fetch();
        $whoName = $who ? ($who['name'] . ' (Student ID: ' . $who['student_id'] . ')') : ('Scholar #' . $id);

        // The Record follows the decision: renewed = approved, terminated = rejected,
        // flagged = stays pending.
        $renRow = $pdo->prepare("SELECT * FROM renewal_retention WHERE id = ?");
        $renRow->execute([$id]);
        $recordStatus = ($row = $renRow->fetch()) ? syncRecordWithRenewal($pdo, $row, $action) : null;

        if ($action === 'renew') {
            logActivity($pdo, 'Scholarship Renewal', 'Renewal & Retention', $whoName . ' scholarship was renewed (eligible)' . ($recordStatus ? '; their record is now ' . $recordStatus . '.' : '.'), $id);
        } else {
            logActivity($pdo, 'Status Changed', 'Renewal & Retention', $whoName . ' status changed to "' . $newStatus . '"' . ($recordStatus ? '; their record is now ' . $recordStatus . '.' : '.'), $id);
        }

        $message = "Scholar status updated to $newStatus.";
        if ($action === 'renew' && $recordStatus === 'approved') {
            $message = 'Scholarship renewed. The record is now approved.';
        }
        sendJson(['success' => true, 'message' => $message, 'recordStatus' => $recordStatus]);
    }

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

    $data = array_map(function($r) use ($pdo, $gradeStats) {
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
            'remarks' => $r['remarks']
        ];
    }, $rows);

    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'pending'")->fetchColumn();
    $eligibleCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'eligible'")->fetchColumn();
    $atRiskCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'at-risk'")->fetchColumn();
    $terminatedCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'terminated'")->fetchColumn();
    $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention")->fetchColumn();

    sendJson([
        'success' => true,
        'data' => $data,
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
