<?php
require_once __DIR__ . '/init.php';

try {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        sendError('Invalid scholar ID.');
    }

    $pdo = getDB();

    $stmtSelect = $pdo->prepare("SELECT * FROM scholars WHERE id = ?");
    $stmtSelect->execute([$id]);
    $scholar = $stmtSelect->fetch();

    if ($scholar) {
        $title = ($scholar['name'] ?? 'Scholar') . ' (' . ($scholar['student_id'] ?? '') . ')';
        $stmtTrash = $pdo->prepare("INSERT INTO deleted_items (item_type, item_id, title, item_data, deleted_by) VALUES (?, ?, ?, ?, ?)");
        $stmtTrash->execute(['scholar', $id, $title, json_encode($scholar), 'Registrar Staff']);

        // Only the Scholars entry is removed. The student's records, applications and grades stay:
        // they can hold another scholarship, and a Merit-based scholar was never evaluated here.

        $stmt = $pdo->prepare("DELETE FROM scholars WHERE id = ?");
        $stmt->execute([$id]);

        logActivity($pdo, 'Scholar Deleted', 'Scholars', $title . ' was moved to Trash Bin.', $id);
    }

    sendJson(['success' => true, 'message' => 'Scholar entry moved to Trash Bin.']);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
