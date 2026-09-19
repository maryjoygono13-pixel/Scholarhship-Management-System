<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();
    $type = trim($_GET['type'] ?? '');

    $sql = "SELECT * FROM imported_files";
    $params = [];
    if (!empty($type)) {
        $sql .= " WHERE file_type = ?";
        $params[] = $type;
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $data = array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'fileType' => $r['file_type'],
            'fileName' => $r['file_name'],
            'fileSize' => (int)$r['file_size'],
            'recordsCount' => (int)$r['records_count'],
            'importedBy' => $r['imported_by'] ?? 'Registrar Staff',
            'status' => $r['status'] ?? 'Active',
            'hasFile' => !empty($r['stored_path']) && is_file(__DIR__ . '/../' . $r['stored_path']),
            'createdAt' => $r['created_at'],
            'formattedDate' => date('M j, Y h:i A', strtotime($r['created_at']))
        ];
    }, $rows);

    sendJson(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
