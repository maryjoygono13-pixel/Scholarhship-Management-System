<?php
/*
 * Dean's Listers straight from the Registrar's database — every officially enrolled student, with or
 * without a scholarship application. The Dean's List is the school's recognition for academic
 * performance, awarded per semester (rule in deans_list_rules.php: the ACTIVE semester's GWA — the term
 * set in Portal Configuration — 1.50 or better and no subject grade of 2.00 or worse). Students with no
 * grades yet for the active semester aren't judged for it. It is NOT the MERIT-BASED Academic
 * Scholarship, which students apply for like any other program.
 *
 * "Scan Registrar Now" (api/scan_deans_list.php) adds qualifying students who aren't on the Scholars
 * list yet under the "Dean's List" label, copies their grades into student_grades (so the Scholars
 * page reads the same figures), and gives each one an approved "Dean's List" Record for the semester —
 * one per student per semester, every semester kept — since Records is where the list is exported. Anyone deleted from Scholars
 * (still in the Trash Bin) is left out.
 */

require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/deans_list_rules.php';
require_once __DIR__ . '/merit_helper.php';   // departmentForProgram(), grades / name helpers
require_once __DIR__ . '/locations.php';
require_once __DIR__ . '/enrollment_verification_helper.php';
require_once __DIR__ . '/records_helper.php';
require_once __DIR__ . '/renewal_enrollment_helper.php';   // registrarTermEnrollment()

