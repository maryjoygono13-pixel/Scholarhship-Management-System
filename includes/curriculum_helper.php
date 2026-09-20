<?php
/*
 * Server-side view of the reference curriculum in assets/js/curriculum-data.js
 * (the same file the Evaluation and Records pages use), so the server can tell
 * which semester a subject belongs to. That is what decides which semester a
 * grade counts toward, instead of whatever semester the student happened to be
 * in when their grades were imported.
 */

require_once __DIR__ . '/term_helper.php';

// Returns the CURRICULUM_DATA table: [programKey => [yearLevel => ['1st Semester' => [['code','name'],...], '2nd Semester' => ...]]].
function loadCurriculumData(): array {
    static $data = null;
    if ($data !== null) return $data;
    $data = [];

    $file = __DIR__ . '/../assets/js/curriculum-data.js';
    $js = is_file($file) ? file_get_contents($file) : false;
    if ($js === false) return $data;

    if (!preg_match('/const CURRICULUM_DATA(?:\s*:\s*[^=]+)?\s*=\s*(\{.*?\n\});/s', $js, $m)) return $data;
    $literal = $m[1];

    // The table is a plain object literal; turn it into JSON.
    $literal = preg_replace('#/\*.*?\*/#s', '', $literal);            // block comments
    $literal = preg_replace('#^\s*//.*$#m', '', $literal);            // full-line comments
    $literal = preg_replace('#,\s*//[^\n"]*$#m', ',', $literal);      // trailing line comments
    $literal = preg_replace('/\b(code|name)\s*:/', '"$1":', $literal); // quote the keys
    $literal = preg_replace('/,(\s*[\]}])/', '$1', $literal);         // trailing commas

    $decoded = json_decode($literal, true);
    if (is_array($decoded)) $data = $decoded;
    return $data;
}

/*
 * The semester ("1st Semester" / "2nd Semester") a subject belongs to in the
 * curriculum of the student's program, or null when it can't be told (program
 * not in the reference data, or the code isn't listed). The student's own year
 * level is searched first, then the other years.
 */
function curriculumSemesterForSubject(string $program, string $major, string $yearLevel, string $subjectCode): ?string {
    $program = trim($program);
    $code = strtoupper(preg_replace('/\s+/', '', $subjectCode));
    if ($program === '' || $code === '') return null;

    // Programs are stored as "<program>" or "<program> · <year level>".
    $yl = trim($yearLevel);
    if ($yl !== '' && substr($program, -strlen('· ' . $yl)) === '· ' . $yl) {
        $program = trim(substr($program, 0, -strlen('· ' . $yl)));
    }

    $data = loadCurriculumData();
    $major = trim($major);
    $table = ($major !== '' && isset($data[$program . '|' . $major])) ? $data[$program . '|' . $major] : ($data[$program] ?? null);
    if (!$table) return null;

    $years = array_keys($table);
    if ($yl !== '' && isset($table[$yl])) {
        $years = array_merge([$yl], array_diff($years, [$yl]));
    }
    foreach ($years as $year) {
        foreach (['1st Semester', '2nd Semester'] as $sem) {
            foreach ($table[$year][$sem] ?? [] as $subject) {
                if (strtoupper(preg_replace('/\s+/', '', (string)($subject['code'] ?? ''))) === $code) {
                    return $sem;
                }
            }
        }
    }
    return null;
}

/*
 * Files every grade of this student under the semester its subject belongs to
 * in the curriculum. Grades whose subject isn't in the curriculum are left as
 * they are. Returns how many grades changed semester.
 */
function retagStudentGradesByCurriculum(PDO $pdo, string $studentId): int {
    $appStmt = $pdo->prepare("SELECT program, major, year_level FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $appStmt->execute([$studentId]);
    $app = $appStmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) return 0;

    $grades = $pdo->prepare("SELECT id, subject_code, semester FROM student_grades WHERE student_id = ?");
    $grades->execute([$studentId]);
    // OR IGNORE: the unique key (student, subject, semester) must never be violated.
    $move = $pdo->prepare("UPDATE OR IGNORE student_grades SET semester = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");

    $changed = 0;
    foreach ($grades->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $sem = curriculumSemesterForSubject((string)$app['program'], (string)$app['major'], (string)$app['year_level'], (string)$g['subject_code']);
        if ($sem !== null && $sem !== normalizeSemesterName($g['semester'])) {
            $move->execute([$sem, $g['id']]);
            $changed += $move->rowCount();
        }
    }
    return $changed;
}
