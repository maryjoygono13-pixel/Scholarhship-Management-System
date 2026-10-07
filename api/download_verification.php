<?php
/*
 * Downloads a CHED enrollment list result file (the list with the Registrar's answer on each
 * row). Registrar sign-in required; the files live in storage/, which isn't publicly reachable.
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/enrollment_verification_helper.php';

try {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = getDB()->prepare("SELECT file_name, result_path FROM enrollment_verifications WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $path = $row && $row['result_path'] ? evStorageDir() . '/' . basename($row['result_path']) : null;
    if (!$path || !is_file($path)) {
        sendError('That result file is no longer available.', 404);
    }

    $base = preg_replace('/[^A-Za-z0-9 _\-]+/', '', pathinfo($row['file_name'], PATHINFO_FILENAME)) ?: 'CHED list';
    $downloadName = $base . ' - enrollment check.xlsx';
    header_remove('Content-Type');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit();
} catch (Throwable $e) {
    sendError($e->getMessage(), 500);
}
