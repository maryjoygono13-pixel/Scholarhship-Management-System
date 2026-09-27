<?php
/*
 * The Active Semester / Academic Year (Settings > Portal Configuration) is the
 * single source of truth for which term the system is working in. Applicants,
 * Records and Renewal & Retention never pick a semester by hand; they read it
 * from here.
 *
 * Changing the active term rolls the cycle forward:
 *   - the Renewal & Retention rows of the term that just ended unlock, so they can be renewed
 *     or terminated (they are view-only while their own term is still the active one);
 *     an approved record that somehow has no row yet is added as pending;
 *   - every scholar approved in that term goes back to Evaluation under the new term, so
 *     their new semester's GWA can be checked (their old-term record stays in Records).
 * Their earlier grades are kept (student_grades is keyed by semester).
 */

require_once __DIR__ . '/settings_helper.php';
require_once __DIR__ . '/gwa_helper.php';
require_once __DIR__ . '/activity_logger.php';
require_once __DIR__ . '/grades_helper.php';
require_once __DIR__ . '/renewal_helper.php';

const TERM_SEMESTERS = ['1st Semester', '2nd Semester', 'Summer Term'];

/*
 * The academic year of today's date: a school year starts in June, so June 2026 to May 2027 is
 * "2026-2027". Used as the default until a different Active Academic Year is saved in Settings.
 */
function currentSchoolYear(?DateTimeInterface $today = null): string {
    $today = $today ?? new DateTimeImmutable('now');
    $year = (int)$today->format('Y');
    $start = (int)$today->format('n') >= 6 ? $year : $year - 1;
    return $start . '-' . ($start + 1);
}

// Maps any stored spelling ("First Semester", "2nd", "summer"...) onto one of TERM_SEMESTERS.
function normalizeSemesterName(?string $raw): string {
    $v = strtolower(trim((string)$raw));
    if (strpos($v, 'summer') !== false) return 'Summer Term';
    if (strpos($v, '2') !== false || strpos($v, 'second') !== false) return '2nd Semester';
    return '1st Semester';
}

function getActiveSemester(PDO $pdo): string {
    return normalizeSemesterName(getSetting($pdo, 'active_semester', '1st Semester'));
}

function getActiveSchoolYear(PDO $pdo): string {
    $sy = trim(getSetting($pdo, 'active_school_year', currentSchoolYear()));
    return $sy !== '' ? $sy : currentSchoolYear();
}

// "2025-2026" -> "2026-2027"
function nextSchoolYear(string $sy): string {
    if (preg_match('/^\s*(\d{4})\s*-\s*(\d{4})\s*$/', $sy, $m)) {
        return ((int)$m[1] + 1) . '-' . ((int)$m[2] + 1);
    }
    return $sy;
}

// "2026-2027" -> "2025-2026"
function previousSchoolYear(string $sy): string {
    if (preg_match('/^\s*(\d{4})\s*-\s*(\d{4})\s*$/', $sy, $m)) {
        return ((int)$m[1] - 1) . '-' . ((int)$m[2] - 1);
    }
    return $sy;
}

/*
 * The term right after the given one — 1st Semester -> 2nd Semester -> Summer Term -> the
 * next school year's 1st Semester. Used to show scholars WHEN a renew/terminate decision
 * on their term actually takes effect (the next term), not the term they were evaluated in.
 * Returns [semester, schoolYear].
 */
function nextTerm(string $semester, string $schoolYear): array {
    $semester = normalizeSemesterName($semester);
    $schoolYear = trim($schoolYear);
    $idx = array_search($semester, TERM_SEMESTERS, true);
    if ($idx === false) $idx = 0;
    if ($idx === count(TERM_SEMESTERS) - 1) {
        return [TERM_SEMESTERS[0], nextSchoolYear($schoolYear)];
    }
    return [TERM_SEMESTERS[$idx + 1], $schoolYear];
}

/*
 * Switches the active term and rolls scholars forward. Returns:
 *   ['changed' => bool, 'moved' => int, 'semester' => string, 'schoolYear' => string]
 * `moved` is how many scholars were sent to Renewal & Retention / back to Evaluation.
 */
