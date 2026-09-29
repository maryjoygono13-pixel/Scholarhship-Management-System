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
            sendError('A scholarship must be saved first before its criteria can be added.');
        }
        $schStmt = $pdo->prepare("SELECT name FROM scholarships WHERE id = ?");
        $schStmt->execute([$scholarshipId]);
        $schName = $schStmt->fetchColumn();
        if (!$schName) {
            sendError('That scholarship no longer exists.', 404);
        }

        if ($action === 'save_batch') {
            // Replaces the full criteria set for this scholarship with exactly what was sent —
            // the wizard always submits its complete current list, so add/edit/remove are all
            // expressed the same way (no separate delete calls needed).
            $items = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];

            foreach ($items as $item) {
                if (trim($item['type'] ?? '') === 'poverty_threshold') {
                    $amount = preg_replace('/[^0-9.]/', '', (string)($item['value'] ?? ''));
                    if (!is_numeric($amount) || (float)$amount <= 0) {
                        sendError('Please enter the Poverty Threshold amount (monthly, in pesos).');
                    }
                }
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM scholarship_criteria WHERE scholarship_id = ?")->execute([$scholarshipId]);

            $insert = $pdo->prepare("INSERT INTO scholarship_criteria (scholarship_id, criterion_type, label, operator, value, value2, required, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $saved = [];
            foreach ($items as $i => $item) {
                $type = trim($item['type'] ?? '');
                if ($type === '' || !isset(CRITERION_TYPES[$type])) continue;
                $label = trim($item['label'] ?? '');
                $operator = trim($item['operator'] ?? 'lte');
                $value = trim((string)($item['value'] ?? ''));
                $value2 = isset($item['value2']) && $item['value2'] !== '' ? trim((string)$item['value2']) : null;
                if ($type === 'poverty_threshold') {
                    // Always "income at most the threshold" — never an applicant-side choice.
                    $operator = 'lte';
                    $value2 = null;
                    $value = preg_replace('/[^0-9.]/', '', $value); // "₱15,000.00" -> "15000.00"
                }
                $required = empty($item['required']) ? 0 : 1;

                $insert->execute([$scholarshipId, $type, $label, $operator, $value, $value2, $required, $i]);
                $saved[] = ['id' => (int)$pdo->lastInsertId(), 'type' => $type, 'label' => $label ?: CRITERION_TYPES[$type]];
            }
            $pdo->commit();

            logActivity($pdo, 'Scholarship Criteria Updated', 'Scholarships', count($saved) . ' eligibility criterion/criteria saved for "' . $schName . '".', $scholarshipId);
            sendJson(['success' => true, 'criteria' => $saved]);
        }

        sendError('Unknown action.');
    }

    // GET — the criteria configured for one scholarship, in display order.
    $scholarshipId = (int)($_GET['scholarship_id'] ?? 0);
    if ($scholarshipId <= 0) {
        sendJson(['success' => true, 'data' => [], 'types' => CRITERION_TYPES]);
    }
    $rows = getScholarshipCriteria($pdo, $scholarshipId);
    $data = array_map(fn($r) => [
        'id' => (int)$r['id'],
        'type' => $r['criterion_type'],
        'label' => $r['label'],
        'operator' => $r['operator'],
        'value' => $r['value'],
        'value2' => $r['value2'],
        'required' => (bool)$r['required'],
    ], $rows);

    sendJson(['success' => true, 'data' => $data, 'types' => CRITERION_TYPES]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendError($e->getMessage(), 500);
}
