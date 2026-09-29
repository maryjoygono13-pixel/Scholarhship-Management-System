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

        if ($action === 'add_subtype' || $action === 'add_subtypes_batch') {
            $typeId = (int)($payload['type_id'] ?? 0);
            if ($typeId <= 0) {
                sendError('Please select a scholarship type first.');
            }

            $typeStmt = $pdo->prepare("SELECT name FROM scholarship_types WHERE id = ?");
            $typeStmt->execute([$typeId]);
            $typeName = $typeStmt->fetchColumn();
            if (!$typeName) {
                sendError('That scholarship type no longer exists.', 404);
            }

            // Normalize both the single-item and batch shapes into one list.
            if ($action === 'add_subtypes_batch') {
                $items = is_array($payload['subtypes'] ?? null) ? $payload['subtypes'] : [];
            } else {
                $items = [['name' => $payload['name'] ?? '', 'gwa_requirement' => $payload['gwa_requirement'] ?? null]];
            }

            if (empty($items)) {
                sendError('Please add at least one program.');
            }

            $existingStmt = $pdo->prepare("SELECT id, name, gwa_requirement FROM scholarship_subtypes WHERE type_id = ? AND LOWER(name) = LOWER(?)");
            $insert = $pdo->prepare("INSERT INTO scholarship_subtypes (type_id, name, gwa_requirement) VALUES (?, ?, ?)");
            $results = [];

            foreach ($items as $item) {
                $name = trim($item['name'] ?? '');
                $gwaRaw = $item['gwa_requirement'] ?? null;

                if ($name === '') {
                    sendError('Every program needs a name.');
                }
                // GWA is optional here — the program's own GWA Requirement is
                // synced onto the sub-type when the scholarship is saved.
                if ($gwaRaw === null || $gwaRaw === '') {
                    $gwa = 0.0; // no GWA requirement
                } elseif (!is_numeric($gwaRaw) || (float)$gwaRaw <= 0) {
                    sendError('"' . $name . '" needs a valid GWA requirement greater than 0.');
                } else {
                    $gwa = round((float)$gwaRaw, 2);
                }

                $existingStmt->execute([$typeId, $name]);
                $found = $existingStmt->fetch();

                if ($found) {
                    $results[] = ['id' => (int)$found['id'], 'name' => $found['name'], 'gwaRequirement' => (float)$found['gwa_requirement'], 'existing' => true];
                    continue;
                }

                $insert->execute([$typeId, $name, $gwa]);
                $newId = (int)$pdo->lastInsertId();
                logActivity($pdo, 'Scholarship Program Added', 'Scholarships', 'New program "' . $name . '"' . ($gwa > 0 ? ' (GWA ' . $gwa . ')' : '') . ' was added under "' . $typeName . '".', $newId);
                $results[] = ['id' => $newId, 'name' => $name, 'gwaRequirement' => $gwa, 'existing' => false];
            }

            if ($action === 'add_subtype') {
                sendJson(array_merge(['success' => true], $results[0]));
            }
            sendJson(['success' => true, 'subtypes' => $results]);
        }

        if ($action === 'update_subtype') {
            $id = (int)($payload['id'] ?? 0);
            $name = trim($payload['name'] ?? '');
            $gwaRaw = $payload['gwa_requirement'] ?? null;

            if ($id <= 0) {
                sendError('Invalid program.');
            }
            if ($name === '') {
                sendError('Please enter a program name.');
            }
            // Blank = no GWA requirement.
            if ($gwaRaw === null || $gwaRaw === '') {
                $gwa = 0.0;
            } elseif (!is_numeric($gwaRaw) || (float)$gwaRaw <= 0) {
                sendError('Please enter a valid GWA requirement greater than 0, or leave it blank.');
            } else {
                $gwa = round((float)$gwaRaw, 2);
            }

            $stmt = $pdo->prepare("UPDATE scholarship_subtypes SET name = ?, gwa_requirement = ? WHERE id = ?");
            $stmt->execute([$name, $gwa, $id]);

            logActivity($pdo, 'Scholarship Program Updated', 'Scholarships', 'Program "' . $name . '" was updated' . ($gwa > 0 ? ' (GWA ' . $gwa . ')' : '') . '.', $id);

            sendJson(['success' => true, 'id' => $id, 'name' => $name, 'gwaRequirement' => $gwa]);
        }

        if ($action === 'delete_subtype') {
            $id = (int)($payload['id'] ?? 0);
            if ($id <= 0) {
                sendError('Invalid program.');
            }

            $nameStmt = $pdo->prepare("SELECT name FROM scholarship_subtypes WHERE id = ?");
            $nameStmt->execute([$id]);
            $name = $nameStmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM scholarship_subtypes WHERE id = ?");
            $stmt->execute([$id]);

            if ($name) {
                logActivity($pdo, 'Scholarship Program Deleted', 'Scholarships', 'Program "' . $name . '" was deleted.', $id);
            }

            sendJson(['success' => true]);
        }

        sendError('Unknown action.');
    }

    // GET — every type with its nested sub-types, in display order.
    $types = $pdo->query("SELECT id, name FROM scholarship_types ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $subtypeStmt = $pdo->prepare("SELECT id, name, gwa_requirement FROM scholarship_subtypes WHERE type_id = ? ORDER BY name ASC");

    $data = array_map(function ($t) use ($subtypeStmt) {
        $subtypeStmt->execute([$t['id']]);
        return [
            'id' => (int)$t['id'],
            'name' => $t['name'],
            'subtypes' => array_map(fn($s) => [
                'id' => (int)$s['id'],
                'name' => $s['name'],
                'gwaRequirement' => (float)$s['gwa_requirement'],
            ], $subtypeStmt->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }, $types);

    sendJson(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