function changeActiveTerm(PDO $pdo, string $newSemester, string $newSchoolYear): array {
    $newSemester = normalizeSemesterName($newSemester);
    $newSchoolYear = trim($newSchoolYear) !== '' ? trim($newSchoolYear) : getActiveSchoolYear($pdo);

    $oldSemester = getActiveSemester($pdo);
    $oldSchoolYear = getActiveSchoolYear($pdo);

    // Summer -> 1st Semester starts the next academic year.
    if ($oldSemester === 'Summer Term' && $newSemester === '1st Semester' && $newSchoolYear === $oldSchoolYear) {
        $newSchoolYear = nextSchoolYear($oldSchoolYear);
    }

    if ($newSemester === $oldSemester && $newSchoolYear === $oldSchoolYear) {
        return ['changed' => false, 'moved' => 0, 'semester' => $oldSemester, 'schoolYear' => $oldSchoolYear];
    }

    $roll = ['moved' => 0, 'skipped' => 0];
    $pdo->beginTransaction();
    try {
        setSetting($pdo, 'active_semester', $newSemester);
        setSetting($pdo, 'active_school_year', $newSchoolYear);
        $roll = rollScholarsForward($pdo, $oldSemester, $newSemester, $newSchoolYear);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    logActivity(
        $pdo,
        'Semester Changed',
        'Settings',
        'Active term changed from ' . $oldSemester . ' ' . $oldSchoolYear . ' to ' . $newSemester . ' ' . $newSchoolYear . '. ' . $roll['moved'] . ' scholar(s) sent to Renewal & Retention and back to Evaluation'
        . ($roll['skipped'] > 0 ? '; ' . $roll['skipped'] . ' already had a record for that semester and were left as they are.' : '.')
    );

    return ['changed' => true, 'moved' => $roll['moved'], 'skipped' => $roll['skipped'], 'semester' => $newSemester, 'schoolYear' => $newSchoolYear];
}

/*
 * Sends every scholar approved in `$oldSemester` to Renewal & Retention and
 * back to Evaluation under the new term. Scholars who already have a record for the
 * new term are left alone (they were evaluated for it before). Returns
 * ['moved' => scholars sent on, 'skipped' => scholars left alone].
 * The school year of the old record is NOT compared: a record's semester is
 * what decides whether its term has ended (applicants may carry a different
 * school year than the active one). Call inside a transaction.
 */
function rollScholarsForward(PDO $pdo, string $oldSemester, string $newSemester, string $newSchoolYear): array {
    $moved = 0;
    $skipped = 0;
    $records = $pdo->query("SELECT * FROM records WHERE LOWER(TRIM(status)) = 'approved' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    $seen = [];
    foreach ($records as $rec) {
        // Only scholars approved in the term that is ending.
        if (normalizeSemesterName($rec['semester']) !== $oldSemester) continue;

        // One entry per student AND scholarship type, so a second scholarship is not skipped.
        $studentKey = (trim((string)$rec['student_id']) !== '' ? 's:' . trim($rec['student_id']) : 'r:' . $rec['id']) . '|' . strtolower(trim((string)$rec['scholarship_type']));
        if (isset($seen[$studentKey])) continue;
        $seen[$studentKey] = true;

        // Already evaluated for the term we are switching to (e.g. going back to 1st/2nd
        // Semester, which they have a record for): don't send them through again.
        if (hasRecordForTerm($pdo, (string)$rec['student_id'], (string)$rec['scholarship_type'], $newSemester, $newSchoolYear, (int)$rec['id'])) {
            $skipped++;
            continue;
        }

        // Renewal & Retention (once per scholar per term) is the ONLY place a scholar's
        // ongoing eligibility is tracked from here on — they never go back through Evaluation
        // once they have a Record; Evaluation is for a first-time application only. Renewal &
        // Retention already re-assesses them against each new term's GWA on its own (see
        // isRenewalActionable / renewalPreviousTerm in includes/renewal_helper.php).
        $recordSy = trim((string)$rec['sy']) !== '' ? trim($rec['sy']) : $newSchoolYear;
        sendRecordToRenewal($pdo, $rec, 'pending', 'Sent from Records at the end of ' . $oldSemester . ' ' . $recordSy . '. Renew or terminate by GWA.');
        $moved++;
    }
    return ['moved' => $moved, 'skipped' => $skipped];
}
