<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();
    $typeParam = trim($_GET['type'] ?? '');

    $query = "SELECT * FROM notifications WHERE 1=1";
    $params = [];

    if ($typeParam !== '' && strtolower($typeParam) !== 'all') {
        $query .= " AND type = ?";
        $params[] = $typeParam;
    }

    $query .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $data = array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'type' => $r['type'],
            'recipientType' => $r['recipient_type'],
            'recipient_type' => $r['recipient_type'],
            'recipientId' => $r['recipient_id'],
            'recipient_id' => $r['recipient_id'],
            'recipientName' => $r['recipient_name'],
            'recipient_name' => $r['recipient_name'],
            'recipientEmail' => $r['recipient_email'],
            'recipient_email' => $r['recipient_email'],
            'subject' => $r['subject'],
            'message' => $r['message'],
            'deadline' => $r['deadline'],
            'status' => $r['status'],
            'sentAt' => $r['sent_at'],
            'sent_at' => $r['sent_at'],
            'createdAt' => $r['sent_at'],
            'errorMessage' => $r['error_message'] ?? '',
            'gmailAccount' => $r['gmail_account'] ?? '',
            'threadId' => $r['gmail_thread_id'] ?? ''
        ];
    }, $rows);

    // Calculate Summary stats
    $todayCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE DATE(sent_at) = UTC_DATE()")->fetchColumn();
    $missingCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'missing_requirements'")->fetchColumn();
    $renewalCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'renewal_deadline'")->fetchColumn();
    $failedCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE status = 'failed' OR type = 'failed_retention'")->fetchColumn();

    sendJson([
        'success' => true,
        'data' => $data,
        'summary' => [
            'sent_today' => $todayCount,
            'missing_req' => $missingCount,
            'renewal' => $renewalCount,
            'failed' => $failedCount
        ]
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
