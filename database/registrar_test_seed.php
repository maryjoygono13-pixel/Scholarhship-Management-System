<?php
/*
 * TEST DATA ONLY — builds a stand-in for the Registrar's database so the "Fetch from Registrar
 * Database" feature (Data Management) can be tested before the real one is connected.
 *
 * Creates database `registrar_db` (connection settings: config/registrar_database.php) with:
 *   students — 25 students with a random program and year level
 *   grades   — a grade for EVERY subject of each student's year level (1st and 2nd Semester),
 *              taken from the same curriculum the system uses (assets/js/curriculum-data.js)
 * and writes the Excel list of the students (Student ID + names only, no grades) to
 *   test-data/registrar_test_students.xlsx
 *
 * Run from the project folder:   C:\xampp\php\php.exe database\registrar_test_seed.php
 * Running it again recreates the same 25 students (fixed random seed), so results are repeatable.
 */

require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/../includes/curriculum_helper.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/scholarship_criteria_helper.php';
require_once __DIR__ . '/../includes/special_qualification_helper.php';
require_once __DIR__ . '/../includes/locations.php';

const SEED_STUDENT_COUNT = 25;
const SEED_SCHOOL_YEAR = '2026-2027';
const SEED_FIRST_YEAR_COUNT = 30;   // first-year students: enrolled, 1st Semester subjects, no grades
// Students given a real email address so notifications can be tested end to end.
const SEED_REAL_EMAILS = [
    '20269025' => 'johnandieedejer375@gmail.com',   // Camille Ocampo Castillo
];
// Students who are NOT officially enrolled this Academic Year, to test the CHED list check:
// Student ID => [enrollment_status, school_year].
const SEED_ENROLLMENT_EXCEPTIONS = [
    '20239010' => ['Dropped', SEED_SCHOOL_YEAR],    // Princess Rosales — dropped
    '20249021' => ['Enrolled', '2025-2026'],        // Princess Bautista — last enrolled last year
];

mt_srand(2026); // fixed seed: same students and grades every run

