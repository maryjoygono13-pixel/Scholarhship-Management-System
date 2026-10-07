<?php
/*
 * Dean's List — the school's academic recognition, separate from every scholarship.
 *
 * Nobody applies for it: a student is a Dean's Lister purely from their own grades. It is NOT the
 * MERIT-BASED Academic Scholarship (that one is applied for and evaluated like any other program,
 * see merit_helper.php), and it has no slots, no Renewal & Retention and no Merit tiers.
 *
 * It's awarded per semester, always for the ACTIVE semester set in Settings > Portal Configuration and
 * on that semester's grades only: with 2nd Semester set, only a 2nd Semester GWA counts, and a student
 * with no 2nd Semester grades yet isn't a Dean's Lister for it (nor listed as one on the Scholars page).
 *
 * Rule:
 *   - GWA of DEANS_LIST_GWA_LIMIT (1.50) or better, and
 *   - no subject grade of DEANS_LIST_GRADE_LIMIT (2.00) or worse.
 * (1.00 is the highest grade on this school's scale, 5.00 a failing one.)
 *
 * Kept free of other includes so the Scholars, Records, Evaluation and Dashboard code can use it.
 */

// The label a Dean's Lister carries on the Scholars list and in Records (not a scholarship program).
const DEANS_LIST_TYPE = "Dean's List";

const DEANS_LIST_GWA_LIMIT = 1.50;
const DEANS_LIST_GRADE_LIMIT = 2.00;

// Whether a stored scholarship_type is the Dean's List label.
function isDeansListType(string $type): bool {
    return strcasecmp(trim($type), DEANS_LIST_TYPE) === 0;
}

/*
 * Whether one semester's grades make a Dean's Lister.
 * $semStat: ['gwa' => float, 'worst' => float] (see getSemesterGradeStats(); worst = the lowest grade).
 */
function deansListQualifies(array $semStat): bool {
    $gwa = (float)($semStat['gwa'] ?? 0);
    $worst = (float)($semStat['worst'] ?? 0);
    return $gwa > 0 && $gwa <= DEANS_LIST_GWA_LIMIT && $worst > 0 && $worst < DEANS_LIST_GRADE_LIMIT;
}

// Why a semester misses the Dean's List, or null when it makes it.
function deansListMissReason(array $semStat): ?string {
    $gwa = (float)($semStat['gwa'] ?? 0);
    $worst = (float)($semStat['worst'] ?? 0);
    $reasons = [];
    if ($gwa <= 0) return 'no grades recorded';
    if ($gwa > DEANS_LIST_GWA_LIMIT) $reasons[] = 'GWA ' . number_format($gwa, 2) . ' is above ' . number_format(DEANS_LIST_GWA_LIMIT, 2);
    if ($worst >= DEANS_LIST_GRADE_LIMIT) $reasons[] = 'a subject grade of ' . number_format($worst, 2) . ' (' . number_format(DEANS_LIST_GRADE_LIMIT, 2) . ' or worse)';
    return $reasons ? implode(' and ', $reasons) : null;
}

/*
 * The semester a student's Dean's List standing is read from: the active semester — or null when they
 * have no grades for it yet (then they aren't a Dean's Lister for this semester).
 * $bySem: [semesterName => stats] (getSemesterGradeStats()).
 */
function deansListBasisSemester(array $bySem, string $activeSemester): ?string {
    return isset($bySem[$activeSemester]) ? $activeSemester : null;
}

// Order of the semesters within a school year (to tell whether a semester comes after another).
function deansListSemesterRank(string $semester): int {
    return ['1st Semester' => 1, '2nd Semester' => 2, 'Summer Term' => 3][$semester] ?? 0;
}
