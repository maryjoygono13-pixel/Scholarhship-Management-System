<?php
/*
 * GWA now comes only from the imported academic records (student_grades), and
 * is kept separately for each semester:
 *
 *   - getSemesterGradeStats() gives every semester's own average / failing
 *     count / grade count, so Evaluation can show 1st and 2nd semester side by side.
 *   - applicants.gwa / failing_grades hold the figures for the applicant's
 *     CURRENT semester. That is the number the approval and renewal checks use.
 */

require_once __DIR__ . '/term_helper.php';
require_once __DIR__ . '/curriculum_helper.php';

// A grade above 3.00 is a failing grade on this school's scale (1.0 = highest, 5.0 = fail).
const FAILING_GRADE_ABOVE = 3.00;

/*
 * Returns [studentId => [semesterName => ['gwa' => float, 'failing' => int, 'count' => int]]],
 * where semesterName is one of TERM_SEMESTERS (legacy spellings are merged).
 */
function getSemesterGradeStats(PDO $pdo, array $studentIds): array {
    $studentIds = array_values(array_unique(array_filter(array_map('strval', $studentIds), fn($s) => $s !== '')));
    if (empty($studentIds)) return [];

    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $pdo->prepare("SELECT student_id, semester, grade FROM student_grades WHERE student_id IN ($placeholders)");
    $stmt->execute($studentIds);

    $acc = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sem = normalizeSemesterName($row['semester']);
        $sid = (string)$row['student_id'];
        $acc[$sid][$sem]['sum'] = ($acc[$sid][$sem]['sum'] ?? 0) + (float)$row['grade'];
        $acc[$sid][$sem]['count'] = ($acc[$sid][$sem]['count'] ?? 0) + 1;
        $acc[$sid][$sem]['failing'] = ($acc[$sid][$sem]['failing'] ?? 0) + ((float)$row['grade'] > FAILING_GRADE_ABOVE ? 1 : 0);
    }

    $stats = [];
    foreach ($acc as $sid => $bySem) {
        foreach ($bySem as $sem => $a) {
            $stats[$sid][$sem] = [
                'gwa' => round($a['sum'] / $a['count'], 2),
                'failing' => (int)$a['failing'],
                'count' => (int)$a['count'],
            ];
        }
    }
    return $stats;
}

/*
 * Refreshes applicants.gwa / failing_grades from the grades of the applicant's
 * current semester. With $zeroWhenNoGrades, an applicant with no grades in that
 * semester is reset to 0 (used when a term rolls over or an import is removed);
 * otherwise their existing figures are left alone.
 */
function recalculateApplicantGwa(PDO $pdo, string $studentId, bool $zeroWhenNoGrades = false): void {
    if (trim($studentId) === '') return;

    // Each grade counts toward the semester its subject belongs to in the curriculum.
    retagStudentGradesByCurriculum($pdo, $studentId);

    $stats = getSemesterGradeStats($pdo, [$studentId])[$studentId] ?? [];

    $rows = $pdo->prepare("SELECT id, semester FROM applicants WHERE student_id = ?");
    $rows->execute([$studentId]);
    $update = $pdo->prepare("UPDATE applicants SET gwa = ?, failing_grades = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");

    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $app) {
        $current = $stats[normalizeSemesterName($app['semester'])] ?? null;
        if ($current) {
            $update->execute([$current['gwa'], $current['failing'], $app['id']]);
        } elseif ($zeroWhenNoGrades) {
            $update->execute([0, 0, $app['id']]);
        }
    }
}