// Adds the Registrar's Dean's Listers to Scholars and Records. Returns how many were added to
// Scholars (0 when the Registrar's database can't be reached). $report (optional) is filled with:
// reachable, qualified, added, alreadyListed, inTrash, recordsAdded, recordsUpdated and addedNames.
function syncRegistrarDeansListers(PDO $pdo, ?array &$report = null): int {
    $report = ['reachable' => true, 'qualified' => 0, 'added' => 0, 'alreadyListed' => 0, 'inTrash' => 0, 'recordsAdded' => 0, 'recordsUpdated' => 0, 'addedNames' => []];
    $schoolYear = getActiveSchoolYear($pdo);
    $semester = getActiveSemester($pdo);   // the Dean's List is awarded for the active semester

    try {
        $reg = getRegistrarDB();
        $students = $reg->query('SELECT * FROM ' . REGISTRAR_STUDENTS_TABLE)->fetchAll(PDO::FETCH_ASSOC);
        $gradeStmt = $reg->prepare('SELECT student_id, subject_code, subject_name, semester, school_year, grade FROM ' . REGISTRAR_GRADES_TABLE . ' WHERE school_year = ?');
        $gradeStmt->execute([$schoolYear]);
        $gradeRows = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $report['reachable'] = false;
        return 0;
    }

    // Grades and per-semester figures for each student (this school year only).
    $grades = [];
    $acc = [];
    foreach ($gradeRows as $g) {
        $sid = trim((string)$g['student_id']);
        $sem = normalizeSemesterName($g['semester']);
        $grade = (float)$g['grade'];
        $grades[$sid][] = $g;
        $acc[$sid][$sem]['sum'] = ($acc[$sid][$sem]['sum'] ?? 0) + $grade;
        $acc[$sid][$sem]['n'] = ($acc[$sid][$sem]['n'] ?? 0) + 1;
        $acc[$sid][$sem]['worst'] = max($acc[$sid][$sem]['worst'] ?? 0, $grade);
    }
    if (!$acc) return 0;

    // student_id => the label / scholarship type of their Scholars entry
    $existing = [];
    foreach ($pdo->query("SELECT student_id, scholarship_type FROM scholars")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[trim((string)$row['student_id'])] = (string)$row['scholarship_type'];
    }
    $deleted = [];
    foreach ($pdo->query("SELECT item_data FROM deleted_items WHERE item_type = 'scholar'")->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $d = json_decode((string)$json, true);
        if (!empty($d['student_id'])) $deleted[trim((string)$d['student_id'])] = true;
    }

    $approvedScholarship = $pdo->prepare("SELECT scholarship_type FROM records WHERE student_id = ? AND LOWER(TRIM(status)) = 'approved' AND LOWER(TRIM(scholarship_type)) != LOWER(?) ORDER BY id DESC LIMIT 1");
    $updateScholar = $pdo->prepare("UPDATE scholars SET gwa = ?, school_year = ?, remarks = ? WHERE student_id = ? AND scholarship_type = ?");

    $added = 0;
    foreach ($students as $stu) {
        $sid = trim((string)$stu['student_id']);
        if ($sid === '' || empty($acc[$sid][$semester])) continue;
        // Officially enrolled for the active semester (the Registrar's per-semester enrollment).
        if (!registrarTermEnrollment($reg, $sid, $schoolYear, $semester)['enrolled']) continue;

        // Judged on the active semester's grades only (none yet: not judged for it).
        if (!isset($acc[$sid][$semester])) continue;
        $a = $acc[$sid][$semester];
        $stat = ['gwa' => round($a['sum'] / $a['n'], 2), 'worst' => $a['worst']];
        if (!deansListQualifies($stat)) continue;
        $report['qualified']++;
        $gwa = $stat['gwa'];
        $fullName = buildFullName((string)$stu['first_name'], (string)($stu['middle_name'] ?? ''), (string)$stu['last_name']);
        $remark = "Dean's Lister — found in the Registrar's database: GWA " . number_format($gwa, 2) . " in $semester, no subject grade of " . number_format(DEANS_LIST_GRADE_LIMIT, 2) . ' or worse.';
        if (isset($existing[$sid])) {
            $report['alreadyListed']++;
            // Already on the Scholars list: bring a Dean's List entry up to this semester. Either way
            // (also when they're listed for a scholarship they hold) they get their Dean's List Record.
            if (isDeansListType($existing[$sid])) {
                $updateScholar->execute([$gwa, $schoolYear, $remark, $sid, DEANS_LIST_TYPE]);
            }
            $r = ensureDeansListRecord($pdo, $sid, $fullName, $semester, $schoolYear, $remark, $stu);
            if ($r === 'added') $report['recordsAdded']++;
            if ($r === 'updated') $report['recordsUpdated']++;
            continue;
        }
        if (isset($deleted[$sid])) { $report['inTrash']++; continue; }   // restore from the Trash Bin to bring back

        // Holds an approved scholarship already: listed under that program (the Dean's Lister badge comes
        // from their grades). Otherwise under the "Dean's List" label.
        $approvedScholarship->execute([$sid, DEANS_LIST_TYPE]);
        $listedAs = (string)($approvedScholarship->fetchColumn() ?: DEANS_LIST_TYPE);

        addRegistrarScholarEntry($pdo, $stu, $listedAs, $gwa, $schoolYear, $remark, $grades[$sid]);
        $existing[$sid] = $listedAs;
        $added++;
        if (count($report['addedNames']) < 10) $report['addedNames'][] = "$fullName ($sid)";
        $r = ensureDeansListRecord($pdo, $sid, $fullName, $semester, $schoolYear, $remark, $stu);
        if ($r === 'added') $report['recordsAdded']++;
        if ($r === 'updated') $report['recordsUpdated']++;
    }

    if ($added > 0) {
        logActivity($pdo, 'Scholar Added', 'Scholars', $added . " Dean's Lister(s) from the Registrar's database were added to Scholars.");
    }
    if ($report['recordsAdded'] > 0) {
        logActivity($pdo, 'Record Added', 'Records', $report['recordsAdded'] . " Dean's List record(s) were added to Records ($semester $schoolYear).");
    }
    if ($report['recordsUpdated'] > 0) {
        logActivity($pdo, 'Record Updated', 'Records', $report['recordsUpdated'] . " Dean's List record(s) were moved on to $semester $schoolYear.");
    }
    $report['added'] = $added;
    return $added;
}

/*
 * Puts one student from the Registrar's database on the Scholars list (Dean's Lister or scholarship
 * holder): name, department, year level, address / map position from their Registrar record, listed
 * under $type. Their grades ($grades: Registrar grade rows) are copied into student_grades so the
 * Scholars page reads the same figures.
 */
