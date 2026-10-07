<?php
/*
 * Adds 89 test students to the Registrar's database who already HOLD a scholarship program — listed in
 * the Registrar's scholarship table (REGISTRAR_SCHOLARSHIPS_TABLE, "student_scholarships"), which is what
 * "Scan Registrar Now" reads to bring current scholars into Scholars and Records.
 *
 *   php database/registrar_scholars.php
 *
 * Each one: a random department / program and year level, one of the active scholarship programs on the
 * Scholarships page (spread evenly), granted in the 1st Semester 2026-2027, with 1st AND 2nd Semester
 * 2026-2027 grades and subjects. Grades:
 *   24 Dean's List quality both semesters, 35 good (GWA about 1.6–2.0), 24 average (2.0–2.6),
 *    6 low (2.75 or worse, some with a failing 5.00)
 * Enrollment: all enrolled in the 1st Semester; 83 enrolled in the 2nd Semester, 6 dropped.
 * Expected scan — portal at 1st Semester 2026-2027: all 89 added with their program (24 with the Dean's
 * Lister badge); at 2nd Semester: 83 added (the 6 who dropped aren't — not enrolled), 22 with the badge.
 * Each gets an approved record for the 1st Semester and a Renewal & Retention entry.
 *
 * Student IDs: <entry year>5<3-digit number>, e.g. 20255031. Re-running replaces this group only (fixed
 * random seed). Writes test-data/registrar_scholars_89.xlsx. database/registrar_test_seed.php runs it last.
 */

require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/curriculum_helper.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';
require_once __DIR__ . '/../includes/deans_list_rules.php';

