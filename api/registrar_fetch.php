<?php
/*
 * Fetch from Registrar Database (Data Management page).
 *
 *   GET            -> connection status of the Registrar's database (config/registrar_database.php)
 *   POST file=...  -> an Excel (.xlsx) or CSV list of students (Student ID + name, no grades).
 *                     Each student is looked up in the Registrar's database: their name is checked
 *                     against the file, and their program, year level and grades are fetched.
 *                     With save=1 (default) the grades are stored in this system's student_grades,
 *                     the same table grade imports fill, and GWAs are recalculated.
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';
require_once __DIR__ . '/../includes/enrollment_verification_helper.php';
require_once __DIR__ . '/../includes/enrollment_verification_helper.php';

/*
 * Makes the fetched student a pending applicant for $program (an SMS scholarships row), filled
 * from their Registrar record the same way the Apply page fills a new application. Re-fetching
 * updates their still-open application instead of adding a second one. Returns 'created'/'updated',
 * or 'full' when the program has no slot left (no new applicant is added then).
 */
function saveFetchedApplicant(PDO $pdo, array $stu, array $program): string {
    $studentId = (string)$stu['student_id'];
    $scholarshipType = normalizeScholarshipType($pdo, trim((string)$program['subtype']) !== '' ? $program['subtype'] : $program['name']);

    $town = resolveMunicipality((string)($stu['municipality'] ?? '')) ?? trim((string)($stu['municipality'] ?? ''));
    $barangay = trim((string)($stu['barangay'] ?? ''));
    [$lat, $lon] = ($town !== '' ? locationCoordinates($town, $barangay, $studentId) : null) ?? [null, null];
    $address = $town !== '' ? composeAddress($barangay, $town) : $barangay;

    $income = isset($stu['family_income']) && $stu['family_income'] !== null && $stu['family_income'] !== '' ? (float)$stu['family_income'] : null;
    $qualification = trim((string)($stu['special_qualification'] ?? ''));
    $schoolYear = trim((string)($stu['school_year'] ?? '')) ?: getActiveSchoolYear($pdo);
    [$registrarEnrolled] = evRegistrarEnrollment($stu, getActiveSchoolYear($pdo));

    $fields = [
        'first_name' => $stu['first_name'], 'middle_name' => $stu['middle_name'] ?? '', 'last_name' => $stu['last_name'],
        'gender' => $stu['gender'] ?? '', 'email' => $stu['email'] ?? '', 'phone' => $stu['phone'] ?? '',
        'birthdate' => $stu['birthdate'] ?? '', 'age' => computeAge($stu['birthdate'] ?? ''),
        'address' => $address, 'municipality' => $town, 'barangay' => $barangay, 'latitude' => $lat, 'longitude' => $lon,
        'school' => 'College of Maasin', 'school_year' => $schoolYear, 'program' => $stu['program'], 'major' => $stu['major'] ?? '',
        'year_level' => $stu['year_level'], 'semester' => getActiveSemester($pdo),
        // Officially enrolled per the Registrar's record (same rule as the CHED list check).
        'enrolled' => $registrarEnrolled ? 1 : 0,
        'enrollment_verified' => $registrarEnrolled ? 1 : 0,
        'enrollment_verified_source' => $registrarEnrolled ? "Fetched from the Registrar's database" : '',
        'enrollment_verified_at' => $registrarEnrolled ? gmdate('Y-m-d H:i:s') : null,   // UTC, like other timestamps
        'gwa_req' => resolveGwaRequirement($pdo, $scholarshipType),
        'family_income' => $income, 'special_qualification' => $qualification,
    ];

    // A still-open application for this same program is updated, never duplicated.
    $existing = $pdo->prepare("SELECT id FROM applicants WHERE student_id = ? AND scholarship_type = ? AND LOWER(status) IN ('pending', 'review') ORDER BY id DESC LIMIT 1");
    $existing->execute([$studentId, $scholarshipType]);
    $existingId = $existing->fetchColumn();
    if ($existingId !== false) {
        $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($fields)));
        $pdo->prepare("UPDATE applicants SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([...array_values($fields), (int)$existingId]);
        return 'updated';
    }

    // A new applicant takes a slot, so a full program gets no more (same rule as the Apply page).
    if (scholarshipIsFull($pdo, $program)) return 'full';

    // Documents aren't part of the Registrar's record, so they still need to be submitted.
    $fields += ['student_id' => $studentId, 'scholarship_type' => $scholarshipType, 'status' => 'pending', 'docs_complete' => 0, 'gwa' => 0, 'failing_grades' => 0];
    $cols = implode(', ', array_keys($fields));
    $marks = implode(', ', array_fill(0, count($fields), '?'));
    $pdo->prepare("INSERT INTO applicants ($cols) VALUES ($marks)")->execute(array_values($fields));
    $newId = (int)$pdo->lastInsertId();
    logActivity($pdo, 'Applicant Added', 'Applicants', trim($stu['first_name'] . ' ' . $stu['last_name']) . " (Student ID: $studentId) was added from the Registrar's database for " . $program['name'] . '.', $newId);
    return 'created';
}

