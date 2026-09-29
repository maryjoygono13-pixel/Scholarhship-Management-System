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
            sendError('A scholarship must be saved first before its required documents can be added.');
        }
        $schStmt = $pdo->prepare("SELECT name FROM scholarships WHERE id = ?");
        $schStmt->execute([$scholarshipId]);
        $schName = $schStmt->fetchColumn();
        if (!$schName) {
            sendError('That scholarship no longer exists.', 404);
        }

        if ($action === 'save_batch') {
            $items = is_array($payload['documents'] ?? null) ? $payload['documents'] : [];

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM scholarship_documents WHERE scholarship_id = ?")->execute([$scholarshipId]);

            $insert = $pdo->prepare("INSERT INTO scholarship_documents (scholarship_id, document_type, description, required, sort_order) VALUES (?, ?, ?, ?, ?)");
            $saved = [];
            foreach ($items as $i => $item) {
                $type = trim($item['type'] ?? '');
                if ($type === '' || !isset(DOCUMENT_TYPES[$type])) continue;
                $description = trim($item['description'] ?? '');
                $required = empty($item['required']) ? 0 : 1;

                $insert->execute([$scholarshipId, $type, $description, $required, $i]);
                $saved[] = ['id' => (int)$pdo->lastInsertId(), 'type' => $type, 'label' => DOCUMENT_TYPES[$type]];
            }
            $pdo->commit();

            logActivity($pdo, 'Scholarship Documents Updated', 'Scholarships', count($saved) . ' required document(s) saved for "' . $schName . '".', $scholarshipId);
            sendJson(['success' => true, 'documents' => $saved]);
        }

        sendError('Unknown action.');
    }

    // GET — the documents configured for one scholarship, in display order.
    $scholarshipId = (int)($_GET['scholarship_id'] ?? 0);
    if ($scholarshipId <= 0) {
        sendJson(['success' => true, 'data' => [], 'types' => DOCUMENT_TYPES]);
    }
    $rows = getScholarshipDocuments($pdo, $scholarshipId);
    $data = array_map(fn($r) => [
        'id' => (int)$r['id'],
        'type' => $r['document_type'],
        'label' => DOCUMENT_TYPES[$r['document_type']] ?? $r['document_type'],
        'description' => $r['description'],
        'required' => (bool)$r['required'],
    ], $rows);

    sendJson(['success' => true, 'data' => $data, 'types' => DOCUMENT_TYPES]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendError($e->getMessage(), 500);
}
