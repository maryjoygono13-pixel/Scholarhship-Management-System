<?php
/*
 * MERIT-BASED Academic Scholarship (Dean's Listers) is different from the scholarships that go
 * through Evaluation -> Renewal & Retention: it has no slot limit and nobody applies for it in
 * particular. Every applicant is considered for it by default, and any enrolled student whose
 * GWA (from the imported academic records) meets its requirement is a scholar automatically.
 * They are added to the Scholars list; they can hold another scholarship at the same time.
 *
 * The scholarship itself (its GWA requirement, active or not) is the one set up on the
 * Scholarships page under the type "MERIT-BASED Academic Scholarship".
 */

require_once __DIR__ . '/grades_helper.php';
require_once __DIR__ . '/name_helper.php';
require_once __DIR__ . '/programs_helper.php';

const MERIT_SCHOLARSHIP_TYPE = 'MERIT-BASED Academic Scholarship';

// The Merit scholarship program from the Scholarships page, or null if there isn't an active one.
function getMeritScholarship(PDO $pdo): ?array {
    $stmt = $pdo->prepare("SELECT * FROM scholarships WHERE LOWER(TRIM(type)) = LOWER(?) AND LOWER(TRIM(status)) = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([MERIT_SCHOLARSHIP_TYPE]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Program -> the department name used on the Scholars page.
function departmentForProgram(string $program): string {
    switch (resolveProgramName($program) ?? '') {
        case 'BS Nursing': return 'Nursing';
        case 'BS Information Technology': return 'Information Technology';
        case 'BS Accountancy': return 'Accountancy';
        case 'BS Business Administration': return 'Business Administration';
        case 'BA Political Science':
        case 'Bachelor of Elementary Education':
        case 'Bachelor of Secondary Education': return 'Liberal Arts and Education';
    }
    return 'Information Technology';   // the Scholars form's own default
}

/*
 * Adds every qualifying applicant to the Scholars list. Safe to call any time (it only adds
 * people who are missing). Returns how many were added.
 *
 * Qualifies: not rejected, enrolled, has imported grades, and their GWA (newest graded
 * semester) is within the Merit scholarship's requirement. Anyone deleted from Scholars
 * (still in the Trash Bin) is not added back.
 */
function syncMeritScholars(PDO $pdo): int {
    $merit = getMeritScholarship($pdo);
    if (!$merit) return 0;
    $required = (float)$merit['gwa_requirement'];
    if ($required <= 0) return 0;

    $applicants = $pdo->query("
        SELECT * FROM applicants
        WHERE LOWER(TRIM(status)) != 'rejected' AND enrolled = 1 AND TRIM(student_id) != ''
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($applicants)) return 0;

    $existing = [];
    foreach ($pdo->query("SELECT student_id FROM scholars")->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        $existing[trim((string)$sid)] = true;
    }
    // Deleted on purpose (Trash Bin): leave them out until they are restored.
    $deleted = [];
    foreach ($pdo->query("SELECT item_data FROM deleted_items WHERE item_type = 'scholar'")->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $d = json_decode((string)$json, true);
        if (!empty($d['student_id'])) $deleted[trim((string)$d['student_id'])] = true;
    }

    $stats = getSemesterGradeStats($pdo, array_column($applicants, 'student_id'));
    $semesterOrder = ['Summer Term', '2nd Semester', '1st Semester'];   // newest graded semester first

    $insert = $pdo->prepare("
        INSERT INTO scholars (student_id, name, department, year_level, gwa, status, school_year, remarks, address, latitude, longitude, scholarship_type)
        VALUES (?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?)
    ");

    $added = 0;
    $done = [];
    foreach ($applicants as $a) {
        $sid = trim((string)$a['student_id']);
        if (isset($existing[$sid]) || isset($deleted[$sid]) || isset($done[$sid])) continue;

        $gwa = null;
        foreach ($semesterOrder as $sem) {
            if (isset($stats[$sid][$sem])) { $gwa = $stats[$sid][$sem]['gwa']; break; }
        }
        if ($gwa === null || $gwa <= 0 || $gwa > $required) continue;

        $year = preg_match('/(\d)/', (string)$a['year_level'], $m) ? max(1, min(4, (int)$m[1])) : 1;
        $sy = trim((string)$a['school_year']) !== '' ? trim($a['school_year']) : getActiveSchoolYear($pdo);
        $insert->execute([
            $sid,
            buildFullName($a['first_name'], $a['middle_name'] ?? '', $a['last_name']),
            departmentForProgram((string)$a['program']),
            $year,
            $gwa,
            $sy,
            'Added automatically: GWA ' . number_format($gwa, 2) . ' meets the ' . MERIT_SCHOLARSHIP_TYPE . ' requirement (<= ' . number_format($required, 2) . ').',
            (string)($a['address'] ?? ''),
            $a['latitude'] ?? null,
            $a['longitude'] ?? null,
            MERIT_SCHOLARSHIP_TYPE,
        ]);
        $done[$sid] = true;
        $added++;
    }

    if ($added > 0) {
        logActivity($pdo, 'Scholar Added', 'Scholars', $added . ' student(s) were added automatically to Scholars: their GWA meets the ' . MERIT_SCHOLARSHIP_TYPE . ' requirement.');
    }
    return $added;
}