// 1. Create the database and tables (on the server/port in config/registrar_database.php).
$server = new PDO('mysql:host=' . REGISTRAR_DB_HOST . ';port=' . (int)REGISTRAR_DB_PORT . ';charset=utf8mb4', REGISTRAR_DB_USER, REGISTRAR_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE IF NOT EXISTS `' . REGISTRAR_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db = getRegistrarDB();
$db->exec('DROP TABLE IF EXISTS ' . REGISTRAR_SUBJECTS_TABLE);
$db->exec('DROP TABLE IF EXISTS ' . REGISTRAR_GRADES_TABLE);
$db->exec('DROP TABLE IF EXISTS ' . REGISTRAR_STUDENTS_TABLE);
$db->exec('CREATE TABLE ' . REGISTRAR_STUDENTS_TABLE . ' (
    student_id VARCHAR(20) PRIMARY KEY,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT \'\',
    gender VARCHAR(10) DEFAULT \'\',
    birthdate DATE DEFAULT NULL,
    email VARCHAR(255) DEFAULT \'\',
    program VARCHAR(255) NOT NULL,
    major VARCHAR(255) DEFAULT \'\',
    year_level VARCHAR(20) NOT NULL,
    phone VARCHAR(20) DEFAULT \'\',
    municipality VARCHAR(100) DEFAULT \'\',
    barangay VARCHAR(100) DEFAULT \'\',
    scholarship_program VARCHAR(255) DEFAULT \'\',
    special_qualification VARCHAR(255) DEFAULT \'\',
    family_income DECIMAL(12,2) DEFAULT NULL,
    enrollment_status VARCHAR(20) NOT NULL DEFAULT \'Enrolled\',
    school_year VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE ' . REGISTRAR_GRADES_TABLE . ' (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(255) DEFAULT \'\',
    semester VARCHAR(20) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    grade DECIMAL(3,2) NOT NULL,
    UNIQUE KEY uq_grade (student_id, subject_code, semester, school_year),
    KEY idx_student (student_id),
    FOREIGN KEY (student_id) REFERENCES ' . REGISTRAR_STUDENTS_TABLE . '(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
// Subjects each student is enrolled in this term (no grade yet).
$db->exec('CREATE TABLE ' . REGISTRAR_SUBJECTS_TABLE . ' (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(255) DEFAULT \'\',
    semester VARCHAR(20) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    UNIQUE KEY uq_enrolled_subject (student_id, subject_code, semester, school_year),
    KEY idx_student (student_id),
    FOREIGN KEY (student_id) REFERENCES ' . REGISTRAR_STUDENTS_TABLE . '(student_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

// 2. Programs as they appear in the curriculum ("Program|Major" for programs with a major).
$curriculum = loadCurriculumData();
if (!$curriculum) {
    fwrite(STDERR, "The curriculum (assets/js/curriculum-data.js) could not be read.\n");
    exit(1);
}
$programKeys = array_keys($curriculum);

// Scholarship programs to assign: every ACTIVE program on the Scholarships page that students
// apply for through this system (CHED's CMSP/COSCHO are applied for on CHED's site; Merit has
// no applications). Students are spread over them in turn, so each program gets a few.
$sms = getDB();
$scholarshipPrograms = [];
foreach ($sms->query("SELECT * FROM scholarships WHERE LOWER(TRIM(status)) = 'active' ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC) as $sp) {
    if (externalApplicationUrl($sp) !== null || isMeritScholarshipType((string)$sp['type'])) continue;
    $sp['needs_income'] = scholarshipNeedsFamilyIncome($sms, (int)$sp['id']);
    $sp['qualification'] = specialQualificationConfig((string)$sp['type']);
    $scholarshipPrograms[] = $sp;
}
if (!$scholarshipPrograms) {
    fwrite(STDERR, "No active scholarship programs to assign. Add one on the Scholarships page first.\n");
    exit(1);
}
$towns = array_keys(array_filter(MUNICIPALITIES, fn($t) => $t[2] === 'Southern Leyte'));
$barangays = ['Poblacion', 'San Isidro', 'San Roque', 'Santa Cruz', 'San Jose', 'Mahayahay', 'Lunsay', 'Combado', 'Asuncion', 'Canturing'];
// Picks without touching mt_rand, so the 25 students and their grades stay exactly the same.
$pickBy = fn(string $key, array $list) => $list[crc32($key) % count($list)];
$yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

$firstNames = [
    'Male' => ['Juan', 'Mark', 'John Paul', 'Christian', 'Jerome', 'Kenneth', 'Ralph', 'Miguel', 'Joshua', 'Adrian', 'Carlo', 'Vincent', 'Rhey', 'Paolo'],
    'Female' => ['Maria', 'Angelica', 'Kristine', 'Jasmine', 'Princess', 'Nicole', 'Camille', 'Andrea', 'Shiela', 'Joanna', 'Bea', 'Rica', 'Mae', 'Lovely'],
];
$lastNames = ['Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Villanueva', 'Ramos', 'Castillo', 'Flores', 'Aquino', 'Navarro', 'Torres', 'Gonzales', 'Lopez', 'Fernandez', 'Pascual', 'Salazar', 'Cabrera', 'Domingo', 'Rosales', 'Abellana', 'Lagunay', 'Montejo', 'Pabillore'];
$middleNames = ['Alvarez', 'Bacalso', 'Cuizon', 'Dacanay', 'Espina', 'Fuentes', 'Gabriel', 'Hilario', 'Ignacio', 'Jimenez', 'Kintanar', 'Lumapas', 'Mercado', 'Nuñez', 'Ocampo'];

// Grade profiles, so the set covers Full Merit, Half Merit, average and failing students.
$profiles = array_merge(array_fill(0, 5, 'excellent'), array_fill(0, 8, 'good'), array_fill(0, 8, 'average'), array_fill(0, 4, 'struggling'));
shuffle($profiles);
$gradeFor = function (string $profile): float {
    $pick = fn(array $opts) => $opts[mt_rand(0, count($opts) - 1)];
    switch ($profile) {
        case 'excellent':  return $pick([1.00, 1.00, 1.25, 1.25, 1.25, 1.50]);
        case 'good':       return $pick([1.25, 1.50, 1.50, 1.75, 1.75, 2.00]);
        case 'average':    return $pick([1.75, 2.00, 2.25, 2.25, 2.50, 2.75]);
        default:           return mt_rand(1, 12) === 1 ? 5.00 : $pick([2.25, 2.50, 2.75, 3.00, 3.00]); // occasional failing grade
    }
};

$insertStudent = $db->prepare('INSERT INTO ' . REGISTRAR_STUDENTS_TABLE . ' (student_id, last_name, first_name, middle_name, gender, birthdate, email, phone, municipality, barangay, scholarship_program, special_qualification, family_income, program, major, year_level, enrollment_status, school_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insertGrade = $db->prepare('INSERT INTO ' . REGISTRAR_GRADES_TABLE . ' (student_id, subject_code, subject_name, semester, school_year, grade) VALUES (?, ?, ?, ?, ?, ?)');

$usedNames = [];
$excelRows = [['Student ID', 'Last Name', 'First Name', 'Middle Name']];
$summary = [];
$gradeCount = 0;
$db->beginTransaction();
for ($i = 1; $i <= SEED_STUDENT_COUNT; $i++) {
    $programKey = $programKeys[mt_rand(0, count($programKeys) - 1)];
    [$program, $major] = array_pad(explode('|', $programKey, 2), 2, '');
    $yearLevel = $yearLevels[mt_rand(0, 3)];
    $yearNum = (int)$yearLevel;
    $gender = mt_rand(0, 1) ? 'Male' : 'Female';

    do {
        $first = $firstNames[$gender][mt_rand(0, count($firstNames[$gender]) - 1)];
        $last = $lastNames[mt_rand(0, count($lastNames) - 1)];
    } while (isset($usedNames["$first $last"]));
    $usedNames["$first $last"] = true;
    $middle = $middleNames[mt_rand(0, count($middleNames) - 1)];

    // Student ID: entry year (2026 for 1st Year, 2025 for 2nd, ...) + 9 + 3-digit number.
    $studentId = (2027 - $yearNum) . '9' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
    $birthYear = 2026 - (17 + $yearNum);
    $birthdate = sprintf('%04d-%02d-%02d', $birthYear, mt_rand(1, 12), mt_rand(1, 28));
    $email = strtolower(preg_replace('/[^a-z]/i', '', $first) . '.' . preg_replace('/[^a-z]/i', '', $last)) . '@example.com'; // test addresses only
    // Real inboxes used for testing notifications (Student ID => email).
    $email = SEED_REAL_EMAILS[$studentId] ?? $email;

    // The scholarship program this student applied for, plus what that program asks for.
    $sp = $scholarshipPrograms[($i - 1) % count($scholarshipPrograms)];
    $qualification = '';
    if ($sp['qualification']) {
        $options = array_values(array_filter($sp['qualification']['options'], fn($o) => $o !== SPECIAL_QUALIFICATION_OTHER));
        $qualification = $pickBy($studentId . 'q', $options);
    }
    $income = $sp['needs_income'] ? (float)(6000 + (crc32($studentId . 'i') % 15) * 1000) : null; // ₱6,000–₱20,000
    $phone = '09' . str_pad((string)(crc32($studentId . 'p') % 1000000000), 9, '0', STR_PAD_LEFT);
    $town = $pickBy($studentId . 't', $towns);
    $barangay = $pickBy($studentId . 'b', $barangays);

    $insertStudent->execute([$studentId, $last, $first, $middle, $gender, $birthdate, $email, $phone, $town, $barangay, $sp['name'], $qualification, $income, $program, $major, $yearLevel, 'Enrolled', SEED_SCHOOL_YEAR]);

    // A grade for every subject of this year level, both semesters.
    $profile = $profiles[$i - 1];
    $n = 0;
    foreach (['1st Semester', '2nd Semester'] as $sem) {
        foreach ($curriculum[$programKey][$yearLevel][$sem] ?? [] as $subject) {
            $insertGrade->execute([$studentId, $subject['code'], $subject['name'], $sem, SEED_SCHOOL_YEAR, $gradeFor($profile)]);
            $n++;
        }
    }
    $gradeCount += $n;
    $excelRows[] = [$studentId, $last, $first, $middle];
    $summary[] = sprintf('%s  %-24s %-9s %2d grades  -> %s%s%s', $studentId, "$last, $first", $yearLevel, $n, $sp['name'], $qualification !== '' ? " [$qualification]" : '', $income !== null ? ' [income ₱' . number_format($income) . ']' : '');
}
$db->commit();

// Enrollment exceptions (applied after inserting, so the students and grades above stay the same).
$setEnrollment = $db->prepare('UPDATE ' . REGISTRAR_STUDENTS_TABLE . ' SET enrollment_status = ?, school_year = ? WHERE student_id = ?');
foreach (SEED_ENROLLMENT_EXCEPTIONS as $exId => [$exStatus, $exYear]) $setEnrollment->execute([$exStatus, $exYear, $exId]);

// A CHED-style list for the "Verify CHED Enrollment List" test: title rows first, no Student IDs,
// names in capitals with a middle initial — 20 of the students above plus 3 who aren't on record.
$cs = $db->query('SELECT * FROM ' . REGISTRAR_STUDENTS_TABLE . " WHERE student_id NOT LIKE '20268%' ORDER BY student_id")->fetchAll(PDO::FETCH_ASSOC);
$ched = [
    ['COMMISSION ON HIGHER EDUCATION'],
    ['Tertiary Education Subsidy (TES) — List of Grantees for Validation'],
    ['Higher Education Institution: College of Maasin', '', '', '', 'Academic Year: ' . SEED_SCHOOL_YEAR],
    [],
    ['SEQ', 'LAST NAME', 'FIRST NAME', 'MIDDLE NAME', 'SEX', 'BIRTHDATE', 'PROGRAM', 'YEAR LEVEL'],
];
$seq = 0;
foreach (array_slice($cs, 0, 20) as $c) {
    $firstName = mb_strtoupper($c['first_name']);
    if ($firstName === 'MARIA') $firstName = 'MA.'; // CHED often abbreviates Maria
    $ched[] = [(string)++$seq, mb_strtoupper($c['last_name']), $firstName, mb_strtoupper(mb_substr($c['middle_name'], 0, 1)) . '.', mb_strtoupper(mb_substr($c['gender'], 0, 1)), date('m/d/Y', strtotime($c['birthdate'])), mb_strtoupper($c['program']), $c['year_level']];
}
foreach ([['ESPINOSA', 'RONALDO', 'T.', 'M', '03/14/2005', 'BS INFORMATION TECHNOLOGY', '2nd Year'], ['MARQUEZ', 'LIEZEL', 'A.', 'F', '11/02/2004', 'BS NURSING', '3rd Year'], ['TAN', 'GERALD', 'B.', 'M', '07/21/2006', 'BS ACCOUNTANCY', '1st Year']] as $x) {
    $ched[] = array_merge([(string)++$seq], $x);
}
$excelDir = __DIR__ . '/../test-data';
if (!is_dir($excelDir)) mkdir($excelDir, 0777, true);
writeXlsx($excelDir . '/ched_enrollment_list_sample.xlsx', $ched, 'TES Grantees', [6, 16, 16, 13, 6, 12, 36, 11]);

// ---------------------------------------------------------------------------------------------
// 30 FIRST-YEAR students: officially enrolled in SEED_SCHOOL_YEAR, no grades yet, enrolled in
// the 1st Semester subjects of their program's 1st Year curriculum. Student IDs 20268001–20268030.
// Also writes test-data/first_year_enrollment_check.xlsx — a CHED-style list of these 30 for the
// "Verify CHED Enrollment List" test (expected: all 30 Enrolled).
// ---------------------------------------------------------------------------------------------
mt_srand(3030); // its own fixed seed
$firstYearRows = [];
$subjectCount = 0;
$insertSubject = $db->prepare('INSERT INTO ' . REGISTRAR_SUBJECTS_TABLE . ' (student_id, subject_code, subject_name, semester, school_year) VALUES (?, ?, ?, ?, ?)');
$db->beginTransaction();
for ($k = 1; $k <= SEED_FIRST_YEAR_COUNT; $k++) {
    $programKey = $programKeys[mt_rand(0, count($programKeys) - 1)];
    [$program, $major] = array_pad(explode('|', $programKey, 2), 2, '');
    $gender = mt_rand(0, 1) ? 'Male' : 'Female';
    do {
        $first = $firstNames[$gender][mt_rand(0, count($firstNames[$gender]) - 1)];
        $last = $lastNames[mt_rand(0, count($lastNames) - 1)];
    } while (isset($usedNames["$first $last"]));
    $usedNames["$first $last"] = true;
    $middle = $middleNames[mt_rand(0, count($middleNames) - 1)];
    $studentId = '20268' . str_pad((string)$k, 3, '0', STR_PAD_LEFT);
    $birthdate = sprintf('2008-%02d-%02d', mt_rand(1, 12), mt_rand(1, 28)); // 18 this year
    $email = strtolower(preg_replace('/[^a-z]/i', '', $first) . '.' . preg_replace('/[^a-z]/i', '', $last)) . '.fy@example.com'; // test addresses only
    $phone = '09' . str_pad((string)(crc32($studentId . 'p') % 1000000000), 9, '0', STR_PAD_LEFT);

    $insertStudent->execute([$studentId, $last, $first, $middle, $gender, $birthdate, $email, $phone, $pickBy($studentId . 't', $towns), $pickBy($studentId . 'b', $barangays), '', '', null, $program, $major, '1st Year', 'Enrolled', SEED_SCHOOL_YEAR]);

    $subjects = $curriculum[$programKey]['1st Year']['1st Semester'] ?? [];
    foreach ($subjects as $subject) {
        $insertSubject->execute([$studentId, $subject['code'], $subject['name'], '1st Semester', SEED_SCHOOL_YEAR]);
    }
    $subjectCount += count($subjects);
    $firstYearRows[] = [$studentId, $last, $first, $middle, $gender, $birthdate, $program . ($major !== '' ? " · $major" : ''), count($subjects)];
}
$db->commit();

// CHED-style verification list of the 30 (title rows, names in capitals, middle initial, no Student IDs).
$fyList = [
    ['COMMISSION ON HIGHER EDUCATION'],
    ['Tertiary Education Subsidy (TES) — New First Year Grantees for Enrollment Validation'],
    ['Higher Education Institution: College of Maasin', '', '', '', 'Academic Year: ' . SEED_SCHOOL_YEAR, '', '1st Semester'],
    [],
    ['SEQ', 'LAST NAME', 'FIRST NAME', 'MIDDLE NAME', 'SEX', 'BIRTHDATE', 'PROGRAM', 'YEAR LEVEL'],
];
foreach ($firstYearRows as $idx => [$fid, $flast, $ffirst, $fmiddle, $fgender, $fbirth, $fprogram]) {
    $fyList[] = [(string)($idx + 1), mb_strtoupper($flast), mb_strtoupper($ffirst), mb_strtoupper(mb_substr($fmiddle, 0, 1)) . '.', mb_strtoupper(mb_substr($fgender, 0, 1)), date('m/d/Y', strtotime($fbirth)), mb_strtoupper(explode(' · ', $fprogram)[0]), '1st Year'];
}
writeXlsx($excelDir . '/first_year_enrollment_check.xlsx', $fyList, 'First Year Grantees', [6, 16, 16, 13, 6, 12, 36, 11]);
// 3. The Excel list for the fetch test: Student ID and names only — no grades.
$excelDir = __DIR__ . '/../test-data';
if (!is_dir($excelDir)) mkdir($excelDir, 0777, true);
$excelPath = $excelDir . '/registrar_test_students.xlsx';
writeXlsx($excelPath, $excelRows, 'Students', [14, 18, 16, 16]);

echo "Registrar test database ready: " . REGISTRAR_DB_NAME . " on " . REGISTRAR_DB_HOST . ":" . REGISTRAR_DB_PORT . "\n";
echo SEED_STUDENT_COUNT . " students, $gradeCount grades (school year " . SEED_SCHOOL_YEAR . ")\n\n";
echo implode("\n", $summary) . "\n\n";
echo "Excel list (no grades): test-data/registrar_test_students.xlsx\n";
echo "CHED-style list for the enrollment check: test-data/ched_enrollment_list_sample.xlsx\n\n";
echo SEED_FIRST_YEAR_COUNT . " first-year students (no grades, $subjectCount 1st Semester subjects):\n";
foreach ($firstYearRows as [$fid, $flast, $ffirst, , , , $fprogram, $fsubjects]) echo sprintf('%s  %-24s %-55s %2d subjects', $fid, "$flast, $ffirst", $fprogram, $fsubjects), "\n";
echo "First-year verification list: test-data/first_year_enrollment_check.xlsx\n";

// =============================================================================================
// BATCH OF 400 STUDENTS (its own fixed random seed, so nothing above changes)
//   Group A — 135 students: random year level, enrolled in that year's subjects, grades for the
//             1st Semester only, a non-CHED scholarship program  -> batch_A_135_first_sem_grades.xlsx
//   Group B — 115 students: random year level and subjects, grades for 1st AND 2nd Semester,
//             a non-CHED scholarship program                     -> batch_B_115_full_year_grades.xlsx
//   Group C — 150 students applying for a CHED scholarship (CMSP or COSCHO), random year level,
//             enrolled in 1st Semester subjects. 20 of them are NOT officially enrolled (10 dropped,
//             10 last enrolled 2025-2026). The CHED-style list also has 15 UNREGISTERED students
//             (not in this database)                             -> batch_C_ched_verification.xlsx
//             Expected verification: 130 Enrolled, 20 Not enrolled, 15 Not found.
// Student IDs: <entry year><group digit 1/2/3><3-digit number>, e.g. 20251007, 20242015, 20263042.
// =============================================================================================
const BATCH_A_COUNT = 135;
const BATCH_B_COUNT = 115;
const BATCH_C_COUNT = 150;
const BATCH_C_DROPPED = 10;        // registered, but dropped
const BATCH_C_LAST_YEAR = 10;      // registered, but last enrolled in 2025-2026
const BATCH_C_UNREGISTERED = 15;   // only on the CHED list, not in the database
mt_srand(4000);

$bFirst = [
    'Male' => ['Aaron', 'Adrian', 'Albert', 'Alfred', 'Allan', 'Andrei', 'Angelo', 'Anthony', 'Arnel', 'Benedict', 'Bryan', 'Carlo', 'Cedric', 'Christian', 'Clarence', 'Daniel', 'Darwin', 'Dennis', 'Dominic', 'Edgar', 'Elmer', 'Emmanuel', 'Ernesto', 'Francis', 'Gabriel', 'Gerald', 'Gilbert', 'Harold', 'Ian', 'Jacob', 'Jayson', 'Jericho', 'Jerome', 'Joel', 'John Mark', 'Jomar', 'Jonathan', 'Joseph', 'Joshua', 'Julius', 'Justin', 'Kenneth', 'Kevin', 'Kyle', 'Lawrence', 'Leo', 'Lester', 'Lorenzo', 'Marco', 'Mark Anthony', 'Martin', 'Michael', 'Nathaniel', 'Neil', 'Noel', 'Oliver', 'Patrick', 'Paul', 'Rafael', 'Ramon', 'Raymond', 'Renz', 'Ricardo', 'Rodel', 'Ronald', 'Ryan', 'Samuel', 'Sean', 'Stephen', 'Timothy', 'Victor', 'Vincent', 'Warren', 'Zandro'],
    'Female' => ['Abigail', 'Aileen', 'Alyssa', 'Andrea', 'Angela', 'Angelica', 'Anne', 'April', 'Arlene', 'Bea', 'Bianca', 'Carla', 'Catherine', 'Charlene', 'Cheska', 'Christine', 'Claire', 'Cristina', 'Danica', 'Diana', 'Divine', 'Donna', 'Elaine', 'Ella', 'Erika', 'Faith', 'Frances', 'Gemma', 'Grace', 'Hannah', 'Hazel', 'Irene', 'Isabel', 'Jamie', 'Janine', 'Jasmine', 'Jean', 'Jenny', 'Jessa', 'Joan', 'Joy', 'Julie Ann', 'Karen', 'Katrina', 'Kim', 'Kristine', 'Lea', 'Leah', 'Lorraine', 'Louise', 'Lyka', 'Marian', 'Marie', 'Marjorie', 'Mary Grace', 'Mary Joy', 'Mica', 'Michelle', 'Nicole', 'Patricia', 'Pauline', 'Precious', 'Rachelle', 'Regine', 'Rose', 'Ruby', 'Sarah', 'Sheena', 'Sofia', 'Stephanie', 'Trisha', 'Vanessa', 'Veronica', 'Ysabel'],
];
$bLast = ['Abad', 'Abella', 'Acosta', 'Agustin', 'Alcantara', 'Alvarado', 'Amper', 'Andrade', 'Angeles', 'Aragon', 'Arceo', 'Arellano', 'Ariola', 'Avila', 'Bacarro', 'Balili', 'Baluyot', 'Barrientos', 'Belmonte', 'Benitez', 'Bernardo', 'Buenaventura', 'Cabahug', 'Cabañero', 'Calderon', 'Camacho', 'Canete', 'Cardenas', 'Catubig', 'Cervantes', 'Corpuz', 'Cruzado', 'Dacula', 'Dagohoy', 'David', 'Dayrit', 'De Guzman', 'De Leon', 'Del Rosario', 'Delos Reyes', 'Diaz', 'Dimaculangan', 'Dizon', 'Esguerra', 'Espiritu', 'Estrada', 'Evangelista', 'Fajardo', 'Feliciano', 'Florendo', 'Galang', 'Gallardo', 'Garduce', 'Gomez', 'Guevarra', 'Hernandez', 'Ilagan', 'Jacinto', 'Javier', 'Lacson', 'Lagman', 'Lim', 'Llamas', 'Lozada', 'Macaraeg', 'Madrid', 'Magno', 'Malinao', 'Manalo', 'Marquez', 'Medina', 'Mercado', 'Miranda', 'Molina', 'Morales', 'Nacua', 'Natividad', 'Nepomuceno', 'Ocampo', 'Ompad', 'Ortega', 'Pacquiao', 'Padilla', 'Panganiban', 'Paredes', 'Perez', 'Quijano', 'Quimpo', 'Rabago', 'Ramirez', 'Rivera', 'Roble', 'Robles', 'Rodriguez', 'Rojas', 'Romero', 'Sabado', 'Salcedo', 'Samson', 'Santiago', 'Saturnino', 'Sebastian', 'Serrano', 'Silva', 'Soriano', 'Sumalinog', 'Tabada', 'Tagle', 'Tan', 'Tolentino', 'Tumulak', 'Uy', 'Valdez', 'Vasquez', 'Velasco', 'Ventura', 'Villamor', 'Yap', 'Ybañez', 'Zamora'];
$bMiddle = ['Alcala', 'Bautista', 'Cabrera', 'Dela Pena', 'Enriquez', 'Fernandez', 'Gamboa', 'Herrera', 'Ignacio', 'Jumawan', 'Laurente', 'Manlapaz', 'Nuñez', 'Osorio', 'Pagaduan', 'Quirante', 'Rosales', 'Sison', 'Tabanao', 'Umali', 'Valencia', 'Yamson', 'Zabala', 'Andaya', 'Bonifacio', 'Cortes', 'Dumlao', 'Escobar', 'Flores', 'Gutierrez'];
$bProfiles = ['excellent', 'good', 'good', 'good', 'average', 'average', 'average', 'struggling'];

// CHED programs from the Scholarships page (CMSP, COSCHO).
$chedPrograms = [];
foreach ($sms->query("SELECT * FROM scholarships WHERE LOWER(TRIM(status)) = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) as $sp) {
    if (externalApplicationUrl($sp) !== null) $chedPrograms[] = $sp['name'];
}
if (!$chedPrograms) $chedPrograms = ['CMSP (CHED Merit Scholarship Program)', 'COSCHO (Scholarship for Coconut Farmers and Their Families)'];

$batchName = function () use (&$usedNames, $bFirst, $bLast, $bMiddle): array {
    $gender = mt_rand(0, 1) ? 'Male' : 'Female';
    do {
        $first = $bFirst[$gender][mt_rand(0, count($bFirst[$gender]) - 1)];
        $last = $bLast[mt_rand(0, count($bLast) - 1)];
    } while (isset($usedNames["$first $last"]));
    $usedNames["$first $last"] = true;
    return [$gender, $first, $last, $bMiddle[mt_rand(0, count($bMiddle) - 1)]];
};
// group: 'A' (1st Semester grades), 'B' (both semesters), 'C' (CHED applicant, no grades)
$batchStudent = function (string $group, int $seq) use (&$batchName, $programKeys, $curriculum, $insertStudent, $insertGrade, $insertSubject, $scholarshipPrograms, $chedPrograms, $pickBy, $towns, $barangays, $gradeFor, $bProfiles): array {
    [$gender, $first, $last, $middle] = $batchName();
    $programKey = $programKeys[mt_rand(0, count($programKeys) - 1)];
    [$program, $major] = array_pad(explode('|', $programKey, 2), 2, '');
    $yearNum = mt_rand(1, 4);
    $yearLevel = ['', '1st Year', '2nd Year', '3rd Year', '4th Year'][$yearNum];
    $studentId = (2027 - $yearNum) . ['A' => '1', 'B' => '2', 'C' => '3'][$group] . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
    $birthdate = sprintf('%04d-%02d-%02d', 2026 - (17 + $yearNum) - mt_rand(0, 1), mt_rand(1, 12), mt_rand(1, 28));
    $email = strtolower(preg_replace('/[^a-z]/i', '', $first) . '.' . preg_replace('/[^a-z]/i', '', $last)) . '.' . strtolower($group) . $seq . '@example.com'; // test addresses only
    $phone = '09' . str_pad((string)(crc32($studentId . 'p') % 1000000000), 9, '0', STR_PAD_LEFT);

    // Scholarship program (and what that program asks for).
    $qualification = '';
    $income = null;
    if ($group === 'C') {
        $scholarship = $chedPrograms[mt_rand(0, count($chedPrograms) - 1)];
    } else {
        $sp = $scholarshipPrograms[mt_rand(0, count($scholarshipPrograms) - 1)];
        $scholarship = $sp['name'];
        if ($sp['qualification']) {
            $options = array_values(array_filter($sp['qualification']['options'], fn($o) => $o !== SPECIAL_QUALIFICATION_OTHER));
            $qualification = $options[mt_rand(0, count($options) - 1)];
        }
        if ($sp['needs_income']) $income = (float)(mt_rand(6, 20) * 1000);
    }

    $insertStudent->execute([$studentId, $last, $first, $middle, $gender, $birthdate, $email, $phone, $pickBy($studentId . 't', $towns), $pickBy($studentId . 'b', $barangays), $scholarship, $qualification, $income, $program, $major, $yearLevel, 'Enrolled', SEED_SCHOOL_YEAR]);

    // Enrolled subjects of this year level; grades per group.
    $sems = $group === 'C' ? ['1st Semester'] : ['1st Semester', '2nd Semester'];
    $gradedSems = $group === 'A' ? ['1st Semester'] : ($group === 'B' ? ['1st Semester', '2nd Semester'] : []);
    $profile = $bProfiles[mt_rand(0, count($bProfiles) - 1)];
    $subjects = 0;
    $grades = 0;
    foreach ($sems as $sem) {
        foreach ($curriculum[$programKey][$yearLevel][$sem] ?? [] as $subject) {
            $insertSubject->execute([$studentId, $subject['code'], $subject['name'], $sem, SEED_SCHOOL_YEAR]);
            $subjects++;
            if (in_array($sem, $gradedSems, true)) {
                $insertGrade->execute([$studentId, $subject['code'], $subject['name'], $sem, SEED_SCHOOL_YEAR, $gradeFor($profile)]);
                $grades++;
            }
        }
    }
    return ['id' => $studentId, 'last' => $last, 'first' => $first, 'middle' => $middle, 'gender' => $gender, 'birthdate' => $birthdate,
            'program' => $program . ($major !== '' ? " · $major" : ''), 'programOnly' => $program, 'yearLevel' => $yearLevel,
            'scholarship' => $scholarship, 'subjects' => $subjects, 'grades' => $grades];
};

$batch = ['A' => [], 'B' => [], 'C' => []];
$db->beginTransaction();
foreach (['A' => BATCH_A_COUNT, 'B' => BATCH_B_COUNT, 'C' => BATCH_C_COUNT] as $group => $count) {
    for ($k = 1; $k <= $count; $k++) $batch[$group][] = $batchStudent($group, $k);
}
$db->commit();

// Group C: some registered students are NOT officially enrolled this Academic Year.
$cOrder = range(0, BATCH_C_COUNT - 1);
shuffle($cOrder);
$notEnrolled = [];
$setEnroll = $db->prepare('UPDATE ' . REGISTRAR_STUDENTS_TABLE . ' SET enrollment_status = ?, school_year = ? WHERE student_id = ?');
foreach (array_slice($cOrder, 0, BATCH_C_DROPPED) as $idx) { $setEnroll->execute(['Dropped', SEED_SCHOOL_YEAR, $batch['C'][$idx]['id']]); $notEnrolled[$batch['C'][$idx]['id']] = 'Dropped'; }
foreach (array_slice($cOrder, BATCH_C_DROPPED, BATCH_C_LAST_YEAR) as $idx) { $setEnroll->execute(['Enrolled', '2025-2026', $batch['C'][$idx]['id']]); $notEnrolled[$batch['C'][$idx]['id']] = 'Last enrolled 2025-2026'; }

// Excel for Groups A and B — ready for "Fetch from Registrar Database" (Student ID + names first).
foreach (['A' => 'batch_A_135_first_sem_grades.xlsx', 'B' => 'batch_B_115_full_year_grades.xlsx'] as $group => $file) {
    $rowsOut = [['Student ID', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Birthdate', 'Program', 'Year Level', 'Scholarship Program']];
    foreach ($batch[$group] as $s) $rowsOut[] = [$s['id'], $s['last'], $s['first'], $s['middle'], $s['gender'], $s['birthdate'], $s['program'], $s['yearLevel'], $s['scholarship']];
    writeXlsx($excelDir . '/' . $file, $rowsOut, $group === 'A' ? '1st Sem Grades' : 'Full Year Grades', [12, 16, 16, 14, 8, 12, 44, 10, 44]);
}

// Group C: a CHED-style list (title rows, no Student IDs, capitals, middle initial) of the 150
// registered applicants plus 15 unregistered ones, mixed together.
$chedShort = fn(string $name) => strtoupper((string)strtok($name, ' '));   // "CMSP (...)" -> "CMSP"
$chedRows = [];
foreach ($batch['C'] as $s) {
    $chedRows[] = [mb_strtoupper($s['last']), mb_strtoupper($s['first']), mb_strtoupper(mb_substr($s['middle'], 0, 1)) . '.', mb_strtoupper(mb_substr($s['gender'], 0, 1)), date('m/d/Y', strtotime($s['birthdate'])), mb_strtoupper($s['programOnly']), $s['yearLevel'], $chedShort($s['scholarship'])];
}
for ($u = 1; $u <= BATCH_C_UNREGISTERED; $u++) {
    [$gender, $first, $last, $middle] = $batchName();   // unique name, never inserted into the database
    $yl = mt_rand(1, 4);
    $uProgram = explode('|', $programKeys[mt_rand(0, count($programKeys) - 1)])[0];
    $chedRows[] = [mb_strtoupper($last), mb_strtoupper($first), mb_strtoupper(mb_substr($middle, 0, 1)) . '.', $gender === 'Male' ? 'M' : 'F', sprintf('%02d/%02d/%04d', mt_rand(1, 12), mt_rand(1, 28), 2026 - (17 + $yl)), mb_strtoupper($uProgram), ['', '1st Year', '2nd Year', '3rd Year', '4th Year'][$yl], $chedShort($chedPrograms[mt_rand(0, count($chedPrograms) - 1)])];
}
shuffle($chedRows);
$chedList = [
    ['COMMISSION ON HIGHER EDUCATION'],
    ['CHED Scholarship Programs (CMSP / COSCHO) — List of Applicants for Enrollment Validation'],
    ['Higher Education Institution: College of Maasin', '', '', '', 'Academic Year: ' . SEED_SCHOOL_YEAR],
    [],
    ['SEQ', 'LAST NAME', 'FIRST NAME', 'MIDDLE NAME', 'SEX', 'BIRTHDATE', 'PROGRAM', 'YEAR LEVEL', 'SCHOLARSHIP'],
];
foreach ($chedRows as $i => $r) $chedList[] = array_merge([(string)($i + 1)], $r);
writeXlsx($excelDir . '/batch_C_ched_verification.xlsx', $chedList, 'CHED Applicants', [6, 16, 16, 13, 6, 12, 36, 11, 13]);

$countGrades = fn($g) => array_sum(array_column($batch[$g], 'grades'));
echo "\nBatch of 400: A " . count($batch['A']) . " (1st Sem grades: " . $countGrades('A') . "), B " . count($batch['B']) . " (full-year grades: " . $countGrades('B') . "), C " . count($batch['C']) . " CHED applicants (" . count($notEnrolled) . " not officially enrolled) + " . BATCH_C_UNREGISTERED . " unregistered on the CHED list\n";
echo "Excel: test-data/batch_A_135_first_sem_grades.xlsx, test-data/batch_B_115_full_year_grades.xlsx, test-data/batch_C_ched_verification.xlsx\n";

// The 184 Dean's List test students (no scholarship program, 1st Semester grades) — see that file.
define('RUN_DEAN_CANDIDATES', true);
require __DIR__ . '/registrar_dean_candidates.php';

// Per-semester enrollment, incl. the 2nd Semester 2026-2027 enrollees — see that file. Runs last.
define('RUN_SEMESTER_ENROLLMENT', true);
require __DIR__ . '/registrar_semester_enrollment.php';

// The 89 students who already hold a scholarship (student_scholarships) — see that file. After the enrollments.
define('RUN_REGISTRAR_SCHOLARS', true);
require __DIR__ . '/registrar_scholars.php';
