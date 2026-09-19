<?php
require_once __DIR__ . '/init.php';

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendError('Invalid file ID.');
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM imported_files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        sendError('Imported file record not found.', 404);
    }

    if (empty($file['stored_path'])) {
        sendError('The original file for this import is not available for download.', 404);
    }

    $fullPath = __DIR__ . '/../' . $file['stored_path'];

    if (!is_file($fullPath)) {
        sendError('The original file could not be found on the server.', 404);
    }

    $safeName = preg_replace('/[^\w.\-]+/', '_', basename($file['file_name']));

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('X-Content-Type-Options: nosniff');

    readfile($fullPath);
    exit();
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
