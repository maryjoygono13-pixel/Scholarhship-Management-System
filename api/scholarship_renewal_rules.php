<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?? $_POST;

        $scholarshipId = (int)($payload['scholarship_id'] ?? 0);
        if ($scholarshipId <= 0) {
            sendError('A scholarship must be saved first before its renewal rules can be added.');
        }
        $schStmt = $pdo->prepare("SELECT name FROM scholarships WHERE id = ?");
        $schStmt->execute([$scholarshipId]);
        $schName = $schStmt->fetchColumn();
        if (!$schName) {
            sendError('That scholarship no longer exists.', 404);
        }

        $requiresRenewal = empty($payload['requiresRenewal']) ? 0 : 1;
        $renewalPeriod = trim($payload['renewalPeriod'] ?? 'Every Semester') ?: 'Every Semester';
        $minGwaRaw = $payload['minGwa'] ?? null;
        $minGwa = ($minGwaRaw !== null && $minGwaRaw !== '' && is_numeric($minGwaRaw) && (float)$minGwaRaw > 0) ? round((float)$minGwaRaw, 2) : null;
        $noFailingRequired = empty($payload['noFailingGradesRequired']) ? 0 : 1;
        $updatedDocsRequired = empty($payload['updatedDocumentsRequired']) ? 0 : 1;
        $description = trim($payload['description'] ?? '');

        $existing = $pdo->prepare("SELECT id FROM scholarship_renewal_rules WHERE scholarship_id = ?");
        $existing->execute([$scholarshipId]);
        if ($existing->fetchColumn()) {
            $stmt = $pdo->prepare("UPDATE scholarship_renewal_rules SET requires_renewal = ?, renewal_period = ?, min_gwa = ?, no_failing_grades_required = ?, updated_documents_required = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE scholarship_id = ?");
            $stmt->execute([$requiresRenewal, $renewalPeriod, $minGwa, $noFailingRequired, $updatedDocsRequired, $description, $scholarshipId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO scholarship_renewal_rules (scholarship_id, requires_renewal, renewal_period, min_gwa, no_failing_grades_required, updated_documents_required, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$scholarshipId, $requiresRenewal, $renewalPeriod, $minGwa, $noFailingRequired, $updatedDocsRequired, $description]);
        }

        logActivity($pdo, 'Scholarship Renewal Rules Updated', 'Scholarships', 'Renewal rules saved for "' . $schName . '" (requires renewal: ' . ($requiresRenewal ? 'Yes' : 'No') . ').', $scholarshipId);
        sendJson(['success' => true]);
    }

    // GET — the renewal rules for one scholarship, or sensible defaults when none saved yet.
    $scholarshipId = (int)($_GET['scholarship_id'] ?? 0);
    $rules = $scholarshipId > 0 ? getScholarshipRenewalRules($pdo, $scholarshipId) : null;

    sendJson(['success' => true, 'data' => [
        'requiresRenewal' => $rules ? (bool)$rules['requires_renewal'] : true,
        'renewalPeriod' => $rules['renewal_period'] ?? 'Every Semester',
        'minGwa' => $rules && $rules['min_gwa'] !== null ? (float)$rules['min_gwa'] : null,
        'noFailingGradesRequired' => $rules ? (bool)$rules['no_failing_grades_required'] : true,
        'updatedDocumentsRequired' => $rules ? (bool)$rules['updated_documents_required'] : false,
        'description' => $rules['description'] ?? '',
    ]]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
