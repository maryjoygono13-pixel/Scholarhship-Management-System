<?php
/*
 * Per-semester enrollment in the Registrar's database (table REGISTRAR_ENROLLMENTS_TABLE,
 * "enrollments"), including the list of enrollees for the 2nd Semester 2026-2027. Renewal &
 * Retention checks this table whenever the Active Semester or Academic Year changes in
 * Settings > Portal Configuration, to see whether each scholar is still officially enrolled.
 *
 *   php database/registrar_semester_enrollment.php
 *
 * 1st Semester 2026-2027: every student whose record says Enrolled for 2026-2027 (the students the
 * seed marked Dropped get a "Dropped" row; those last enrolled in 2025-2026 get none).
 * 2nd Semester 2026-2027, from the 1st Semester enrollees:
 *   - most are Enrolled (and get their 2nd Semester subjects in enrolled_subjects),
 *   - about 1 in 10 Dropped, about 1 in 12 didn't enroll (no row),
 *   - group B (IDs like 20242015) all enrolled: they already have 2nd Semester grades,
 *   - a few fixed test cases (see $fixed below).
 * Re-running rebuilds the table the same way every time (fixed random seed). Also writes
 * test-data/second_semester_enrollees_2026-2027.xlsx. database/registrar_test_seed.php runs this
 * last, so a full rebuild keeps it.
 */

require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/../includes/curriculum_helper.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';

function buildSemesterEnrollments(): array {
    $sy = '2026-2027';
    mt_srand(2222);   // its own fixed seed
    $db = getRegistrarDB();
    $curriculum = loadCurriculumData();
    $T = REGISTRAR_ENROLLMENTS_TABLE;

    $db->exec("DROP TABLE IF EXISTS $T");
    $db->exec("CREATE TABLE $T (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        school_year VARCHAR(20) NOT NULL,
        semester VARCHAR(30) NOT NULL,
        enrollment_status VARCHAR(50) NOT NULL,
        enrolled_on DATE DEFAULT NULL,
        UNIQUE KEY uniq_term (student_id, school_year, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Fixed 2nd Semester outcomes for students used in earlier tests (Renewal & Retention entries).
    $fixed = [
        '20269025' => 'Enrolled',      // Camille Ocampo Castillo (real e-mail test)
        '20239007' => 'Dropped',
        '20231005' => 'Enrolled',
        '20261004' => 'Not enrolled',
        '20241003' => 'Enrolled',
        '20251002' => 'Dropped',
        '20261001' => 'Enrolled',
    ];

    $insert = $db->prepare("INSERT INTO $T (student_id, school_year, semester, enrollment_status, enrolled_on) VALUES (?, ?, ?, ?, ?)");
    $hasSubjects = $db->prepare('SELECT COUNT(*) FROM ' . REGISTRAR_SUBJECTS_TABLE . ' WHERE student_id = ? AND semester = ? AND school_year = ?');
    $addSubject = $db->prepare('INSERT INTO ' . REGISTRAR_SUBJECTS_TABLE . ' (student_id, subject_code, subject_name, semester, school_year) VALUES (?, ?, ?, ?, ?)');
    $dropSubjects = $db->prepare('DELETE FROM ' . REGISTRAR_SUBJECTS_TABLE . ' WHERE student_id = ? AND semester = ? AND school_year = ?');

    $students = $db->query('SELECT * FROM ' . REGISTRAR_STUDENTS_TABLE . ' ORDER BY student_id')->fetchAll(PDO::FETCH_ASSOC);
    $rows = [];
    $count = ['1st' => 0, 'Enrolled' => 0, 'Dropped' => 0, 'Not enrolled' => 0];
    $db->beginTransaction();
    foreach ($students as $s) {
        $sid = (string)$s['student_id'];
        $status = strtolower(trim((string)$s['enrollment_status']));
        $studentSy = trim((string)$s['school_year']);

        if ($studentSy !== $sy) {
            // Last enrolled in an earlier year: that year's 2nd Semester, nothing for 2026-2027.
            $insert->execute([$sid, $studentSy, '2nd Semester', 'Enrolled', substr($studentSy, 5) . '-01-12']);
            $rows[] = [$s, 'Not enrolled', 'Not enrolled'];
            continue;
        }
        if ($status === 'dropped') {
            $insert->execute([$sid, $sy, '1st Semester', 'Dropped', '2026-08-04']);
            $rows[] = [$s, 'Dropped', 'Not enrolled'];
            continue;
        }

        // 1st Semester: enrolled.
        $insert->execute([$sid, $sy, '1st Semester', 'Enrolled', '2026-08-04']);
        $count['1st']++;

        // 2nd Semester.
        if (isset($fixed[$sid])) {
            $second = $fixed[$sid];
        } elseif (substr($sid, 4, 1) === '2') {
            $second = 'Enrolled';   // group B: already has 2nd Semester grades
        } else {
            $roll = mt_rand(1, 100);
            $second = $roll <= 10 ? 'Dropped' : ($roll <= 18 ? 'Not enrolled' : 'Enrolled');
        }
        $count[$second]++;
        if ($second !== 'Not enrolled') {
            $insert->execute([$sid, $sy, '2nd Semester', $second, '2027-01-11']);
        }

        // Enrolled in the 2nd Semester: their subjects for it. Dropped / not enrolled: none.
        if ($second === 'Enrolled') {
            $hasSubjects->execute([$sid, '2nd Semester', $sy]);
            if ((int)$hasSubjects->fetchColumn() === 0) {
                $key = trim((string)$s['program']) . (trim((string)$s['major']) !== '' ? '|' . trim((string)$s['major']) : '');
                foreach ($curriculum[$key][$s['year_level']]['2nd Semester'] ?? [] as $subject) {
                    $addSubject->execute([$sid, $subject['code'], $subject['name'], '2nd Semester', $sy]);
                }
            }
        } else {
            $dropSubjects->execute([$sid, '2nd Semester', $sy]);
        }
        $rows[] = [$s, 'Enrolled', $second];
    }
    $db->commit();

    // The list of 2nd Semester enrollees (and who isn't), for checking Renewal & Retention.
    $out = [['Student ID', 'Last Name', 'First Name', 'Middle Name', 'Program', 'Year Level', 'Scholarship Program', '1st Semester 2026-2027', '2nd Semester 2026-2027']];
    usort($rows, fn($a, $b) => [$a[2] !== 'Enrolled', $a[0]['student_id']] <=> [$b[2] !== 'Enrolled', $b[0]['student_id']]);
    foreach ($rows as [$s, $first, $second]) {
        $out[] = [$s['student_id'], $s['last_name'], $s['first_name'], $s['middle_name'], $s['program'] . (trim((string)$s['major']) !== '' ? ' · ' . $s['major'] : ''), $s['year_level'], $s['scholarship_program'], $first, $second];
    }
    $dir = __DIR__ . '/../test-data';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    writeXlsx($dir . '/second_semester_enrollees_2026-2027.xlsx', $out, '2nd Sem Enrollees', [12, 16, 15, 14, 44, 10, 40, 20, 20]);
    return $count;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__) || defined('RUN_SEMESTER_ENROLLMENT')) {
    $c = buildSemesterEnrollments();
    echo "Semester enrollment: 1st Semester 2026-2027 {$c['1st']} enrolled; 2nd Semester 2026-2027 {$c['Enrolled']} enrolled, {$c['Dropped']} dropped, {$c['Not enrolled']} didn't enroll.\n";
    echo "Excel: test-data/second_semester_enrollees_2026-2027.xlsx\n";
}
