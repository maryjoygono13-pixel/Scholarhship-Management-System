<?php
require_once __DIR__ . '/init.php';

// Inbox of messages received from scholars / applicants.
//   GET                       -> list (+ unread count); ?filter=unread, ?q=search
//   POST action=create        -> store a received message (staff entry, or any
//                                form / webhook that posts sender_* + message)
//   POST action=mark_read | mark_unread | mark_all_read | delete

try {
    $pdo = getDB();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = trim($_POST['action'] ?? 'create');
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'create') {
            $name = trim($_POST['sender_name'] ?? '');
            $email = trim($_POST['sender_email'] ?? '');
            $studentId = trim($_POST['student_id'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $source = trim($_POST['source'] ?? 'manual');

            if ($name === '' && $email === '') {
                sendError('Please enter who the message is from.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendError('Please enter a valid email address.');
            }
            if ($message === '') {
                sendError('The message cannot be empty.');
            }
            if ($subject === '') {
                $subject = '(No subject)';
            }
            if (!in_array($source, ['manual', 'form', 'email'], true)) {
                $source = 'manual';
            }

            $stmt = $pdo->prepare("INSERT INTO inbox_messages (sender_name, sender_email, student_id, subject, message, source) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $studentId, $subject, $message, $source]);
            $newId = (int)$pdo->lastInsertId();

            logActivity($pdo, 'Message Received', 'Notifications', 'Message "' . $subject . '" received from ' . ($name !== '' ? $name : $email) . '.', $newId);
            sendJson(['success' => true, 'id' => $newId, 'message' => 'Message saved to inbox.']);
        }

        if ($action === 'mark_all_read') {
            $pdo->exec("UPDATE inbox_messages SET is_read = 1 WHERE is_read = 0");
            sendJson(['success' => true]);
        }

        if ($id <= 0) {
            sendError('Invalid message.');
        }

        if ($action === 'mark_read' || $action === 'mark_unread') {
            $stmt = $pdo->prepare("UPDATE inbox_messages SET is_read = ? WHERE id = ?");
            $stmt->execute([$action === 'mark_read' ? 1 : 0, $id]);
            sendJson(['success' => true]);
        }

        if ($action === 'delete') {
            $sel = $pdo->prepare("SELECT subject, sender_name FROM inbox_messages WHERE id = ?");
            $sel->execute([$id]);
            $row = $sel->fetch();
            $pdo->prepare("DELETE FROM inbox_messages WHERE id = ?")->execute([$id]);
            if ($row) {
                logActivity($pdo, 'Message Deleted', 'Notifications', 'Inbox message "' . $row['subject'] . '" from ' . $row['sender_name'] . ' was deleted.', $id);
            }
            sendJson(['success' => true]);
        }

        sendError('Unknown action.');
    }

    $filter = trim($_GET['filter'] ?? '');
    $q = trim($_GET['q'] ?? '');

    $sql = "SELECT * FROM inbox_messages WHERE 1=1";
    $params = [];
    if ($filter === 'unread') {
        $sql .= " AND is_read = 0";
    }
    if ($q !== '') {
        $sql .= " AND (sender_name LIKE ? OR sender_email LIKE ? OR subject LIKE ? OR message LIKE ? OR student_id LIKE ?)";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $data = array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'senderName' => $r['sender_name'],
            'senderEmail' => $r['sender_email'],
            'studentId' => $r['student_id'],
            'subject' => $r['subject'],
            'message' => $r['message'],
            'source' => $r['source'],
            'isRead' => (int)$r['is_read'] === 1,
            'receivedAt' => $r['received_at'],
        ];
    }, $stmt->fetchAll());

    $unread = (int)$pdo->query("SELECT COUNT(*) FROM inbox_messages WHERE is_read = 0")->fetchColumn();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM inbox_messages")->fetchColumn();

    sendJson(['success' => true, 'data' => $data, 'unread' => $unread, 'total' => $total]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
