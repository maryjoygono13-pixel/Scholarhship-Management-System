<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/recipients.php';

try {
    $pdo = getDB();
    $segment = trim($_GET['segment'] ?? 'all');

    $data = resolveRecipients($pdo, 'segment', $segment);

    sendJson([
        'success' => true,
        'data' => $data,
        'count' => count($data)
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
