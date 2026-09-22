<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/recipients.php';

try {
    $pdo = getDB();
    $mode = trim($_GET['mode'] ?? 'segment');

    if ($mode === 'individual') {
        // Who can be picked depends on the notification type — see includes/recipients.php.
        $type = trim($_GET['type'] ?? '');
        $data = listIndividualRecipients($pdo, $type);

        sendJson([
            'success' => true,
            'data' => $data,
            'count' => count($data)
        ]);
    }

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