// Lowercase, single-spaced, without accents/punctuation — for comparing names fairly.
function normalizePersonName(string $s): string {
    $s = strtolower(trim($s));
    $s = strtr($s, ['ñ' => 'n', 'Ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    $s = preg_replace('/[^a-z ]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

try {
    // ---- Connection status ----
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $status = ['host' => REGISTRAR_DB_HOST, 'port' => (int)REGISTRAR_DB_PORT, 'database' => REGISTRAR_DB_NAME];
        try {
            $reg = getRegistrarDB();
            $status['connected'] = true;
            $status['students'] = (int)$reg->query('SELECT COUNT(*) FROM ' . REGISTRAR_STUDENTS_TABLE)->fetchColumn();
        } catch (Throwable $e) {
            $status['connected'] = false;
            $status['error'] = 'Could not connect: ' . $e->getMessage();
        }
        sendJson(['success' => true, 'data' => $status]);
    }

    // ---- Fetch for an uploaded student list ----
    if (empty($_FILES['file']['name']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        sendError('Please choose an Excel (.xlsx) or CSV file with the students to fetch.');
    }
    $fileName = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        sendError('Please upload an .xlsx or .csv file.');
    }
    $save = !isset($_POST['save']) || $_POST['save'] === '1';

    // Read the rows.
    $rows = [];
    if ($ext === 'xlsx') {
        try {
            $rows = readXlsxRows($_FILES['file']['tmp_name']);
        } catch (Throwable $e) {
            sendError('The Excel file could not be read: ' . $e->getMessage());
        }
    } elseif (($h = fopen($_FILES['file']['tmp_name'], 'r')) !== false) {
        while (($r = fgetcsv($h, 4000, ',')) !== false) $rows[] = array_map('trim', $r);
        fclose($h);
    }
    if (count($rows) < 2) {
        sendError('The file has no student rows under its header.');
    }

    // Find the columns by their headers.
    $col = [];
    foreach ($rows[0] as $i => $h) {
        $k = strtolower(trim(preg_replace('/[\s\-]+/', '_', (string)$h)));
        if (in_array($k, ['student_id', 'studentid', 'student_no', 'student_number', 'id_number', 'id'], true)) $col['student_id'] = $i;
        elseif (in_array($k, ['last_name', 'lastname', 'surname', 'family_name'], true)) $col['last_name'] = $i;
        elseif (in_array($k, ['first_name', 'firstname', 'given_name'], true)) $col['first_name'] = $i;
        elseif (in_array($k, ['middle_name', 'middlename'], true)) $col['middle_name'] = $i;
        elseif (in_array($k, ['name', 'full_name', 'fullname', 'student_name'], true)) $col['name'] = $i;
    }
    if (!isset($col['student_id'])) {
        sendError('The file needs a "Student ID" column so each student can be found in the Registrar\'s database.');
    }

    try {
        $reg = getRegistrarDB();
    } catch (Throwable $e) {
        sendError('Could not connect to the Registrar\'s database (' . REGISTRAR_DB_HOST . ':' . REGISTRAR_DB_PORT . '/' . REGISTRAR_DB_NAME . '). Check config/registrar_database.php.', 502);
    }
    $findStudent = $reg->prepare('SELECT * FROM ' . REGISTRAR_STUDENTS_TABLE . ' WHERE student_id = ?');
    $findGrades = $reg->prepare('SELECT subject_code, subject_name, semester, school_year, grade FROM ' . REGISTRAR_GRADES_TABLE . ' WHERE student_id = ? ORDER BY semester, subject_code');

    $pdo = getDB();
    $saveGrade = $pdo->prepare("
        INSERT INTO student_grades (student_id, subject_code, subject_name, semester, school_year, grade, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), school_year = VALUES(school_year), grade = VALUES(grade), updated_at = CURRENT_TIMESTAMP
    ");
    $isApplicant = $pdo->prepare('SELECT COUNT(*) FROM applicants WHERE student_id = ?');
    $findProgram = $pdo->prepare("SELECT * FROM scholarships WHERE name = ? ORDER BY (LOWER(status) = 'active') DESC, id DESC LIMIT 1");

    $results = [];
    $summary = ['rows' => 0, 'found' => 0, 'notFound' => 0, 'nameMismatch' => 0, 'gradesSaved' => 0, 'applicantsCreated' => 0, 'applicantsUpdated' => 0, 'programIssues' => 0, 'programFull' => 0];
    if ($save) scholarshipSlotLock($pdo);   // slot checks below can't race the Apply page
    foreach (array_slice($rows, 1) as $r) {
        $studentId = trim((string)($r[$col['student_id']] ?? ''));
        if ($studentId === '') continue;
        $summary['rows']++;

        $fileName = isset($col['name'])
            ? (string)($r[$col['name']] ?? '')
            : trim(($r[$col['first_name'] ?? -1] ?? '') . ' ' . ($r[$col['middle_name'] ?? -1] ?? '') . ' ' . ($r[$col['last_name'] ?? -1] ?? ''));
        $fileName = trim(preg_replace('/\s+/', ' ', $fileName));

        $findStudent->execute([$studentId]);
        $stu = $findStudent->fetch();
        if (!$stu) {
            $summary['notFound']++;
            $results[] = ['studentId' => $studentId, 'nameInFile' => $fileName, 'found' => false];
            continue;
        }
        $summary['found']++;

        // Name check: the file's first + last name must match the Registrar's (middle name optional).
        $regName = trim($stu['first_name'] . ' ' . $stu['middle_name'] . ' ' . $stu['last_name']);
        // Same tolerance as the CHED list check: capitals, "Ma." for Maria, "John" for "John Paul".
        $hasNameColumns = isset($col['first_name'], $col['last_name']);
        $nameMatches = $fileName === ''
            ? null
            : ($hasNameColumns
                ? evNorm((string)($r[$col['last_name']] ?? '')) === evNorm($stu['last_name']) && evFirstNamesMatch((string)$stu['first_name'], (string)($r[$col['first_name']] ?? ''))
                : in_array(normalizePersonName($fileName), [normalizePersonName($regName), normalizePersonName($stu['first_name'] . ' ' . $stu['last_name']), normalizePersonName($stu['last_name'] . ' ' . $stu['first_name'])], true));
        if ($nameMatches === false) $summary['nameMismatch']++;

        $findGrades->execute([$studentId]);
        $grades = $findGrades->fetchAll();

        // GWA per semester from the fetched grades (for the result table).
        $bySem = [];
        foreach ($grades as $g) $bySem[normalizeSemesterName($g['semester'])][] = (float)$g['grade'];
        $gwa = [];
        foreach ($bySem as $sem => $vals) $gwa[$sem] = round(array_sum($vals) / count($vals), 2);

        // Only save when the name matches (or the file has no name): never file grades under the wrong person.
        $saved = 0;
        $applicantAction = null;   // 'created' / 'updated' / null
        $programName = trim((string)($stu['scholarship_program'] ?? ''));
        $programIssue = null;
        if ($save && $nameMatches !== false) {
            foreach ($grades as $g) {
                $saveGrade->execute([$studentId, $g['subject_code'], $g['subject_name'], normalizeSemesterName($g['semester']), $g['school_year'], (float)$g['grade']]);
                $saved++;
            }

            // The student becomes an applicant for the scholarship program in their Registrar
            // record, so they show up in Applicants and Evaluation (same fields the Apply page fills).
            $program = null;
            if ($programName === '') {
                $programIssue = 'No scholarship program in the Registrar\'s record';
            } else {
                $findProgram->execute([$programName]);
                $program = $findProgram->fetch() ?: null;
                if (!$program) $programIssue = "Scholarship program \"$programName\" isn't on the Scholarships page";
            }
            if ($program) {
                $applicantAction = saveFetchedApplicant($pdo, $stu, $program);
                if ($applicantAction === 'created') $summary['applicantsCreated']++;
                if ($applicantAction === 'updated') $summary['applicantsUpdated']++;
                if ($applicantAction === 'full') {
                    $summary['programFull']++;
                    $programIssue = 'Not added as an applicant: ' . scholarshipFullMessage($program['name']);
                    $applicantAction = null;
                }
            }

            $isApplicant->execute([$studentId]);
            if ((int)$isApplicant->fetchColumn() > 0) recalculateApplicantGwa($pdo, $studentId);
        }
        if ($programIssue !== null) $summary['programIssues']++;
        $summary['gradesSaved'] += $saved;

        $results[] = [
            'studentId' => $studentId,
            'nameInFile' => $fileName,
            'found' => true,
            'nameInRegistrar' => $regName,
            'nameMatches' => $nameMatches,
            'program' => $stu['program'] . ($stu['major'] !== '' ? ' · ' . $stu['major'] : ''),
            'yearLevel' => $stu['year_level'],
            'enrollmentStatus' => $stu['enrollment_status'],
            'gradesCount' => count($grades),
            'gwaFirst' => $gwa['1st Semester'] ?? null,
            'gwaSecond' => $gwa['2nd Semester'] ?? null,
            'gradesSaved' => $saved,
            'scholarship' => $programName,
            'applicant' => $applicantAction,   // 'created' / 'updated' / null
            'programIssue' => $programIssue,
        ];
    }

    logActivity($pdo, 'Registrar Data Fetched', 'Data Management',
        "Fetched {$summary['found']} of {$summary['rows']} student(s) from the Registrar's database using \"" . $_FILES['file']['name'] . "\"" . ($save ? "; {$summary['gradesSaved']} grade(s) saved." : ' (preview only, nothing saved).'));

    sendJson(['success' => true, 'summary' => $summary, 'saved' => $save, 'results' => $results]);
} catch (Throwable $e) {
    sendError($e->getMessage(), 500);
}
