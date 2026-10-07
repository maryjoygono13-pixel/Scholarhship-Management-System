<?php
/*
 * MERIT-BASED Academic Scholarship: a scholarship students APPLY for, evaluated and approved like
 * any other program (Apply page -> Evaluation -> Records). Once approved, the student is on the
 * Scholars list through their approved Record (syncApprovedScholars() below).
 *
 * It is not the Dean's List: that one is the school's recognition for grades alone, with no
 * application (see deans_list_rules.php / deans_list_helper.php).
 *
 * The program set up on the Scholarships page under the type "MERIT-BASED Academic Scholarship"
 * defines its Full Merit / Half Merit GWA ranges (collegiate) and its Basic Education criteria.
 * A Merit scholar's standing is decided by each semester's GWA:
 *   - within the Full or Half Merit range: Active for that semester;
 *   - worse than the Half Merit limit but better than MERIT_LOCKOUT_GWA (2.00): not Active for that
 *     semester, but back as soon as a later semester is within range again;
 *   - MERIT_LOCKOUT_GWA (2.00) or worse in any semester: Removed for the rest of that school year,
 *     even if a later semester is back within range, until the next school year.
 */

require_once __DIR__ . '/grades_helper.php';
require_once __DIR__ . '/name_helper.php';
require_once __DIR__ . '/programs_helper.php';

const MERIT_SCHOLARSHIP_TYPE = 'MERIT-BASED Academic Scholarship';

// A semester GWA this bad (or worse) locks a Merit scholar out for the rest of the school year.
const MERIT_LOCKOUT_GWA = 2.00;

/*
 * Collegiate Merit tiers (Full Merit / Half Merit GWA ranges) and the Basic Education
 * requirement are edited on the Merit program itself (scholarships.merit_* columns, set on the
 * Scholarships page). These are only the defaults, used until a Merit program sets its own.
 * Anything worse than the Half Merit limit isn't Merit at all, so that limit is also the
 * program's GWA requirement.
 */
const MERIT_DEFAULT_RANGES = [
    'full_min' => 1.00, 'full_max' => 1.30,
    'half_min' => 1.31, 'half_max' => 1.50,
    'basic_criteria' => 'Top 1 or Top 2 in class',
];

// A program row's Merit ranges (falls back to the defaults for anything missing).
function meritRangesFromRow(?array $row): array {
    $r = MERIT_DEFAULT_RANGES;
    if (!$row) return $r;
    foreach (['full_min', 'full_max', 'half_min', 'half_max'] as $k) {
        if (isset($row['merit_' . $k]) && (float)$row['merit_' . $k] > 0) $r[$k] = round((float)$row['merit_' . $k], 2);
    }
    if (trim((string)($row['merit_basic_criteria'] ?? '')) !== '') $r['basic_criteria'] = trim($row['merit_basic_criteria']);
    return $r;
}

// The active Merit program's ranges — what every Merit decision is based on.
function getMeritRanges(PDO $pdo): array {
    return meritRangesFromRow(getMeritScholarship($pdo));
}

// 'Full Merit' / 'Half Merit' for a collegiate GWA under the given ranges, or null when it doesn't qualify.
function meritTierForGwa(float $gwa, array $ranges = MERIT_DEFAULT_RANGES): ?string {
    if ($gwa <= 0) return null;
    if ($gwa >= $ranges['full_min'] && $gwa <= $ranges['full_max']) return 'Full Merit';
    if ($gwa >= $ranges['half_min'] && $gwa <= $ranges['half_max']) return 'Half Merit';
    return null;
}

// The GWA requirement a Merit program has for its education level (0 = none, Basic Education).
function meritGwaRequirementFor(string $educationLevel, array $ranges = MERIT_DEFAULT_RANGES): float {
    return strtolower(trim($educationLevel)) === 'basic education' ? 0.0 : (float)$ranges['half_max'];
}

// "Full Merit 1.00–1.30, Half Merit 1.31–1.50"
function meritRangesText(array $ranges): string {
    $f = fn($v) => number_format((float)$v, 2);
    return 'Full Merit ' . $f($ranges['full_min']) . '–' . $f($ranges['full_max']) . ', Half Merit ' . $f($ranges['half_min']) . '–' . $f($ranges['half_max']);
}

// Whether a scholarship type is the MERIT-BASED Academic one (also its older name "Academic Merit").
function isMeritScholarshipType(string $type): bool {
    $t = strtolower(trim($type));
    return $t === strtolower(MERIT_SCHOLARSHIP_TYPE) || $t === 'academic merit';
}

/*
 * The semester that locks this student out of Merit for `$schoolYear`: a semester of that school
 * year whose GWA is MERIT_LOCKOUT_GWA or worse. Returns ['semester' => ..., 'gwa' => ...] or null.
 * Grades with no school year recorded count toward the given (active) school year.
 */
function meritLockout(PDO $pdo, string $studentId, string $schoolYear): ?array {
    $stmt = $pdo->prepare("SELECT semester, school_year, grade FROM student_grades WHERE student_id = ?");
    $stmt->execute([trim($studentId)]);
    $acc = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sy = trim((string)$row['school_year']);
        if ($sy !== '' && $sy !== trim($schoolYear)) continue;
        $sem = normalizeSemesterName($row['semester']);
        $acc[$sem]['sum'] = ($acc[$sem]['sum'] ?? 0) + (float)$row['grade'];
        $acc[$sem]['n'] = ($acc[$sem]['n'] ?? 0) + 1;
    }
    foreach ($acc as $sem => $a) {
        $gwa = round($a['sum'] / $a['n'], 2);
        if ($gwa >= MERIT_LOCKOUT_GWA) return ['semester' => $sem, 'gwa' => $gwa];
    }
    return null;
}

