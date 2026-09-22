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
     *
     * Approved and rejected applicants are excluded.
     */

    if ($method === 'GET') {

        $stmt = $pdo->query("
            SELECT *
            FROM applicants
            WHERE LOWER(status) IN ('pending', 'review')
            ORDER BY id DESC
        ");

        $rows = $stmt->fetchAll();

        $gradesMap = [];
        $studentIds = array_column($rows, 'student_id');
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $gradeStmt = $pdo->prepare("SELECT student_id, subject_code, grade FROM student_grades WHERE student_id IN ($placeholders)");
            $gradeStmt->execute($studentIds);
            foreach ($gradeStmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $gradesMap[$g['student_id']][$g['subject_code']] = (float)$g['grade'];
            }
        }

        // Each semester's own GWA / failing count, from the imported academic records.
        $semesterStats = getSemesterGradeStats($pdo, $studentIds);

        $data = array_map(function ($r) use ($gradesMap, $pdo, $semesterStats) {
            $stats = $semesterStats[(string)$r['student_id']] ?? [];
            $currentSem = normalizeSemesterName($r['semester'] ?? '');
            // Scholars with no grades on file yet but a stored GWA (entered before
            // grades came from imports) keep it under their own semester.
            $semesterGwa = function (string $sem) use ($stats, $currentSem, $r) {
                if (isset($stats[$sem])) return $stats[$sem]['gwa'];
                if ($sem === $currentSem && (float)$r['gwa'] > 0) return (float)$r['gwa'];
                return null;
            };

            return [
                'id' => (string)$r['id'],

                'name' => buildShortName($r['first_name'], $r['middle_name'] ?? '', $r['last_name']),
                'fullName' => buildFullName($r['first_name'], $r['middle_name'] ?? '', $r['last_name']),

                'studentId' => $r['student_id'],
                'program' => $r['program'],
                'major' => $r['major'] ?? '',
                'yearLevel' => $r['year_level'],
                'semester' => $r['semester'] ?? '1st Semester',
                'type' => $r['scholarship_type'],

                'gwa' => (float)$r['gwa'],
                'semesterGwa' => [
                    'first' => $semesterGwa('1st Semester'),
                    'second' => $semesterGwa('2nd Semester'),
                    'summer' => $semesterGwa('Summer Term'),
                ],
                'gwaReq' => resolveGwaRequirement($pdo, (string)$r['scholarship_type'], (float)$r['gwa_req']),

                'failingGrades' => (int)$r['failing_grades'],
                'units' => (int)$r['units'],

                'enrolled' => (bool)$r['enrolled'],
                'docsComplete' => (bool)$r['docs_complete'],

                // Shown as non-compliant here in Evaluation only (GWA requirement not met); the
                // applicant's real status is untouched, so the Applicants page keeps showing it.
                'status' => isGwaRequirementUnmet($pdo, $r) ? STATUS_NON_COMPLIANT : $r['status'],
                'remarks' => $r['remarks'] ?? '',

                'grades' => (object)($gradesMap[$r['student_id']] ?? [])
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
     * review → approved
     * review → rejected
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

        // An applicant can only be approved if their GWA meets the requirement
        // of the scholarship type/sub-type they applied for.
        if ($status !== null && strtolower($status) === 'approved') {
            $chk = $pdo->prepare("SELECT gwa, gwa_req, scholarship_type FROM applicants WHERE id = ?");
            $chk->execute([$id]);
            $cand = $chk->fetch();
            if ($cand) {
                $required = resolveGwaRequirement($pdo, (string)$cand['scholarship_type'], (float)$cand['gwa_req']);
                if ((float)$cand['gwa'] <= 0) {
                    sendError('Cannot approve: no grades are recorded for the current semester yet. Import the academic records in Data Management first.', 422);
                }
                if ((float)$cand['gwa'] > $required) {
                    sendError('Cannot approve: GWA ' . number_format((float)$cand['gwa'], 2) . ' does not meet the required ' . number_format($required, 2) . ' for ' . $cand['scholarship_type'] . '.', 422);
                }
            }
        }

        // "Non-compliant" is only a view in Evaluation, never a saved status. The page echoes it back
        // when saving remarks, so just leave the real status as it is.
        if ($status !== null && strtolower($status) === STATUS_NON_COMPLIANT) {
            $status = null;
        }

        // A student terminated from this scholarship can't be approved for it again.
        if ($status !== null && strtolower($status) === 'approved') {
            $chk = $pdo->prepare("SELECT student_id, scholarship_type FROM applicants WHERE id = ?");
            $chk->execute([$id]);
            $cand = $chk->fetch();
            if ($cand && findScholarshipTermination($pdo, (string)$cand['student_id'], (string)$cand['scholarship_type'])) {
                sendError(terminationBlockMessage((string)$cand['scholarship_type']), 422);
            }
        }

        // A non-compliant applicant (GWA requirement not met) can only be rejected, not moved forward.
        if ($status !== null && strtolower($status) === 'review') {
            $chk = $pdo->prepare("SELECT gwa, gwa_req, scholarship_type FROM applicants WHERE id = ?");
            $chk->execute([$id]);
            $cand = $chk->fetch();
            if ($cand && isGwaRequirementUnmet($pdo, $cand)) {
                sendError('This applicant is non-compliant (GWA requirement not met) and can only be rejected.', 422);
            }
        }

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

if ($status !== null) {
    $stmtWho = $pdo->prepare("SELECT student_id, first_name, last_name FROM applicants WHERE id = ?");
    $stmtWho->execute([$id]);
    $who = $stmtWho->fetch();
    $whoName = $who
        ? trim($who['first_name'] . ' ' . $who['last_name']) . ' (Student ID: ' . $who['student_id'] . ')'
        : ('Applicant #' . $id);

    $statusLower = strtolower($status);
    if ($statusLower === 'approved') {
        logActivity($pdo, 'Evaluation Approved', 'Evaluation', $whoName . ' was approved for their scholarship application.', $id);
        logActivity($pdo, 'Scholarship Assignment', 'Scholarships', $whoName . ' was assigned their scholarship after approval.', $id);
    } elseif ($statusLower === 'rejected') {
        logActivity($pdo, 'Evaluation Rejected', 'Evaluation', $whoName . ' was rejected for their scholarship application.', $id);
    } else {
        logActivity($pdo, 'Status Changed', 'Evaluation', $whoName . ' status changed to "' . $status . '".', $id);
    }
}

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
            middle_name,
            last_name,
            scholarship_type,
            status,
            remarks,
            semester,
            school_year
        FROM applicants
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    $applicant = $stmt->fetch();

    if ($applicant) {

        $name = buildFullName($applicant['first_name'], $applicant['middle_name'] ?? '', $applicant['last_name']);

        // The Record carries the same status as the decision (approved / rejected). Either way the
        // applicant then goes to Renewal & Retention as pending, locked until the next semester.
        $recordStatus = strtolower($applicant['status']) === 'approved' ? 'approved' : 'rejected';
        $recordSemester = normalizeSemesterName($applicant['semester'] ?: getActiveSemester($pdo));
        $recordSy = trim((string)$applicant['school_year']) !== '' ? trim($applicant['school_year']) : getActiveSchoolYear($pdo);

        /*
         * Prevent duplicate records if the same applicant is accidentally
         * approved/rejected twice in the SAME term for the SAME scholarship. A new term
         * gets its own record, and so does a second scholarship type (a student can hold
         * e.g. a CHED scholarship and the MERIT-BASED Academic one at the same time).
         */
        $check = $pdo->prepare("
            SELECT id, semester
            FROM records
            WHERE student_id = ? AND sy = ? AND LOWER(TRIM(scholarship_type)) = LOWER(TRIM(?))
            ORDER BY id DESC
        ");
        $check->execute([$applicant['student_id'], $recordSy, $applicant['scholarship_type']]);

        $existingRecord = false;
        foreach ($check->fetchAll(PDO::FETCH_ASSOC) as $existing) {
            if (normalizeSemesterName($existing['semester']) === $recordSemester) {
                $existingRecord = $existing;
                break;
            }
        }

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
                    ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?
                )
            ");

            $insert->execute([
                $id,
                $applicant['student_id'],
                $name,
                $applicant['scholarship_type'],
                $recordStatus,
                $recordSemester,
                $recordSy,
                $applicant['remarks'] ?? ''
            ]);

            // Approved and rejected applicants both enter Renewal & Retention as pending. The row
            // is view-only until the Active Semester moves on (see isRenewalLocked).
            $newRecord = $pdo->prepare("SELECT * FROM records WHERE id = ?");
            $newRecord->execute([(int)$pdo->lastInsertId()]);
            $recordRow = $newRecord->fetch(PDO::FETCH_ASSOC);
            if ($recordRow) {
                sendRecordToRenewal($pdo, $recordRow, 'pending', null, $recordStatus);
            }
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

