<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    $id = (int)($_POST['id'] ?? 0);
    $studentId = trim($_POST['student_id'] ?? $_POST['studentId'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? 'Information Technology');
    $yearLevel = (int)($_POST['year_level'] ?? $_POST['yearLevel'] ?? 1);
    $gwa = (float)($_POST['gwa'] ?? 1.50);
    $schoolYear = trim($_POST['school_year'] ?? $_POST['schoolYear'] ?? '2025-2026');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($studentId) || empty($name) || empty($department)) {
        sendError('Please fill out Student ID, Name, and Department.');
    }

    if ($yearLevel < 1 || $yearLevel > 4) {
        $yearLevel = 1;
    }

    // GWA Maintenance Logic: GWA <= 1.50 is Active/Maintaining, > 1.50 is Removed
    $status = trim($_POST['status'] ?? '');
    if (empty($status) || strtolower($status) === 'auto') {
        $status = ($gwa <= 1.50) ? 'Active' : 'Removed';
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE scholars SET
            student_id = ?, name = ?, department = ?, year_level = ?, gwa = ?, status = ?, school_year = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?");
        $stmt->execute([$studentId, $name, $department, $yearLevel, $gwa, $status, $schoolYear, $remarks, $id]);
        logActivity($pdo, 'Scholar Updated', 'Scholars', $name . ' (Student ID: ' . $studentId . ') was updated.', $id);
        sendJson(['success' => true, 'id' => $id, 'message' => 'Scholar record updated successfully.']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO scholars (student_id, name, department, year_level, gwa, status, school_year, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$studentId, $name, $department, $yearLevel, $gwa, $status, $schoolYear, $remarks]);
        $newId = (int)$pdo->lastInsertId();
        logActivity($pdo, 'Scholar Added', 'Scholars', $name . ' (Student ID: ' . $studentId . ') was added as a scholar.', $newId);
        sendJson(['success' => true, 'id' => $newId, 'message' => 'Scholar record added successfully.']);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