// The Merit scholarship program from the Scholarships page, or null if there isn't an active one.
function getMeritScholarship(PDO $pdo): ?array {
    $stmt = $pdo->prepare("SELECT * FROM scholarships WHERE LOWER(TRIM(type)) = LOWER(?) AND LOWER(TRIM(status)) = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([MERIT_SCHOLARSHIP_TYPE]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Program -> the department name used on the Scholars page.
function departmentForProgram(string $program): string {
    // Scholars are listed by program, named as in the Departments dropdowns (PROGRAM_DISPLAY_NAMES).
    $canonical = resolveProgramName($program);
    return PROGRAM_DISPLAY_NAMES[$canonical ?? ''] ?? 'BS Information Technology';   // the Scholars form's own default
}

/*
 * Every scholarship (MERIT-BASED Academic included) is granted by an approved application — so
 * once a Record is approved, that student belongs on the Scholars list too ("Dean's List" Records
 * are left out: those students are added only by "Scan Registrar Now"),
 * regardless of their GWA at that moment. GWA maintenance (Active vs Removed) is judged
 * afterwards by list_scholars.php from their imported grades, same as any other scholar.
 */
function syncApprovedScholars(PDO $pdo): int {
    $records = $pdo->query("SELECT * FROM records WHERE LOWER(TRIM(status)) = 'approved' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($records)) return 0;

    $existing = [];   // student_id => the scholarship type on their Scholars entry
    foreach ($pdo->query("SELECT student_id, scholarship_type FROM scholars")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[trim((string)$row['student_id'])] = trim((string)$row['scholarship_type']);
    }
    // Deleted on purpose (Trash Bin): leave them out until they are restored.
    $deleted = [];
    foreach ($pdo->query("SELECT item_data FROM deleted_items WHERE item_type = 'scholar'")->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $d = json_decode((string)$json, true);
        if (!empty($d['student_id'])) $deleted[trim((string)$d['student_id'])] = true;
    }

    // A Dean's Lister (added by "Scan Registrar Now") who is approved for a scholarship: their Scholars
    // entry now names that scholarship. Being a Dean's Lister still shows as the badge (by grades).
    $toScholarship = $pdo->prepare("UPDATE scholars SET scholarship_type = ?, remarks = ? WHERE student_id = ? AND scholarship_type = \"Dean's List\"");
    foreach ($records as $rec) {
        $sid = trim((string)$rec['student_id']);
        if ($sid === '' || strcasecmp($existing[$sid] ?? '', "Dean's List") !== 0) continue;
        if (strcasecmp(trim((string)$rec['scholarship_type']), "Dean's List") === 0) continue;
        $toScholarship->execute([(string)$rec['scholarship_type'], 'Approved for ' . $rec['scholarship_type'] . ' in Records (also a Dean\'s Lister by grades).', $sid]);
        $existing[$sid] = (string)$rec['scholarship_type'];
    }

    $findApplicantById = $pdo->prepare("SELECT * FROM applicants WHERE id = ?");
    $findApplicantByStudent = $pdo->prepare("SELECT * FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");

    $gradeStats = getSemesterGradeStats($pdo, array_column($records, 'student_id'));
    $semesterOrder = ['Summer Term', '2nd Semester', '1st Semester'];   // newest graded semester first

    $insert = $pdo->prepare("
        INSERT INTO scholars (student_id, name, department, year_level, gwa, status, school_year, remarks, address, latitude, longitude, scholarship_type)
        VALUES (?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?)
    ");

    $added = 0;
    $done = [];
    foreach ($records as $rec) {
        $sid = trim((string)$rec['student_id']);
        if ($sid === '' || isset($existing[$sid]) || isset($deleted[$sid]) || isset($done[$sid])) continue;
        // Dean's List records come with their Scholars entry from "Scan Registrar Now"; removing that
        // entry must stick, so they're never re-added here on a page load.
        if (strcasecmp(trim((string)$rec['scholarship_type']), "Dean's List") === 0) continue;

        $app = null;
        if ((int)($rec['applicant_id'] ?? 0) > 0) {
            $findApplicantById->execute([(int)$rec['applicant_id']]);
            $app = $findApplicantById->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$app) {
            $findApplicantByStudent->execute([$sid]);
            $app = $findApplicantByStudent->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $gwa = null;
        foreach ($semesterOrder as $sem) {
            if (isset($gradeStats[$sid][$sem])) { $gwa = $gradeStats[$sid][$sem]['gwa']; break; }
        }
        if ($gwa === null && $app && (float)$app['gwa'] > 0) $gwa = (float)$app['gwa'];
        $gwa = $gwa ?? 0.0;

        $name = $app ? buildFullName($app['first_name'], $app['middle_name'] ?? '', $app['last_name']) : trim((string)$rec['name']);
        $department = $app ? departmentForProgram((string)$app['program']) : '';
        $year = ($app && preg_match('/(\d)/', (string)$app['year_level'], $m)) ? max(1, min(4, (int)$m[1])) : 1;
        $sy = trim((string)$rec['sy']) !== '' ? trim($rec['sy']) : getActiveSchoolYear($pdo);

        $insert->execute([
            $sid,
            $name,
            $department,
            $year,
            $gwa,
            $sy,
            'Added automatically: approved for ' . $rec['scholarship_type'] . ' in Records.',
            $app ? (string)($app['address'] ?? '') : '',
            $app['latitude'] ?? null,
            $app['longitude'] ?? null,
            (string)$rec['scholarship_type'],
        ]);
        $done[$sid] = true;
        $added++;
    }

    if ($added > 0) {
        logActivity($pdo, 'Scholar Added', 'Scholars', $added . ' student(s) were added automatically to Scholars: their application was approved in Records.');
    }
    return $added;
}
