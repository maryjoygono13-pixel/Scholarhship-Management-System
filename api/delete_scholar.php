<?php
/*
 * Moves Scholars entries to the Trash Bin (restorable).
 *   POST id=5             -> that scholar (the row's delete icon)
 *   POST ids[]=5&ids[]=6  -> several at once ("Delete selected" on the Scholars page)
 */
require_once __DIR__ . '/init.php';

try {
    $raw = $_POST['ids'] ?? null;
    $bulk = $raw !== null;
    $ids = $bulk
        ? array_values(array_unique(array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', (string)$raw)), fn($v) => $v > 0)))
        : array_filter([(int)($_POST['id'] ?? $_GET['id'] ?? 0)], fn($v) => $v > 0);
    if (!$ids) {
        sendError($bulk ? 'No scholars were selected.' : 'Invalid scholar ID.');
    }
    if (count($ids) > 1000) {
        sendError('Please delete at most 1,000 scholars at a time.');
    }

    $pdo = getDB();
    $stmtSelect = $pdo->prepare("SELECT * FROM scholars WHERE id = ?");
    $stmtTrash = $pdo->prepare("INSERT INTO deleted_items (item_type, item_id, title, item_data, deleted_by) VALUES (?, ?, ?, ?, ?)");
    $stmtDelete = $pdo->prepare("DELETE FROM scholars WHERE id = ?");

    $titles = [];
    $pdo->beginTransaction();
    foreach ($ids as $id) {
        $stmtSelect->execute([$id]);
        $scholar = $stmtSelect->fetch();
        if (!$scholar) continue;
        $title = ($scholar['name'] ?? 'Scholar') . ' (' . ($scholar['student_id'] ?? '') . ')';
        $stmtTrash->execute(['scholar', $id, $title, json_encode($scholar), 'Registrar Staff']);

        // Only the Scholars entry is removed. The student's records, applications and grades stay:
        // they can hold another scholarship, and a Merit-based scholar was never evaluated here.
        $stmtDelete->execute([$id]);
        $titles[$id] = $title;
    }
    $pdo->commit();

    if (count($titles) === 1) {
        logActivity($pdo, 'Scholar Deleted', 'Scholars', reset($titles) . ' was moved to Trash Bin.', (int)array_key_first($titles));
    } elseif ($titles) {
        $preview = implode(', ', array_slice($titles, 0, 10)) . (count($titles) > 10 ? ', and ' . (count($titles) - 10) . ' more' : '');
        logActivity($pdo, 'Scholar Deleted', 'Scholars', count($titles) . ' scholars were moved to Trash Bin: ' . $preview . '.');
    }

    $n = count($titles);
    sendJson(['success' => true, 'deleted' => $n, 'message' => $bulk ? "$n scholar" . ($n === 1 ? '' : 's') . ' moved to Trash Bin.' : 'Scholar entry moved to Trash Bin.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    sendError($e->getMessage(), 500);
}
