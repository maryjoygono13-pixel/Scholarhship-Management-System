<?php
/*
 * Adds 184 test students to the Registrar's database who have NO scholarship program (they never
 * applied for anything) but do have their 1st Semester grades for 2026-2027 — for testing the
 * Dean's List ("Scan Registrar Now" on the Scholars page), which is based on grades alone.
 *
 *   php database/registrar_dean_candidates.php
 *
 * Random department / program and year level, enrolled in that year level's 1st Semester subjects.
 * Not everyone qualifies (rule: GWA 1.50 or better and no subject grade of 2.00 or worse):
 *   70  Dean's Listers
 *   44  almost: 22 with a GWA just above 1.50 (1.51–1.60), 22 with a GWA of 1.50 or better but one
 *       subject at 2.00
 *   70  not eligible: GWA well above 1.50, 15 of them with a failing grade (5.00)
 * Expected scan result: 70 new Dean's Listers from this group.
 *
 * Student IDs: <entry year>4<3-digit number>, e.g. 20254017. Re-running replaces this group only
 * (fixed random seed, so the same students and grades every time). Also writes
 * test-data/dean_list_candidates_184.xlsx with each student's expected result.
 * database/registrar_test_seed.php runs this at the end, so a full rebuild keeps them.
 */

require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/../includes/curriculum_helper.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';
require_once __DIR__ . '/../includes/deans_list_rules.php';

