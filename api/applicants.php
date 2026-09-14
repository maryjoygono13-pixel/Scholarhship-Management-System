<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Extract ID if passed via URI path or GET parameter
    $id = (int)($_GET['id'] ?? 0);

    if (!$id && isset($_SERVER['PATH_INFO'])) {
        $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));

        if (isset($parts[0]) && is_numeric($parts[0])) {
            $id = (int)$parts[0];
        }
    }

    /*
     * =========================================================
     * GET
     * =========================================================
     *
     * Only return applicants that are still being processed.
     *
     * pending   = newly submitted
     * review    = being evaluated
     * interview = for interview
     *
     * Approved and rejected applicants are excluded.
     */

    if ($method === 'GET') {

        $stmt = $pdo->query("
            SELECT *
            FROM applicants
            WHERE LOWER(status) IN ('pending', 'review', 'interview')
            ORDER BY id DESC
        ");

        $rows = $stmt->fetchAll();

        $data = array_map(function ($r) {
            return [
                'id' => (string)$r['id'],

                'name' => trim(
                    $r['first_name'] . ' ' . $r['last_name']
                ),

                'studentId' => $r['student_id'],
                'program' => $r['program'],
                'major' => $r['major'] ?? '',
                'yearLevel' => $r['year_level'],
                'type' => $r['scholarship_type'],

                'gwa' => (float)$r['gwa'],
                'gwaReq' => (float)$r['gwa_req'],

                'failingGrades' => (int)$r['failing_grades'],
                'units' => (int)$r['units'],

                'enrolled' => (bool)$r['enrolled'],
                'docsComplete' => (bool)$r['docs_complete'],

                'status' => $r['status'],
                'remarks' => $r['remarks'] ?? ''
            ];
        }, $rows);

        sendJson($data);

    /*
     * =========================================================
     * PATCH / POST
     * =========================================================
     *
     * Used by the Evaluation page to change:
     *
     * pending → review
     * review → interview
     * interview → approved
     * interview → rejected
     *
     * The applicant is NOT deleted.
     */

    } elseif ($method === 'PATCH' || $method === 'POST') {

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?? $_POST;

        if (!$id && isset($payload['id'])) {
            $id = (int)$payload['id'];
        }

        if ($id <= 0) {
            sendError('Applicant ID missing.');
        }

        $status = $payload['status'] ?? null;
        $remarks = $payload['remarks'] ?? null;

        $updates = [];
        $params = [];

        if ($status !== null) {
            $updates[] = "status = ?";
            $params[] = $status;
        }

        if ($remarks !== null) {
            $updates[] = "remarks = ?";
            $params[] = $remarks;
        }

        if (empty($updates)) {
            sendJson([
                'success' => true,
                'message' => 'No changes requested.'
            ]);
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "
            UPDATE applicants
            SET " . implode(', ', $updates) . "
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($sql);
$stmt->execute($params);

/*
 * If applicant is approved or rejected,
 * create an evaluation record.
 */
if ($status !== null && in_array(strtolower($status), ['approved', 'rejected'])) {

    // Get the updated applicant
    $stmt = $pdo->prepare("
        SELECT
            student_id,
            first_name,
            last_name,
            scholarship_type,
            status,
            remarks
        FROM applicants
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    $applicant = $stmt->fetch();

    if ($applicant) {

        $name = trim(
            $applicant['first_name'] . ' ' . $applicant['last_name']
        );

        /*
         * Prevent duplicate records if the same applicant
         * is accidentally approved/rejected again.
         */
        $check = $pdo->prepare("
            SELECT id
            FROM records
            WHERE student_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $check->execute([$applicant['student_id']]);

        $existingRecord = $check->fetch();

        if (!$existingRecord) {

            $insert = $pdo->prepare("
                INSERT INTO records
                (
                    applicant_id,
                    student_id,
                    name,
                    scholarship_type,
                    status,
                    semester,
                    sy,
                    date_evaluated,
                    remarks
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, DATE('now'), ?
                )
            ");

            $insert->execute([
                $id,
                $applicant['student_id'],
                $name,
                $applicant['scholarship_type'],
                strtolower($applicant['status']),
                'First Semester',
                '2025-2026',
                $applicant['remarks'] ?? ''
            ]);
        }
    }
}

sendJson([
    'success' => true,
    'message' => 'Applicant evaluation saved.'
]);

    } else {

        sendError('Method not allowed.', 405);
    }

} catch (Exception $e) {

    sendError($e->getMessage(), 500);
}

