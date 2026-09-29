<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?? $_POST;

    $applicantId = (int)($payload['applicantId'] ?? 0);
    $criterionId = (int)($payload['criterionId'] ?? 0);
    $status = trim(strtolower($payload['status'] ?? ''));
    $remarks = trim($payload['remarks'] ?? '');

    if ($applicantId <= 0 || $criterionId <= 0) {
        sendError('Applicant and criterion are required.');
    }
    if (!in_array($status, ['pass', 'fail', 'pending'], true)) {
        sendError('Status must be pass, fail, or pending.');
    }

    $applicantStmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM applicants WHERE id = ?");
    $applicantStmt->execute([$applicantId]);
    $applicant = $applicantStmt->fetch(PDO::FETCH_ASSOC);
    if (!$applicant) {
        sendError('That applicant no longer exists.', 404);
    }

    $critStmt = $pdo->prepare("SELECT label, criterion_type FROM scholarship_criteria WHERE id = ?");
    $critStmt->execute([$criterionId]);
    $criterion = $critStmt->fetch(PDO::FETCH_ASSOC);
    if (!$criterion) {
        sendError('That criterion no longer exists.', 404);
    }
    $criterionLabel = $criterion['label'] !== '' ? $criterion['label'] : (CRITERION_TYPES[$criterion['criterion_type']] ?? $criterion['criterion_type']);

    $reviewer = trim((string)($_SESSION['user_name'] ?? '')) ?: 'Registrar Staff';
    $pdo->prepare("INSERT INTO applicant_criteria_reviews (applicant_id, scholarship_criteria_id, status, remarks, reviewed_by) VALUES (?, ?, ?, ?, ?)")
        ->execute([$applicantId, $criterionId, $status, $remarks, $reviewer]);

    $who = trim($applicant['first_name'] . ' ' . $applicant['last_name']) . ' (Student ID: ' . $applicant['student_id'] . ')';
    logActivity($pdo, 'Manual Criterion Reviewed', 'Evaluation', $who . '\'s "' . $criterionLabel . '" criterion was marked ' . strtoupper($status) . '.', $applicantId);

    sendJson(['success' => true]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
