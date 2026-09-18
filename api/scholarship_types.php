<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?? $_POST;
        $action = trim($payload['action'] ?? '');

        if ($action === 'add_type') {
            $name = trim($payload['name'] ?? '');
            if ($name === '') {
                sendError('Please enter a scholarship type name.');
            }

            $existing = $pdo->prepare("SELECT id, name FROM scholarship_types WHERE LOWER(name) = LOWER(?)");
            $existing->execute([$name]);
            $found = $existing->fetch();

            if ($found) {
                sendJson(['success' => true, 'id' => (int)$found['id'], 'name' => $found['name'], 'existing' => true]);
            }

            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM scholarship_types")->fetchColumn();
            $insert = $pdo->prepare("INSERT INTO scholarship_types (name, sort_order) VALUES (?, ?)");
            $insert->execute([$name, $maxOrder + 1]);
            $newId = (int)$pdo->lastInsertId();

            logActivity($pdo, 'Scholarship Type Added', 'Scholarships', 'New scholarship type "' . $name . '" was added.', $newId);

            sendJson(['success' => true, 'id' => $newId, 'name' => $name, 'existing' => false]);
        }

        if ($action === 'add_subtype') {
            $typeId = (int)($payload['type_id'] ?? 0);
            $name = trim($payload['name'] ?? '');

            if ($typeId <= 0) {
                sendError('Please select a scholarship type first.');
            }
            if ($name === '') {
                sendError('Please enter a sub-type name.');
            }

            $typeStmt = $pdo->prepare("SELECT name FROM scholarship_types WHERE id = ?");
            $typeStmt->execute([$typeId]);
            $typeName = $typeStmt->fetchColumn();
            if (!$typeName) {
                sendError('That scholarship type no longer exists.', 404);
            }

            $existing = $pdo->prepare("SELECT id, name FROM scholarship_subtypes WHERE type_id = ? AND LOWER(name) = LOWER(?)");
            $existing->execute([$typeId, $name]);
            $found = $existing->fetch();

            if ($found) {
                sendJson(['success' => true, 'id' => (int)$found['id'], 'name' => $found['name'], 'existing' => true]);
            }

            $insert = $pdo->prepare("INSERT INTO scholarship_subtypes (type_id, name) VALUES (?, ?)");
            $insert->execute([$typeId, $name]);
            $newId = (int)$pdo->lastInsertId();

            logActivity($pdo, 'Scholarship Sub-type Added', 'Scholarships', 'New sub-type "' . $name . '" was added under "' . $typeName . '".', $newId);

            sendJson(['success' => true, 'id' => $newId, 'name' => $name, 'existing' => false]);
        }

        sendError('Unknown action.');
    }

    // GET — every type with its nested sub-types, in display order.
    $types = $pdo->query("SELECT id, name FROM scholarship_types ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $subtypeStmt = $pdo->prepare("SELECT id, name FROM scholarship_subtypes WHERE type_id = ? ORDER BY name ASC");

    $data = array_map(function ($t) use ($subtypeStmt) {
        $subtypeStmt->execute([$t['id']]);
        return [
            'id' => (int)$t['id'],
            'name' => $t['name'],
            'subtypes' => array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name']], $subtypeStmt->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }, $types);

    sendJson(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
