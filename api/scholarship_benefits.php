<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?? $_POST;
        $action = trim($payload['action'] ?? 'save_batch');

        $scholarshipId = (int)($payload['scholarship_id'] ?? 0);
        if ($scholarshipId <= 0) {
            sendError('A scholarship must be saved first before its benefits can be added.');
        }
        $schStmt = $pdo->prepare("SELECT name FROM scholarships WHERE id = ?");
        $schStmt->execute([$scholarshipId]);
        $schName = $schStmt->fetchColumn();
        if (!$schName) {
            sendError('That scholarship no longer exists.', 404);
        }

        if ($action === 'save_batch') {
            $items = is_array($payload['benefits'] ?? null) ? $payload['benefits'] : [];

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM scholarship_benefits WHERE scholarship_id = ?")->execute([$scholarshipId]);

            $insert = $pdo->prepare("INSERT INTO scholarship_benefits (scholarship_id, benefit_type, value, apply_scope, description, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $saved = [];
            foreach ($items as $i => $item) {
                $type = trim($item['type'] ?? '');
                if ($type === '' || !isset(BENEFIT_TYPES[$type])) continue;
                $value = trim($item['value'] ?? '');
                $applyScope = trim($item['applyScope'] ?? '') ?: null;
                $description = trim($item['description'] ?? '');

                $insert->execute([$scholarshipId, $type, $value, $applyScope, $description, $i]);
                $saved[] = ['id' => (int)$pdo->lastInsertId(), 'type' => $type, 'label' => BENEFIT_TYPES[$type]];
            }
            $pdo->commit();

            logActivity($pdo, 'Scholarship Benefits Updated', 'Scholarships', count($saved) . ' benefit(s) saved for "' . $schName . '".', $scholarshipId);
            sendJson(['success' => true, 'benefits' => $saved]);
        }

        sendError('Unknown action.');
    }

    // GET — the benefits configured for one scholarship, in display order.
    $scholarshipId = (int)($_GET['scholarship_id'] ?? 0);
    if ($scholarshipId <= 0) {
        sendJson(['success' => true, 'data' => [], 'types' => BENEFIT_TYPES]);
    }
    $rows = getScholarshipBenefits($pdo, $scholarshipId);
    $data = array_map(fn($r) => [
        'id' => (int)$r['id'],
        'type' => $r['benefit_type'],
        'label' => BENEFIT_TYPES[$r['benefit_type']] ?? $r['benefit_type'],
        'value' => $r['value'],
        'applyScope' => $r['apply_scope'],
        'description' => $r['description'],
    ], $rows);

    sendJson(['success' => true, 'data' => $data, 'types' => BENEFIT_TYPES]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendError($e->getMessage(), 500);
}
