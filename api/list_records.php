<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $studentId = trim($_POST['student_id'] ?? $_POST['studentId'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['scholarship_type'] ?? $_POST['scholarshipType'] ?? 'Academic Merit');
        $status = trim($_POST['status'] ?? 'approved');
        $semester = trim($_POST['semester'] ?? 'First Semester');
        $sy = trim($_POST['sy'] ?? '2025-2026');
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($name) || empty($studentId)) {
            sendError('Student ID and Name are required.');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE records SET student_id = ?, name = ?, scholarship_type = ?, status = ?, semester = ?, sy = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$studentId, $name, $type, $status, $semester, $sy, $remarks, $id]);
            logActivity($pdo, 'Record Updated', 'Records', $name . ' (Student ID: ' . $studentId . ') record was updated.', $id);
            sendJson(['success' => true, 'id' => $id, 'message' => 'Record updated successfully.']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO records (student_id, name, scholarship_type, status, semester, sy, date_evaluated, remarks) VALUES (?, ?, ?, ?, ?, ?, DATE('now'), ?)");
            $stmt->execute([$studentId, $name, $type, $status, $semester, $sy, $remarks]);
            $newId = (int)$pdo->lastInsertId();
            logActivity($pdo, 'Record Added', 'Records', $name . ' (Student ID: ' . $studentId . ') record was added.', $newId);
            sendJson(['success' => true, 'id' => $newId, 'message' => 'Record created successfully.']);
        }
    }
    $statusParam = trim($_GET['status'] ?? '');
    $typeParam = trim($_GET['type'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $query = "SELECT * FROM records WHERE 1=1";
    $params = [];

    if ($statusParam !== '' && strtolower($statusParam) !== 'all status' && strtolower($statusParam) !== 'all') {
        $query .= " AND LOWER(status) = LOWER(?)";
        $params[] = $statusParam;
    }

    if ($typeParam !== '' && strtolower($typeParam) !== 'all scholarship types' && strtolower($typeParam) !== 'all') {
        $query .= " AND LOWER(scholarship_type) = LOWER(?)";
        $params[] = $typeParam;
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

    // Look up the applicant behind each record (if any) for richer academic details.
    $appStmt = $pdo->prepare("
        SELECT first_name, middle_name, last_name, program, major, year_level, gwa, gwa_req, failing_grades, units, enrolled, docs_complete
        FROM applicants
        WHERE id = ? OR student_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $data = array_map(function($r) use ($appStmt) {
        $appStmt->execute([(int)($r['applicant_id'] ?? 0), $r['student_id']]);
        $app = $appStmt->fetch();

        // Prefer the applicant's structured name (so the table can show a
        // middle initial and the detail view can show the full middle
        // name); fall back to the record's own flat name when there's no
        // matching applicant to pull first/middle/last from.
        if ($app && !empty($app['first_name'])) {
            $shortName = buildShortName($app['first_name'], $app['middle_name'] ?? '', $app['last_name']);
            $fullName = buildFullName($app['first_name'], $app['middle_name'] ?? '', $app['last_name']);
        } else {
            $shortName = $r['name'];
            $fullName = $r['name'];
        }

        return [
            'id' => (int)$r['id'],
            'studentId' => $r['student_id'],
            'student_id' => $r['student_id'],
            'name' => $shortName,
            'fullName' => $fullName,
            'scholarshipType' => $r['scholarship_type'],
            'scholarship_type' => $r['scholarship_type'],
            'status' => $r['status'],
            'semester' => $r['semester'],
            'sy' => $r['sy'],
            'dateEvaluated' => $r['date_evaluated'],
            'date_evaluated' => $r['date_evaluated'],
            'remarks' => $r['remarks'],
            'program' => $app['program'] ?? null,
            'major' => $app['major'] ?? '',
            'yearLevel' => $app['year_level'] ?? '',
            'gwa' => $app ? (float)$app['gwa'] : null,
            'gwaReq' => $app ? (float)$app['gwa_req'] : null,
            'failingGrades' => $app ? (int)$app['failing_grades'] : null,
            'units' => $app ? (int)$app['units'] : null,
            'enrolled' => $app ? (bool)$app['enrolled'] : null,
            'docsComplete' => $app ? (bool)$app['docs_complete'] : null,
        ];
    }, $rows);

    sendJson(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