function addRegistrarScholars(): array {
    $sy = '2026-2027';
    mt_srand(8989);   // its own fixed seed
    $db = getRegistrarDB();
    $sms = getDB();
    $curriculum = loadCurriculumData();
    if (!$curriculum) throw new RuntimeException('The curriculum (assets/js/curriculum-data.js) could not be read.');
    $programKeys = array_keys($curriculum);
    $towns = array_keys(array_filter(MUNICIPALITIES, fn($t) => $t[2] === 'Southern Leyte'));
    $barangays = ['Poblacion', 'San Isidro', 'San Roque', 'Santa Cruz', 'San Jose', 'Mahayahay', 'Lunsay', 'Combado', 'Asuncion', 'Canturing'];
    $pickBy = fn(string $key, array $list) => $list[crc32($key) % count($list)];
    $pick = fn(array $list) => $list[mt_rand(0, count($list) - 1)];
    $shuffle = function (array $a): array { for ($i = count($a) - 1; $i > 0; $i--) { $j = mt_rand(0, $i); [$a[$i], $a[$j]] = [$a[$j], $a[$i]]; } return $a; };

    // The scholarship programs offered (Scholarships page), spread evenly over the 89.
    $programs = $sms->query("SELECT name FROM scholarships WHERE LOWER(TRIM(status)) = 'active' ORDER BY type, name")->fetchAll(PDO::FETCH_COLUMN);
    if (!$programs) throw new RuntimeException('There are no active scholarship programs on the Scholarships page.');

    // Tables (the scholarship list is this script's own; enrollments may already exist).
    $S = REGISTRAR_SCHOLARSHIPS_TABLE;
    $db->exec("CREATE TABLE IF NOT EXISTS $S (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        scholarship_program VARCHAR(255) NOT NULL,
        school_year VARCHAR(20) NOT NULL,
        semester VARCHAR(30) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Active',
        granted_on DATE DEFAULT NULL,
        UNIQUE KEY uniq_grant (student_id, scholarship_program, school_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec('CREATE TABLE IF NOT EXISTS ' . REGISTRAR_ENROLLMENTS_TABLE . ' (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        school_year VARCHAR(20) NOT NULL,
        semester VARCHAR(30) NOT NULL,
        enrollment_status VARCHAR(50) NOT NULL,
        enrolled_on DATE DEFAULT NULL,
        UNIQUE KEY uniq_term (student_id, school_year, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Replace this group only.
    $group = "LENGTH(student_id) = 8 AND SUBSTRING(student_id, 5, 1) = '5'";
    foreach ([REGISTRAR_GRADES_TABLE, REGISTRAR_SUBJECTS_TABLE, REGISTRAR_ENROLLMENTS_TABLE, $S, REGISTRAR_STUDENTS_TABLE] as $t) $db->exec("DELETE FROM $t WHERE $group");

    $used = [];
    foreach ($db->query('SELECT first_name, last_name FROM ' . REGISTRAR_STUDENTS_TABLE)->fetchAll(PDO::FETCH_NUM) as [$f, $l]) $used["$f $l"] = true;
    $first = [
        'Male' => ['Adonis', 'Alfie', 'Andro', 'Archie', 'Benjie', 'Bong', 'Cesar', 'Cyrus', 'Dindo', 'Efren', 'Elvis', 'Ferdie', 'Gino', 'Gregorio', 'Homer', 'Ismael', 'Jaime', 'Jomari', 'Junrey', 'Kristoffer', 'Leandro', 'Lemuel', 'Mario', 'Melvin', 'Nestor', 'Orlando', 'Pocholo', 'Rafael', 'Randy', 'Rene', 'Rolando', 'Santino', 'Teodoro', 'Uriel', 'Valentin', 'Wesley', 'Xavier', 'Yuri', 'Zaldy', 'Zion'],
        'Female' => ['Agnes', 'Alma', 'Aurora', 'Belinda', 'Carina', 'Cecilia', 'Corazon', 'Dolores', 'Editha', 'Elena', 'Erlinda', 'Fe', 'Gloria', 'Gracia', 'Imelda', 'Josephine', 'Juvy', 'Leonora', 'Lilibeth', 'Lourdes', 'Luz', 'Marites', 'Marivic', 'Nenita', 'Norma', 'Ofelia', 'Perla', 'Remedios', 'Rosario', 'Sonia', 'Teresita', 'Trinidad', 'Victoria', 'Wilma', 'Yolanda', 'Zenaida', 'Ailyn', 'Jonalyn', 'Rachel', 'Shiela'],
    ];
    $last = ['Abellana', 'Alferez', 'Amodia', 'Antipuesto', 'Bacalso', 'Bajenting', 'Bentulan', 'Cabatingan', 'Calunsag', 'Caminos', 'Cañete', 'Daclan', 'Dayondon', 'Delute', 'Encabo', 'Enriquez', 'Gabutan', 'Gementiza', 'Gilbuena', 'Ilustrisimo', 'Jabagat', 'Labajo', 'Lacaba', 'Lagura', 'Lumapas', 'Mabanag', 'Madelo', 'Mahinay', 'Maraño', 'Montecillo', 'Nadela', 'Nuevo', 'Ocaña', 'Ondoy', 'Pacaña', 'Pajaron', 'Patalinghug', 'Quirog', 'Rabaya', 'Repollo', 'Sabandal', 'Saquilabon', 'Sumaylo', 'Tabal', 'Tagaro', 'Tapales', 'Tiongco', 'Ubas', 'Villaflor', 'Ylanan'];
    $middle = ['Alvarez', 'Basilan', 'Cuizon', 'Diaz', 'Estrera', 'Flores', 'Go', 'Hortelano', 'Lim', 'Mercado', 'Oyao', 'Pepito', 'Ramos', 'Sy', 'Tumulak'];

    $profiles = $shuffle(array_merge(array_fill(0, 24, 'deans'), array_fill(0, 35, 'good'), array_fill(0, 24, 'average'), array_fill(0, 6, 'low')));   // 89
    $assigned = [];
    for ($i = 0; $i < 89; $i++) $assigned[] = $programs[$i % count($programs)];
    $assigned = $shuffle($assigned);
    $droppedIdx = array_flip(array_slice($shuffle(range(0, 88)), 0, 6));   // 6 dropped in the 2nd Semester

    $avg = fn(array $g) => round(array_sum($g) / count($g), 2);
    $makeGrades = function (string $profile, int $n) use ($pick, $avg): array {
        for ($try = 0; $try < 5000; $try++) {
            $g = [];
            switch ($profile) {
                case 'deans':   for ($k = 0; $k < $n; $k++) $g[] = $pick([1.00, 1.25, 1.25, 1.50, 1.50, 1.75]); if ($avg($g) <= 1.50 && max($g) < 2.00) return $g; break;
                case 'good':    for ($k = 0; $k < $n; $k++) $g[] = $pick([1.25, 1.50, 1.75, 1.75, 2.00, 2.25]); if ($avg($g) >= 1.60 && $avg($g) <= 2.00) return $g; break;
                case 'average': for ($k = 0; $k < $n; $k++) $g[] = $pick([1.75, 2.00, 2.25, 2.50, 2.75, 3.00]); if ($avg($g) > 2.00 && $avg($g) <= 2.60) return $g; break;
                case 'low':     for ($k = 0; $k < $n; $k++) $g[] = $pick([2.25, 2.50, 2.75, 3.00, 3.00]); if (mt_rand(0, 1)) $g[mt_rand(0, $n - 1)] = 5.00; if ($avg($g) >= 2.75) return $g; break;
            }
        }
        throw new RuntimeException("Couldn't generate grades for $profile.");
    };

    $insertStudent = $db->prepare('INSERT INTO ' . REGISTRAR_STUDENTS_TABLE . ' (student_id, last_name, first_name, middle_name, gender, birthdate, email, phone, municipality, barangay, scholarship_program, special_qualification, family_income, program, major, year_level, enrollment_status, school_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertSubject = $db->prepare('INSERT INTO ' . REGISTRAR_SUBJECTS_TABLE . ' (student_id, subject_code, subject_name, semester, school_year) VALUES (?, ?, ?, ?, ?)');
    $insertGrade = $db->prepare('INSERT INTO ' . REGISTRAR_GRADES_TABLE . ' (student_id, subject_code, subject_name, semester, school_year, grade) VALUES (?, ?, ?, ?, ?, ?)');
    $insertEnroll = $db->prepare('INSERT INTO ' . REGISTRAR_ENROLLMENTS_TABLE . ' (student_id, school_year, semester, enrollment_status, enrolled_on) VALUES (?, ?, ?, ?, ?)');
    $insertGrant = $db->prepare("INSERT INTO $S (student_id, scholarship_program, school_year, semester, status, granted_on) VALUES (?, ?, ?, '1st Semester', 'Active', '2026-08-15')");

    $rows = [];
    $summary = ['total' => 0, 'enrolled2nd' => 0, 'dropped2nd' => 0, 'deans2nd' => 0];
    $db->beginTransaction();
    for ($i = 0; $i < 89; $i++) {
        $seq = $i + 1;
        do {
            $programKey = $pick($programKeys);
            $yearNum = mt_rand(1, 4);
            $yearLevel = ['', '1st Year', '2nd Year', '3rd Year', '4th Year'][$yearNum];
            $sem1 = $curriculum[$programKey][$yearLevel]['1st Semester'] ?? [];
            $sem2 = $curriculum[$programKey][$yearLevel]['2nd Semester'] ?? [];
        } while (count($sem1) < 3 || count($sem2) < 3);
        [$program, $major] = array_pad(explode('|', $programKey, 2), 2, '');

        $gender = mt_rand(0, 1) ? 'Male' : 'Female';
        do { $f = $pick($first[$gender]); $l = $pick($last); } while (isset($used["$f $l"]));
        $used["$f $l"] = true;
        $m = $pick($middle);

        $studentId = (2027 - $yearNum) . '5' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
        $birthdate = sprintf('%04d-%02d-%02d', 2026 - (17 + $yearNum) - mt_rand(0, 1), mt_rand(1, 12), mt_rand(1, 28));
        $email = strtolower(preg_replace('/[^a-z]/i', '', $f) . '.' . preg_replace('/[^a-z]/i', '', $l)) . '.s' . $seq . '@example.com';   // test addresses only
        $phone = '09' . str_pad((string)(crc32($studentId . 'p') % 1000000000), 9, '0', STR_PAD_LEFT);
        $scholarship = $assigned[$i];
        $dropped = isset($droppedIdx[$i]);
        $insertStudent->execute([$studentId, $l, $f, $m, $gender, $birthdate, $email, $phone, $pickBy($studentId . 't', $towns), $pickBy($studentId . 'b', $barangays), $scholarship, '', null, $program, $major, $yearLevel, 'Enrolled', $sy]);
        $insertGrant->execute([$studentId, $scholarship, $sy]);

        // 1st Semester: enrolled, graded. 2nd Semester: enrolled and graded, or dropped (no subjects / grades).
        $insertEnroll->execute([$studentId, $sy, '1st Semester', 'Enrolled', '2026-08-04']);
        $insertEnroll->execute([$studentId, $sy, '2nd Semester', $dropped ? 'Dropped' : 'Enrolled', '2027-01-11']);
        $profile = $profiles[$i];
        $stats = [];
        foreach (['1st Semester' => $sem1, '2nd Semester' => $sem2] as $sem => $subjects) {
            if ($dropped && $sem === '2nd Semester') continue;
            $grades = $makeGrades($profile, count($subjects));
            foreach (array_values($subjects) as $k => $subject) {
                $insertSubject->execute([$studentId, $subject['code'], $subject['name'], $sem, $sy]);
                $insertGrade->execute([$studentId, $subject['code'], $subject['name'], $sem, $sy, $grades[$k]]);
            }
            $stats[$sem] = ['gwa' => $avg($grades), 'worst' => max($grades)];
        }

        $deans1st = deansListQualifies($stats['1st Semester']);
        $deans2nd = isset($stats['2nd Semester']) && deansListQualifies($stats['2nd Semester']);
        $summary['total']++;
        $summary[$dropped ? 'dropped2nd' : 'enrolled2nd']++;
        if ($deans2nd) $summary['deans2nd']++;
        $rows[] = [
            $studentId, $l, $f, $m, $program . ($major !== '' ? " · $major" : ''), $yearLevel, $scholarship,
            number_format($stats['1st Semester']['gwa'], 2), isset($stats['2nd Semester']) ? number_format($stats['2nd Semester']['gwa'], 2) : '',
            $dropped ? 'Dropped' : 'Enrolled',
            'Added as ' . $scholarship . ' scholar',
            $deans1st ? "Dean's Lister" : '',
            $dropped ? 'Not added (dropped — not enrolled for 2nd Semester)' : 'Added as ' . $scholarship . ' scholar',
            $deans2nd ? "Dean's Lister" : '',
        ];
    }
    $db->commit();

    usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));
    $dir = __DIR__ . '/../test-data';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    writeXlsx($dir . '/registrar_scholars_89.xlsx',
        array_merge([['Student ID', 'Last Name', 'First Name', 'Middle Name', 'Program', 'Year Level', 'Scholarship Program', '1st Sem GWA', '2nd Sem GWA', '2nd Semester', 'Expected scan (portal at 1st Semester)', "Dean's Lister (1st Sem)", 'Expected scan (portal at 2nd Semester)', "Dean's Lister (2nd Sem)"]], $rows),
        'Registrar Scholars', [12, 16, 14, 14, 44, 10, 44, 11, 11, 12, 44, 20, 52, 20], ['center' => true]);
    return $summary;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__) || defined('RUN_REGISTRAR_SCHOLARS')) {
    $s = addRegistrarScholars();
    echo "Registrar scholars: {$s['total']} added with a scholarship program — 2nd Semester 2026-2027: {$s['enrolled2nd']} enrolled, {$s['dropped2nd']} dropped; {$s['deans2nd']} qualify for the Dean's List.\n";
    echo "Excel: test-data/registrar_scholars_89.xlsx\n";
}
