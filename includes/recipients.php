<?php
/*
 * Who a notification goes to. Shared by api/get_recipients.php (the list/count shown
 * in the compose dialog) and api/send_notification.php (the actual send), so the
 * people the registrar sees are exactly the people who get the email.
 *
 * Individual recipients come from different tables depending on the notification
 * type, since each type is about a different kind of person:
 *   - Missing requirements -> Applicants who are missing a document (still pending).
 *   - Renewal deadline      -> Renewal & Retention entries due for renewal.
 *   - Failed retention      -> Renewal & Retention entries that failed retention.
 *   - Approval status       -> Records (an applicant's approved/rejected decision).
 * None of those tables store an email address directly, so it's resolved back to
 * the matching Applicants row.
 */

/** Email for a student, preferring the exact applicant record when known. */
function resolveEmailForStudent(PDO $pdo, string $studentId, ?int $applicantId = null): string {
    if ($applicantId) {
        $stmt = $pdo->prepare("SELECT email FROM applicants WHERE id = ?");
        $stmt->execute([$applicantId]);
        $email = $stmt->fetchColumn();
        if ($email) {
            return trim((string)$email);
        }
    }
    if ($studentId === '') {
        return '';
    }
    // Same student may have more than one applicant row (different scholarship /
    // semester); the most recent one is the most likely to have a current email.
    $stmt = $pdo->prepare("SELECT email FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$studentId]);
    return trim((string)($stmt->fetchColumn() ?: ''));
}

/** Notification type -> which table its individual recipients come from. */
function individualKindForType(string $notifType): string {
    switch ($notifType) {
        case 'renewal_deadline':
        case 'failed_retention':
            return 'renewal';
        case 'approval_status':
            return 'record';
        case 'missing_requirements':
        default:
            return 'applicant';
    }
}

/**
 * The people who can be picked in "Individual" mode for a given notification type.
 * Each item: id (that source row's id — pair it with 'kind' when sending), studentId, name.
 */
function listIndividualRecipients(PDO $pdo, string $notifType): array {
    $kind = individualKindForType($notifType);

    if ($kind === 'renewal') {
        // Renewal deadline = due for renewal; Failed retention = fell short of it.
        $statuses = $notifType === 'failed_retention' ? ['at-risk', 'terminated'] : ['eligible', 'pending'];
        $in = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $pdo->prepare("SELECT id, student_id, name FROM renewal_retention WHERE LOWER(status) IN ($in) ORDER BY name");
        $stmt->execute($statuses);
    } elseif ($kind === 'record') {
        $stmt = $pdo->prepare("SELECT id, student_id, name FROM records WHERE LOWER(status) IN ('approved', 'rejected') ORDER BY name");
        $stmt->execute();
    } else {
        $pending = "LOWER(status) IN ('pending', 'review', 'interview')";
        $stmt = $pdo->query("SELECT id, student_id, first_name, last_name FROM applicants WHERE $pending AND docs_complete = 0 ORDER BY last_name, first_name");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function ($r) use ($kind) {
        return [
            'id' => (int)$r['id'],
            'kind' => $kind,
            'studentId' => $r['student_id'] ?? '',
            'name' => $kind === 'applicant'
                ? trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))
                : trim((string)($r['name'] ?? '')),
        ];
    }, $rows);
}

function resolveRecipients(PDO $pdo, string $mode, string $segment, string $individualId = '', string $individualKind = 'applicant'): array {
    if ($mode === 'individual') {
        if ($individualId === '') {
            return [];
        }

        if ($individualKind === 'renewal') {
            $stmt = $pdo->prepare("SELECT * FROM renewal_retention WHERE id = ?");
            $stmt->execute([$individualId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$r) {
                return [];
            }
            return [[
                'id' => (int)$r['id'],
                'studentId' => $r['student_id'] ?? '',
                'name' => trim((string)$r['name']),
                'email' => resolveEmailForStudent($pdo, (string)($r['student_id'] ?? '')),
                'status' => $r['status'] ?? '',
            ]];
        }

        if ($individualKind === 'record') {
            $stmt = $pdo->prepare("SELECT * FROM records WHERE id = ?");
            $stmt->execute([$individualId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$r) {
                return [];
            }
            $applicantId = isset($r['applicant_id']) && $r['applicant_id'] !== null ? (int)$r['applicant_id'] : null;
            return [[
                'id' => (int)$r['id'],
                'studentId' => $r['student_id'] ?? '',
                'name' => trim((string)$r['name']),
                'email' => resolveEmailForStudent($pdo, (string)($r['student_id'] ?? ''), $applicantId),
                'status' => $r['status'] ?? '',
            ]];
        }

        $stmt = $pdo->prepare("SELECT * FROM applicants WHERE id = ?");
        $stmt->execute([$individualId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $pending = "LOWER(status) IN ('pending', 'review')";
        switch ($segment) {
            case 'missing_docs':
            case 'missing_req':
                $where = "$pending AND docs_complete = 0";
                break;
            case 'pending':
                $where = "LOWER(status) = 'pending'";
                break;
            case 'evaluation':
                $where = "LOWER(status) = 'review'";
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