function addRegistrarScholarEntry(PDO $pdo, array $stu, string $type, ?float $gwa, string $schoolYear, string $remark, array $grades): void {
    $sid = trim((string)$stu['student_id']);
    $saveGrade = $pdo->prepare("
        INSERT INTO student_grades (student_id, subject_code, subject_name, semester, school_year, grade, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name), school_year = VALUES(school_year), grade = VALUES(grade), updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($grades as $g) {
        $saveGrade->execute([$sid, $g['subject_code'], $g['subject_name'], normalizeSemesterName($g['semester']), $g['school_year'], (float)$g['grade']]);
    }

    $town = resolveMunicipality((string)($stu['municipality'] ?? '')) ?? trim((string)($stu['municipality'] ?? ''));
    $barangay = trim((string)($stu['barangay'] ?? ''));
    [$lat, $lon] = ($town !== '' ? locationCoordinates($town, $barangay, $sid) : null) ?? [null, null];
    $year = preg_match('/(\d)/', (string)$stu['year_level'], $m) ? max(1, min(4, (int)$m[1])) : 1;
    $pdo->prepare("
        INSERT INTO scholars (student_id, name, department, year_level, gwa, status, school_year, remarks, address, latitude, longitude, scholarship_type)
        VALUES (?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?, ?)
    ")->execute([
        $sid,
        buildFullName((string)$stu['first_name'], (string)($stu['middle_name'] ?? ''), (string)$stu['last_name']),
        departmentForProgram((string)$stu['program']),
        $year,
        $gwa ?? 0,
        $schoolYear,
        $remark,
        $town !== '' ? composeAddress($barangay, $town) : $barangay,
        $lat,
        $lon,
        $type,
    ]);
}

/*
 * The student's "Dean's List" Record for this semester — one per student per semester, and every
 * semester's record is kept (1st Semester, then 2nd Semester, ... all searchable on Records), so every
 * Dean's Lister the scan finds is on Records. A student who holds a scholarship that school year gets
 * no separate record: their scholarship record carries the Dean's Lister badge. Saved with their program and year level from the
 * Registrar's record ($stu). An older copy of this same record still in the Trash Bin is superseded
 * and removed from it, so restoring it can't create a duplicate. Returns 'added', or null when the
 * record already exists.
 */
function ensureDeansListRecord(PDO $pdo, string $studentId, string $name, string $semester, string $schoolYear, string $remark, array $stu = []): ?string {
    // Holds a scholarship this school year (e.g. CMSP): Records shows that scholarship with the Dean's
    // Lister badge on it, so no separate "Dean's List" record.
    $holds = $pdo->prepare("SELECT COUNT(*) FROM records WHERE student_id = ? AND TRIM(sy) = ? AND LOWER(TRIM(status)) = 'approved' AND LOWER(TRIM(scholarship_type)) != LOWER(?)");
    $holds->execute([$studentId, $schoolYear, DEANS_LIST_TYPE]);
    if ((int)$holds->fetchColumn() > 0) return null;

    $saved = saveTermRecord($pdo, [
        'student_id' => $studentId,
        'name' => $name,
        'scholarship_type' => DEANS_LIST_TYPE,
        'status' => 'approved',
        'semester' => $semester,
        'sy' => $schoolYear,
        'remarks' => $remark,
        'origin' => 'deans_list',
        'program' => trim((string)($stu['program'] ?? '')) . (trim((string)($stu['major'] ?? '')) !== '' ? ' · ' . trim((string)$stu['major']) : ''),
        'year_level' => (string)($stu['year_level'] ?? ''),
    ]);
    if (!$saved['created']) return null;
    $pdo->prepare("DELETE FROM deleted_items WHERE item_type = 'record' AND JSON_UNQUOTE(JSON_EXTRACT(item_data, '$.student_id')) = ? AND JSON_UNQUOTE(JSON_EXTRACT(item_data, '$.sy')) = ? AND JSON_UNQUOTE(JSON_EXTRACT(item_data, '$.semester')) = ? AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(item_data, '$.scholarship_type'))) = LOWER(?)")
        ->execute([$studentId, $schoolYear, $semester, DEANS_LIST_TYPE]);
    return 'added';
}

// [studentId => true] for the given students whose Dean's List basis semester (the active one, or
// their latest graded one before it) meets the rule. Used by the "Dean's Listers" filter option.
function deansListerIds(PDO $pdo, array $studentIds): array {
    $active = getActiveSemester($pdo);
    $out = [];
    foreach (getSemesterGradeStats($pdo, $studentIds) as $sid => $bySem) {
        $sem = deansListBasisSemester($bySem, $active);
        if ($sem !== null && deansListQualifies($bySem[$sem])) $out[(string)$sid] = true;
    }
    return $out;
}
