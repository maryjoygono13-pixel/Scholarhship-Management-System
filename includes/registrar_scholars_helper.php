<?php
/*
 * Current scholars straight from the Registrar's database: the students in its scholarship table
 * (REGISTRAR_SCHOLARSHIPS_TABLE) who hold an ACTIVE scholarship program for the active school year.
 * Part of "Scan Registrar Now" (api/scan_deans_list.php), run before the Dean's List part.
 *
 * Each one who is officially enrolled for the active semester (the Registrar's per-semester enrollment)
 * and whose program is on the Scholarships page:
 *   - goes on the Scholars list under that program (a Dean's List entry of theirs becomes the program);
 *     their Dean's Lister badge, if any, comes from their grades as for everyone,
 *   - gets an approved record for the term the scholarship was granted (one per term, never duplicated),
 *   - and a Renewal & Retention entry, so the next term's renew / terminate decision happens there.
 * Not enrolled, a program the system doesn't offer, or deleted from Scholars (Trash Bin): left out.
 * (students.scholarship_program is only the program a student applies for — not read here.)
 */

require_once __DIR__ . '/deans_list_helper.php';   // addRegistrarScholarEntry(), records / enrollment helpers
require_once __DIR__ . '/renewal_helper.php';      // sendRecordToRenewal()

/*
 * Returns ['reachable', 'tableFound', 'found', 'added', 'converted', 'alreadyListed', 'notEnrolled',
 *          'unknownProgram', 'inTrash', 'recordsAdded', 'addedNames'].
 */
function syncRegistrarScholars(PDO $pdo): array {
    $report = ['reachable' => true, 'tableFound' => true, 'found' => 0, 'added' => 0, 'converted' => 0, 'alreadyListed' => 0,
               'notEnrolled' => 0, 'unknownProgram' => 0, 'inTrash' => 0, 'recordsAdded' => 0, 'addedNames' => []];
    $schoolYear = getActiveSchoolYear($pdo);
    $semester = getActiveSemester($pdo);

    try {
        $reg = getRegistrarDB();
        $has = $reg->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $has->execute([REGISTRAR_SCHOLARSHIPS_TABLE]);
        if (!(int)$has->fetchColumn()) { $report['tableFound'] = false; return $report; }
        $grantStmt = $reg->prepare('SELECT * FROM ' . REGISTRAR_SCHOLARSHIPS_TABLE . " WHERE LOWER(TRIM(status)) = 'active' AND TRIM(school_year) = ? ORDER BY student_id");
        $grantStmt->execute([$schoolYear]);
        $grants = $grantStmt->fetchAll(PDO::FETCH_ASSOC);
        $studentStmt = $reg->prepare('SELECT * FROM ' . REGISTRAR_STUDENTS_TABLE . ' WHERE student_id = ?');
        $gradeStmt = $reg->prepare('SELECT student_id, subject_code, subject_name, semester, school_year, grade FROM ' . REGISTRAR_GRADES_TABLE . ' WHERE student_id = ? AND school_year = ?');
    } catch (Throwable $e) {
        $report['reachable'] = false;
        return $report;
    }

    // The programs on the Scholarships page, by name (and short name), to their stored type.
    $programs = [];
    foreach ($pdo->query("SELECT name, code, subtype FROM scholarships")->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $type = normalizeScholarshipType($pdo, trim((string)$p['subtype']) !== '' ? $p['subtype'] : $p['name']);
        foreach ([$p['name'], $p['code'], $p['subtype'], $type] as $k) {
            if (trim((string)$k) !== '') $programs[strtolower(trim((string)$k))] = $type;
        }
    }

    $existing = [];
    foreach ($pdo->query("SELECT student_id, scholarship_type FROM scholars")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[trim((string)$row['student_id'])] = (string)$row['scholarship_type'];
    }
    $deleted = [];
    foreach ($pdo->query("SELECT item_data FROM deleted_items WHERE item_type = 'scholar'")->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $d = json_decode((string)$json, true);
        if (!empty($d['student_id'])) $deleted[trim((string)$d['student_id'])] = true;
    }
    $toProgram = $pdo->prepare("UPDATE scholars SET scholarship_type = ?, remarks = ? WHERE student_id = ? AND scholarship_type = ?");

    foreach ($grants as $grant) {
        $sid = trim((string)$grant['student_id']);
        $report['found']++;
        $studentStmt->execute([$sid]);
        $stu = $studentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$stu || !registrarTermEnrollment($reg, $sid, $schoolYear, $semester)['enrolled']) { $report['notEnrolled']++; continue; }
        $type = $programs[strtolower(trim((string)$grant['scholarship_program']))] ?? null;
        if ($type === null) { $report['unknownProgram']++; continue; }

        $grantSemester = normalizeSemesterName((string)$grant['semester']);
        $fullName = buildFullName((string)$stu['first_name'], (string)($stu['middle_name'] ?? ''), (string)$stu['last_name']);
        $remark = "$type scholar per the Registrar's database (granted $grantSemester $schoolYear).";

        if (isset($existing[$sid])) {
            if (isDeansListType($existing[$sid])) {
                $toProgram->execute([$type, $remark, $sid, DEANS_LIST_TYPE]);   // listed under the program from now on
                $existing[$sid] = $type;
                $report['converted']++;
            } else {
                $report['alreadyListed']++;
            }
        } elseif (isset($deleted[$sid])) {
            $report['inTrash']++;   // restore from the Trash Bin to bring back
            continue;
        } else {
            $gradeStmt->execute([$sid, $schoolYear]);
            $grades = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);
            $gwa = null;
            $sem = array_filter($grades, fn($g) => normalizeSemesterName($g['semester']) === $semester);
            if ($sem) $gwa = round(array_sum(array_map(fn($g) => (float)$g['grade'], $sem)) / count($sem), 2);
            addRegistrarScholarEntry($pdo, $stu, $type, $gwa, $schoolYear, $remark, $grades);
            $existing[$sid] = $type;
            $report['added']++;
            if (count($report['addedNames']) < 10) $report['addedNames'][] = "$fullName ($sid, $type)";
        }

        // Their approved record for the granted term (never a duplicate), then Renewal & Retention.
        $saved = saveTermRecord($pdo, [
            'student_id' => $sid,
            'name' => $fullName,
            'scholarship_type' => $type,
            'status' => 'approved',
            'semester' => $grantSemester,
            'sy' => $schoolYear,
            'remarks' => $remark,
            'origin' => 'registrar',
            'program' => trim((string)$stu['program']) . (trim((string)($stu['major'] ?? '')) !== '' ? ' · ' . trim((string)$stu['major']) : ''),
            'year_level' => (string)$stu['year_level'],
            'date_evaluated' => !empty($grant['granted_on']) ? (string)$grant['granted_on'] : date('Y-m-d'),
        ]);
        if ($saved['created']) {
            $report['recordsAdded']++;
            sendRecordToRenewal($pdo, $saved['record'], 'pending', "Scholar per the Registrar's database (granted $grantSemester $schoolYear).");
        }
    }

    if ($report['added'] + $report['converted'] > 0) {
        logActivity($pdo, 'Scholar Added', 'Scholars', ($report['added'] + $report['converted']) . " scholar(s) holding a scholarship program in the Registrar's database were added to Scholars.");
    }
    if ($report['recordsAdded'] > 0) {
        logActivity($pdo, 'Record Added', 'Records', $report['recordsAdded'] . " scholarship record(s) from the Registrar's database were added to Records ($schoolYear).");
    }
    return $report;
}
