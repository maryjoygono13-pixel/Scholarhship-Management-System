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

        if ($action === 'update_semester') {
            $semester = trim($_POST['semester'] ?? '');
            if (!in_array($semester, ['1st Semester', '2nd Semester'], true)) {
                sendError('Invalid semester value.');
            }

            $stmt = $pdo->prepare("UPDATE renewal_retention SET semester = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$semester, $id]);

            $stmtWho = $pdo->prepare("SELECT student_id, name FROM renewal_retention WHERE id = ?");
            $stmtWho->execute([$id]);
            $who = $stmtWho->fetch();
            $whoName = $who ? ($who['name'] . ' (Student ID: ' . $who['student_id'] . ')') : ('Scholar #' . $id);

            logActivity($pdo, 'Semester Changed', 'Renewal & Retention', $whoName . ' semester changed to "' . $semester . '".', $id);

            sendJson(['success' => true, 'message' => "Semester updated to $semester.", 'semester' => $semester]);
        }

        if (!in_array($action, ['renew', 'flag', 'terminate'])) {
            sendError('Invalid action request.');
        }

        $newStatus = $action === 'renew' ? 'eligible' : ($action === 'flag' ? 'at-risk' : 'terminated');
        $stmt = $pdo->prepare("UPDATE renewal_retention SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $remarks ?: "Status updated to $newStatus", $id]);

        $stmtWho = $pdo->prepare("SELECT student_id, name FROM renewal_retention WHERE id = ?");
        $stmtWho->execute([$id]);
        $who = $stmtWho->fetch();
        $whoName = $who ? ($who['name'] . ' (Student ID: ' . $who['student_id'] . ')') : ('Scholar #' . $id);

        if ($action === 'renew') {
            logActivity($pdo, 'Scholarship Renewal', 'Renewal & Retention', $whoName . ' scholarship was renewed (eligible).', $id);
        } else {
            logActivity($pdo, 'Status Changed', 'Renewal & Retention', $whoName . ' status changed to "' . $newStatus . '".', $id);
        }

        sendJson(['success' => true, 'message' => "Scholar status updated to $newStatus."]);
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

    $data = array_map(function($r) {
        return [
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

    $eligibleCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'eligible'")->fetchColumn();
    $atRiskCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'at-risk'")->fetchColumn();
    $terminatedCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention WHERE status = 'terminated'")->fetchColumn();
    $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM renewal_retention")->fetchColumn();

    sendJson([
        'success' => true,
        'data' => $data,
        'summary' => [
            'eligible' => $eligibleCount,
            'at_risk' => $atRiskCount,
            'terminated' => $terminatedCount,
            'total' => $totalCount
        ]
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
