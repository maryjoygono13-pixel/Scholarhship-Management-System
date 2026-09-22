<?php
/*
 * Who a notification goes to. Shared by api/get_recipients.php (the count shown
 * in the compose dialog) and api/send_notification.php (the actual send), so the
 * number the registrar sees is the number of people who get the email.
 */

function resolveRecipients(PDO $pdo, string $mode, string $segment, string $individualId = ''): array {
    if ($mode === 'individual') {
        if ($individualId === '') {
            return [];
        }
        $stmt = $pdo->prepare("SELECT * FROM applicants WHERE id = ?");
        $stmt->execute([$individualId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $pending = "LOWER(status) IN ('pending', 'review', 'interview')";
        switch ($segment) {
            case 'missing_docs':
            case 'missing_req':
                $where = "$pending AND docs_complete = 0";
                break;
            case 'pending':
                $where = "LOWER(status) = 'pending'";
                break;
            case 'evaluation':
                $where = "LOWER(status) IN ('review', 'interview')";
                break;
            case 'approved':
            case 'renewal':
                $where = "LOWER(status) = 'approved'";
                break;
            case 'rejected':
                $where = "LOWER(status) = 'rejected'";
                break;
            case 'at_risk':
                $where = "(gwa > 2.0 OR failing_grades > 0)";
                break;
            case 'all':
            default:
                $where = "1=1";
        }
        $rows = $pdo->query("SELECT * FROM applicants WHERE $where")->fetchAll(PDO::FETCH_ASSOC);
    }

    return array_map(function ($a) {
        return [
            'id' => (int)$a['id'],
            'studentId' => $a['student_id'] ?? '',
            'name' => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
            'email' => trim((string)($a['email'] ?? '')),
            'status' => $a['status'] ?? '',
        ];
    }, $rows);
}
