<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/records_helper.php';
require_once __DIR__ . '/../includes/enrollment_verification_helper.php';   // evSplitFullName()
require_once __DIR__ . '/../includes/deans_list_helper.php';               // "Dean's Lister" filter

try {
    $pdo = getDB();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $studentId = trim($_POST['student_id'] ?? $_POST['studentId'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $type = normalizeScholarshipType($pdo, trim($_POST['scholarship_type'] ?? $_POST['scholarshipType'] ?? 'Academic Merit'));
        $status = trim($_POST['status'] ?? 'approved');
        // The semester and school year come from the Active Term (Settings), never from
        // the form. Editing a record leaves its term alone; a new record gets the active one.
        $semester = getActiveSemester($pdo);
        $sy = getActiveSchoolYear($pdo);
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($name) || empty($studentId)) {
            sendError('Student ID and Name are required.');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE records SET student_id = ?, name = ?, scholarship_type = ?, status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$studentId, $name, $type, $status, $remarks, $id]);
            logActivity($pdo, 'Record Updated', 'Records', $name . ' (Student ID: ' . $studentId . ') record was updated.', $id);
            sendJson(['success' => true, 'id' => $id, 'message' => 'Record updated successfully.']);
        } else {
            $saved = saveTermRecord($pdo, ['student_id' => $studentId, 'name' => $name, 'scholarship_type' => $type, 'status' => $status, 'semester' => $semester, 'sy' => $sy, 'remarks' => $remarks, 'origin' => 'evaluation']);
            if (!$saved['created']) {
                sendError("$name already has a $type record for $semester $sy. Edit that record instead.", 409);
            }
            $newId = $saved['id'];
            logActivity($pdo, 'Record Added', 'Records', $name . ' (Student ID: ' . $studentId . ') record was added.', $newId);
            sendJson(['success' => true, 'id' => $newId, 'message' => 'Record created successfully.']);
        }
    }
    $statusParam = trim($_GET['status'] ?? '');
    $typeParam = trim($_GET['type'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $query = "SELECT * FROM records WHERE 1=1";
    $params = [];

    if ($statusParam !== '' && strtolower($statusParam) !== 'all status' && strtolower($statusParam) !== 'all') {
        $query .= " AND LOWER(status) = LOWER(?)";
        $params[] = $statusParam;
    }

    if ($typeParam !== '' && strtolower($typeParam) !== 'all scholarship types' && strtolower($typeParam) !== 'all') {
        $query .= " AND LOWER(scholarship_type) = LOWER(?)";
        $params[] = $typeParam;
    }

    if ($search !== '') {
        $query .= " AND (name LIKE ? OR student_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Look up the applicant behind each record (if any) for richer academic details.
    $appStmt = $pdo->prepare("
        SELECT id, birthdate, age, first_name, middle_name, last_name, program, major, year_level, semester, gwa, gwa_req, failing_grades, units, enrolled, docs_complete, scholarship_type, transcript_file, coe_file, good_moral_file
        FROM applicants
        WHERE id = ? OR student_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $gradeStmt = $pdo->prepare("SELECT subject_code, grade FROM student_grades WHERE student_id = ?");
    // No application behind the record (e.g. a Dean's Lister from the Registrar scan): program and
    // year level come from their Scholars entry instead, or else from the Registrar's database.
    $scholarStmt = $pdo->prepare("SELECT department, year_level FROM scholars WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $registrarStudent = function (string $studentId): ?array {
        static $stmt = null, $failed = false;
        if ($failed) return null;
        try {
            $stmt ??= getRegistrarDB()->prepare('SELECT program, year_level FROM ' . REGISTRAR_STUDENTS_TABLE . ' WHERE student_id = ?');
            $stmt->execute([trim($studentId)]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $failed = true;   // Registrar's database unreachable: leave the fields blank
            return null;
        }
    };

    // Each semester's own GWA, so a record shows the GWA of ITS semester (a 1st Semester
    // record must not pick up the student's 2nd Semester grades).
    $semesterStats = getSemesterGradeStats($pdo, array_column($rows, 'student_id'));

    // Records whose scholar was already sent on to Renewal & Retention for that same term.
    $sentToRenewal = [];   // term key => the renewal decision so far (pending / eligible / at-risk / terminated)
    foreach ($pdo->query("SELECT student_id, school_year, semester, scholarship_type, status FROM renewal_retention ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $ren) {
        $sentToRenewal[trim($ren['student_id']) . '|' . trim($ren['school_year']) . '|' . normalizeSemesterName($ren['semester']) . '|' . strtolower(trim((string)$ren['scholarship_type']))] = strtolower(trim((string)$ren['status']));
    }

    $ageUpdate = $pdo->prepare("UPDATE applicants SET age = ? WHERE id = ?");

    $data = array_map(function($r) use ($appStmt, $gradeStmt, $semesterStats, $sentToRenewal, $pdo, $ageUpdate, $scholarStmt, $registrarStudent) {
        $appStmt->execute([(int)($r['applicant_id'] ?? 0), $r['student_id']]);
        $app = $appStmt->fetch();
        $scholarRow = null;
        if (!$app) {
            $scholarStmt->execute([$r['student_id']]);
            $scholarRow = $scholarStmt->fetch() ?: null;
        }
        // Scholars stores the department's display name ("B Elementary Education"), so map it back.
        $fallbackProgram = $scholarRow
            ? (resolveProgramName((string)$scholarRow['department']) ?? (array_search((string)$scholarRow['department'], PROGRAM_DISPLAY_NAMES, true) ?: (string)$scholarRow['department']))
            : null;
        $fallbackYear = $scholarRow && (int)$scholarRow['year_level'] > 0 ? ['', '1st Year', '2nd Year', '3rd Year', '4th Year'][min(4, (int)$scholarRow['year_level'])] : '';
        if (!$app && !$scholarRow && ($reg = $registrarStudent((string)$r['student_id']))) {
            $fallbackProgram = (string)$reg['program'];
            $fallbackYear = (string)$reg['year_level'];
        }

        // Age always follows the birthdate, so it goes up on the scholar's birthday. The
        // stored applicants.age is brought up to date too whenever it has fallen behind.
        $age = $app ? computeAge($app['birthdate'] ?? '') : null;
        if ($age !== null && ($app['age'] === null || (int)$app['age'] !== $age)) {
            $ageUpdate->execute([$age, (int)$app['id']]);
        }

        $recordSem = normalizeSemesterName($r['semester']);
        $termStats = $semesterStats[(string)$r['student_id']][$recordSem] ?? null;
        $recordGwa = null;
        $recordFailing = null;
        if ($termStats) {
            $recordGwa = $termStats['gwa'];
            $recordFailing = $termStats['failing'];
        } elseif ($app && $recordSem === normalizeSemesterName($app['semester'] ?? '') && (float)$app['gwa'] > 0) {
            // No imported grades, but a GWA already stored for this same semester.
            $recordGwa = (float)$app['gwa'];
            $recordFailing = (int)$app['failing_grades'];
        }

        $gradeStmt->execute([$r['student_id']]);
        $grades = [];
        foreach ($gradeStmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $grades[$g['subject_code']] = (float)$g['grade'];
        }

        // The record's own name always wins for display — it's what the Edit Record form shows
        // and saves, so whatever a registrar types there must be what shows up here. (This used to
        // prefer the linked applicant's structured name for a middle initial, but that meant an
        // edited record name never actually appeared, which was the wrong tradeoff.)
        $shortName = $r['name'];

        // [surname, first name] for A–Z sorting and the export.
        [$splitLast, $splitFirst] = evSplitFullName((string)$r['name']);
        $appLast = trim((string)($app['last_name'] ?? ''));
        $surnameParts = ($appLast !== '' && stripos(' ' . $r['name'] . ' ', ' ' . $appLast . ' ') !== false)
            ? [$appLast, trim((string)($app['first_name'] ?? '')) ?: $splitFirst]
            : [$splitLast, $splitFirst];
        $fullName = $r['name'];

        // Each record shows its own term (a renewal decision is a record of its own for its term), and
        // the program / year level saved with it — the student's at that time — before today's.
        $displaySemester = normalizeSemesterName($r['semester']);
        $displaySy = $r['sy'];
        $programForDept = trim((string)($r['program'] ?? '')) !== '' ? (string)$r['program'] : ($app['program'] ?? $fallbackProgram ?? '');
        $yearForRecord = trim((string)($r['year_level'] ?? '')) !== '' ? (string)$r['year_level'] : ($app['year_level'] ?? $fallbackYear);
        $canonicalProgram = resolveProgramName((string)$programForDept);

        return [
            'id' => (int)$r['id'],
            'studentId' => $r['student_id'],
            'student_id' => $r['student_id'],
            'name' => $shortName,
            'fullName' => $fullName,
            // The record's own name as stored in `records`, straight from the row — used to
            // pre-fill the Edit form so saving never overwrites it with a same-Student-ID
            // applicant's (possibly different/mismatched) name.
            'recordName' => $r['name'],
            // Surname / first name for sorting (A–Z by surname) and the export: the applicant's own
            // last name when it's part of the record name, otherwise read from the record name.
            'lastName' => $surnameParts[0],
            'firstName' => $surnameParts[1],
            'age' => $age,
            'birthdate' => $app['birthdate'] ?? '',
            'scholarshipType' => $r['scholarship_type'],
            'scholarship_type' => $r['scholarship_type'],
            'status' => $r['status'],
            'semester' => $r['semester'],
            // The Semester column follows the Active Semester in Settings > Portal
            // Configuration live, the same way Renewal & Retention's does — not the term the
            // record was originally evaluated in (that's still `semester`, used for matching).
            'currentSemester' => $displaySemester,
            'displaySy' => $displaySy,
            // Renewed in Renewal & Retention (shown as "Renewed"; still an approved record).
            'renewed' => ($r['origin'] ?? '') === 'renewal' && strtolower(trim((string)$r['status'])) === 'approved',
            // Terminated in Renewal & Retention (shown as "Terminated"; a rejected record).
            'terminated' => ($r['origin'] ?? '') === 'renewal' && strtolower(trim((string)$r['status'])) === 'rejected',
            'origin' => $r['origin'] ?? 'evaluation',
            'department' => $canonicalProgram !== null ? (PROGRAM_DISPLAY_NAMES[$canonicalProgram] ?? $canonicalProgram) : (string)$programForDept,
            'sy' => $r['sy'],
            'renewalStatus' => strtolower(trim((string)$r['status'])) === 'rejected' ? null : ($sentToRenewal[trim($r['student_id']) . '|' . trim($r['sy']) . '|' . $recordSem . '|' . strtolower(trim((string)$r['scholarship_type']))] ?? null),
            'sentToRenewal' => strtolower(trim((string)$r['status'])) !== 'rejected' && isset($sentToRenewal[trim($r['student_id']) . '|' . trim($r['sy']) . '|' . $recordSem . '|' . strtolower(trim((string)$r['scholarship_type']))]),
            'dateEvaluated' => $r['date_evaluated'],
            'date_evaluated' => $r['date_evaluated'],
            'remarks' => $r['remarks'],
            'program' => $programForDept !== '' ? $programForDept : null,
            'programCode' => programAcronym($programForDept),
            'major' => $app['major'] ?? '',
            'yearLevel' => $yearForRecord,
            'gwa' => $recordGwa,
            'semesterGwa' => [
                'first' => $semesterStats[(string)$r['student_id']]['1st Semester']['gwa'] ?? null,
                'second' => $semesterStats[(string)$r['student_id']]['2nd Semester']['gwa'] ?? null,
                'summer' => $semesterStats[(string)$r['student_id']]['Summer Term']['gwa'] ?? null,
            ],
            'gwaReq' => $app ? (float)$app['gwa_req'] : null,
            // Newest graded semester: GWA 1.50 or better and no subject grade of 2.00 or worse.
            // Dean's Lister badge for THIS record: its own semester's GWA 1.50 or better and no subject
            // grade of 2.00 or worse (any scholarship type, e.g. a CMSP scholar who is also a Dean's Lister).
            'deansLister' => $termStats !== null && deansListQualifies($termStats),
            'failingGrades' => $recordFailing,
            'units' => $app ? (int)$app['units'] : null,
            'enrolled' => $app ? (bool)$app['enrolled'] : null,
            'docsComplete' => $app ? (bool)$app['docs_complete'] : null,
            // The documents the applicant submitted for this record's scholarship (same list Evaluation shows).
            'documents' => $app ? evaluateApplicantDocuments($pdo, array_merge($app, ['scholarship_type' => $r['scholarship_type']])) : [],
            'grades' => (object)$grades,
        ];
    }, $rows);

    sendJson(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