function addDeanListCandidates(): array {
    $schoolYear = '2026-2027';
    $counts = ['eligible' => 70, 'almost_gwa' => 22, 'almost_grade' => 22, 'fail' => 55, 'fail_failing' => 15];   // 184
    mt_srand(5184);   // its own fixed seed

    $db = getRegistrarDB();
    $curriculum = loadCurriculumData();
    if (!$curriculum) throw new RuntimeException('The curriculum (assets/js/curriculum-data.js) could not be read.');
    $programKeys = array_keys($curriculum);
    $towns = array_keys(array_filter(MUNICIPALITIES, fn($t) => $t[2] === 'Southern Leyte'));
    $barangays = ['Poblacion', 'San Isidro', 'San Roque', 'Santa Cruz', 'San Jose', 'Mahayahay', 'Lunsay', 'Combado', 'Asuncion', 'Canturing'];
    $pickBy = fn(string $key, array $list) => $list[crc32($key) % count($list)];
    $pick = fn(array $list) => $list[mt_rand(0, count($list) - 1)];

    // Replace this group only.
    $groupIds = "LENGTH(student_id) = 8 AND SUBSTRING(student_id, 5, 1) = '4'";
    foreach ([REGISTRAR_GRADES_TABLE, REGISTRAR_SUBJECTS_TABLE, REGISTRAR_STUDENTS_TABLE] as $t) $db->exec("DELETE FROM $t WHERE $groupIds");

    // Names: unique against everyone already in the Registrar's database.
    $used = [];
    foreach ($db->query('SELECT first_name, last_name FROM ' . REGISTRAR_STUDENTS_TABLE)->fetchAll(PDO::FETCH_NUM) as [$f, $l]) $used["$f $l"] = true;
    $first = [
        'Male' => ['Aldrin', 'Alvin', 'Arvin', 'Bernard', 'Brix', 'Carl', 'Christopher', 'Dale', 'Darren', 'Dexter', 'Earl', 'Eduardo', 'Elijah', 'Enrico', 'Felix', 'Fernando', 'Froilan', 'Gerard', 'Gian', 'Hanz', 'Harvey', 'Isaac', 'Ivan', 'Jasper', 'Jed', 'Jeffrey', 'Jethro', 'Jhon', 'Jun', 'Karl', 'Kier', 'Lance', 'Leonard', 'Lloyd', 'Marvin', 'Matthew', 'Mico', 'Nash', 'Nico', 'Norman', 'Paolo', 'Ralph', 'Reymart', 'Rico', 'Rommel', 'Russel', 'Sherwin', 'Tristan', 'Vince', 'Wilfredo'],
        'Female' => ['Aira', 'Alexa', 'Althea', 'Amor', 'Angeline', 'Aubrey', 'Bernadette', 'Camille', 'Carmela', 'Chloe', 'Clarisse', 'Daisy', 'Dianne', 'Ellaine', 'Emerald', 'Fatima', 'Francine', 'Gail', 'Hershey', 'Iris', 'Jade', 'Janelle', 'Jessica', 'Jocelyn', 'Kathleen', 'Kaye', 'Kristel', 'Lalaine', 'Liza', 'Mae', 'Maricel', 'Mariel', 'Mylene', 'Nathalie', 'Nina', 'Paula', 'Princess', 'Queenie', 'Rhea', 'Riza', 'Samantha', 'Shaira', 'Sheila', 'Tricia', 'Vanessa', 'Venus', 'Wendy', 'Yvonne', 'Zia', 'Zyra'],
    ];
    $last = ['Advincula', 'Agbayani', 'Alcober', 'Almonte', 'Amistad', 'Apostol', 'Aquino', 'Arnaiz', 'Bacani', 'Balagtas', 'Banzon', 'Basa', 'Batungbakal', 'Bautista', 'Bermudez', 'Bolante', 'Buhay', 'Cabral', 'Cajes', 'Canlas', 'Carpio', 'Castañeda', 'Cayabyab', 'Concepcion', 'Cuenca', 'Dalisay', 'Damasco', 'Datu', 'De Castro', 'Del Mundo', 'Dumaguete', 'Ebarle', 'Ermita', 'Escaño', 'Fabian', 'Falcon', 'Ferrer', 'Gabriel', 'Galvez', 'Gatchalian', 'Gonzaga', 'Hilario', 'Ignacio', 'Jamora', 'Lacuesta', 'Lantajo', 'Legaspi', 'Lopez', 'Luna', 'Macaspac', 'Magbanua', 'Mangubat', 'Mariano', 'Montero', 'Nacional', 'Navarro', 'Olaguer', 'Orbeta', 'Pabalan', 'Palma', 'Pangilinan', 'Pascual', 'Quiambao', 'Rabe', 'Regalado', 'Reyes', 'Sabio', 'Salazar', 'Santos', 'Sarmiento', 'Tabuena', 'Taboada', 'Tejada', 'Torralba', 'Umali', 'Valenzuela', 'Vergara', 'Villareal', 'Ybanez', 'Zaldivar'];
    $middle = ['Abarquez', 'Belarmino', 'Cuyos', 'Dagatan', 'Esteban', 'Garcia', 'Labrador', 'Maglasang', 'Ocampo', 'Ponce', 'Rama', 'Sumampong', 'Tan', 'Uy', 'Villacorta'];

    // One category per student, in random order.
    $categories = [];
    foreach ($counts as $cat => $n) $categories = array_merge($categories, array_fill(0, $n, $cat));
    for ($i = count($categories) - 1; $i > 0; $i--) { $j = mt_rand(0, $i); [$categories[$i], $categories[$j]] = [$categories[$j], $categories[$i]]; }

    $avg = fn(array $g) => round(array_sum($g) / count($g), 2);
    // Grades for one semester's subjects that land in the wanted category.
    $makeGrades = function (string $cat, int $n) use ($pick, $avg): array {
        for ($try = 0; $try < 5000; $try++) {
            $g = [];
            switch ($cat) {
                case 'eligible':       // GWA 1.50 or better, every subject better than 2.00
                    for ($k = 0; $k < $n; $k++) $g[] = $pick([1.00, 1.25, 1.25, 1.50, 1.50, 1.75]);
                    if ($avg($g) <= 1.50 && max($g) < 2.00) return $g;
                    break;
                case 'almost_gwa':     // every subject better than 2.00, but GWA 1.51–1.60
                    for ($k = 0; $k < $n; $k++) $g[] = $pick([1.25, 1.50, 1.50, 1.75, 1.75]);
                    if ($avg($g) >= 1.51 && $avg($g) <= 1.60 && max($g) < 2.00) return $g;
                    break;
                case 'almost_grade':   // GWA 1.50 or better, but one subject at exactly 2.00
                    for ($k = 0; $k < $n; $k++) $g[] = $pick([1.00, 1.25, 1.25, 1.50]);
                    $g[mt_rand(0, $n - 1)] = 2.00;
                    if ($avg($g) <= 1.50) return $g;
                    break;
                case 'fail':           // GWA well above 1.50
                    for ($k = 0; $k < $n; $k++) $g[] = $pick([1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00]);
                    if ($avg($g) >= 1.75) return $g;
                    break;
                case 'fail_failing':   // GWA well above 1.50 and a failing grade
                    for ($k = 0; $k < $n; $k++) $g[] = $pick([1.75, 2.00, 2.25, 2.50, 2.75, 3.00]);
                    $g[mt_rand(0, $n - 1)] = 5.00;
                    if ($avg($g) >= 1.75) return $g;
                    break;
            }
        }
        throw new RuntimeException("Couldn't generate grades for $cat.");
    };

    $insertStudent = $db->prepare('INSERT INTO ' . REGISTRAR_STUDENTS_TABLE . ' (student_id, last_name, first_name, middle_name, gender, birthdate, email, phone, municipality, barangay, scholarship_program, special_qualification, family_income, program, major, year_level, enrollment_status, school_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertSubject = $db->prepare('INSERT INTO ' . REGISTRAR_SUBJECTS_TABLE . ' (student_id, subject_code, subject_name, semester, school_year) VALUES (?, ?, ?, ?, ?)');
    $insertGrade = $db->prepare('INSERT INTO ' . REGISTRAR_GRADES_TABLE . ' (student_id, subject_code, subject_name, semester, school_year, grade) VALUES (?, ?, ?, ?, ?, ?)');

    $rows = [];
    $summary = ['eligible' => 0, 'almost' => 0, 'not' => 0];
    $db->beginTransaction();
    foreach ($categories as $i => $cat) {
        $seq = $i + 1;
        // A program / year level that has 1st Semester subjects in the curriculum.
        do {
            $programKey = $pick($programKeys);
            $yearNum = mt_rand(1, 4);
            $yearLevel = ['', '1st Year', '2nd Year', '3rd Year', '4th Year'][$yearNum];
            $subjects = $curriculum[$programKey][$yearLevel]['1st Semester'] ?? [];
        } while (count($subjects) < 3);
        [$program, $major] = array_pad(explode('|', $programKey, 2), 2, '');

        $gender = mt_rand(0, 1) ? 'Male' : 'Female';
        do { $f = $pick($first[$gender]); $l = $pick($last); } while (isset($used["$f $l"]));
        $used["$f $l"] = true;
        $m = $pick($middle);

        $studentId = (2027 - $yearNum) . '4' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
        $birthdate = sprintf('%04d-%02d-%02d', 2026 - (17 + $yearNum) - mt_rand(0, 1), mt_rand(1, 12), mt_rand(1, 28));
        $email = strtolower(preg_replace('/[^a-z]/i', '', $f) . '.' . preg_replace('/[^a-z]/i', '', $l)) . '.d' . $seq . '@example.com';   // test addresses only
        $phone = '09' . str_pad((string)(crc32($studentId . 'p') % 1000000000), 9, '0', STR_PAD_LEFT);
        // No scholarship program: these students never applied for anything.
        $insertStudent->execute([$studentId, $l, $f, $m, $gender, $birthdate, $email, $phone, $pickBy($studentId . 't', $towns), $pickBy($studentId . 'b', $barangays), '', '', null, $program, $major, $yearLevel, 'Enrolled', $schoolYear]);

        $grades = $makeGrades($cat, count($subjects));
        foreach (array_values($subjects) as $k => $subject) {
            $insertSubject->execute([$studentId, $subject['code'], $subject['name'], '1st Semester', $schoolYear]);
            $insertGrade->execute([$studentId, $subject['code'], $subject['name'], '1st Semester', $schoolYear, $grades[$k]]);
        }

        // Expected result, from the same rule the system uses.
        $stat = ['gwa' => $avg($grades), 'worst' => max($grades)];
        $qualifies = deansListQualifies($stat);
        $expected = $qualifies ? "Dean's Lister" : (str_starts_with($cat, 'almost') ? 'Almost — not eligible' : 'Not eligible');
        $summary[$qualifies ? 'eligible' : (str_starts_with($cat, 'almost') ? 'almost' : 'not')]++;
        $rows[] = [$studentId, $l, $f, $m, $gender, $program . ($major !== '' ? " · $major" : ''), $yearLevel, count($grades), number_format($stat['gwa'], 2), number_format($stat['worst'], 2), $expected, $qualifies ? '' : deansListMissReason($stat)];
    }
    $db->commit();

    usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));
    $dir = __DIR__ . '/../test-data';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    writeXlsx($dir . '/dean_list_candidates_184.xlsx',
        array_merge([['Student ID', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Program', 'Year Level', 'Subjects', '1st Sem GWA', 'Lowest Grade', "Expected (Dean's List)", 'Why not']], $rows),
        "Dean's List Test", [12, 16, 14, 14, 8, 44, 10, 9, 11, 12, 22, 60]);
    return $summary + ['total' => count($rows)];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__) || defined('RUN_DEAN_CANDIDATES')) {
    $s = addDeanListCandidates();
    echo "Dean's List test students: {$s['total']} added (no scholarship program, 1st Semester grades) — {$s['eligible']} Dean's Listers, {$s['almost']} almost, {$s['not']} not eligible.\n";
    echo "Excel: test-data/dean_list_candidates_184.xlsx\n";
}
