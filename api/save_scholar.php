<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    $id = (int)($_POST['id'] ?? 0);
    $studentId = trim($_POST['student_id'] ?? $_POST['studentId'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? 'Information Technology');
    $yearLevel = (int)($_POST['year_level'] ?? $_POST['yearLevel'] ?? 1);
    // GWA is not typed in any more: it comes from the imported academic records
    // (see api/list_scholars.php). The stored column only keeps a fallback figure.
    $schoolYear = trim($_POST['school_year'] ?? $_POST['schoolYear'] ?? '') ?: getActiveSchoolYear($pdo);
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($studentId) || empty($name) || empty($department)) {
        sendError('Please fill out Student ID, Name, and Department.');
    }

    if ($yearLevel < 1 || $yearLevel > 4) {
        $yearLevel = 1;
    }

    // Standing (Active / Removed) is worked out from the grades when the list is shown;
    // the stored value is only a manual override.
    $status = trim($_POST['status'] ?? '');
    if (empty($status) || strtolower($status) === 'auto') {
        $status = 'Active';
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE scholars SET
            student_id = ?, name = ?, department = ?, year_level = ?, status = ?, school_year = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?");
        $stmt->execute([$studentId, $name, $department, $yearLevel, $status, $schoolYear, $remarks, $id]);
        logActivity($pdo, 'Scholar Updated', 'Scholars', $name . ' (Student ID: ' . $studentId . ') was updated.', $id);
        sendJson(['success' => true, 'id' => $id, 'message' => 'Scholar record updated successfully.']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO scholars (student_id, name, department, year_level, status, school_year, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$studentId, $name, $department, $yearLevel, $status, $schoolYear, $remarks]);
        $newId = (int)$pdo->lastInsertId();
        logActivity($pdo, 'Scholar Added', 'Scholars', $name . ' (Student ID: ' . $studentId . ') was added as a scholar.', $newId);
        sendJson(['success' => true, 'id' => $newId, 'message' => 'Scholar record added successfully.']);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
