<?php
/*
 * A scholar's standing (Active / Removed), worked out live from their imported grades.
 * Shared by the Scholars page (api/list_scholars.php) and the Dashboard's "Active Scholars"
 * card, so both always agree.
 */

require_once __DIR__ . '/grades_helper.php';
require_once __DIR__ . '/gwa_helper.php';
require_once __DIR__ . '/merit_helper.php';
require_once __DIR__ . '/deans_list_rules.php';

// Used only for scholars whose scholarship can't be found (e.g. entered by hand).
const SCHOLAR_DEFAULT_GWA_REQUIREMENT = 1.50;

/*
 * Each scholar row with its standing filled in: gwa (newest graded semester), gwa_first /
 * gwa_second / gwa_summer, gwaSemester, scholarshipType, meritTier, maintainsGrade,
 * thresholdRequirement, deansLister and displayStatus ('Active' / 'Removed').
 */
function computeScholarStandings(PDO $pdo, array $scholars): array {
    // GWA comes from the imported academic records, kept separately for each semester.
    $gradeStats = getSemesterGradeStats($pdo, array_column($scholars, 'student_id'));

    // The scholarship a scholar holds decides the GWA they must keep: the latest
    // evaluation record first, then their application.
    $recordType = $pdo->prepare("SELECT scholarship_type FROM records WHERE student_id = ? AND LOWER(TRIM(status)) = 'approved' ORDER BY id DESC LIMIT 1");
    $applicantType = $pdo->prepare("SELECT scholarship_type FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");

    // Newest graded semester first: that is the standing the scholar is judged on.
    $semesterOrder = ['Summer Term', '2nd Semester', '1st Semester'];
    $meritRanges = getMeritRanges($pdo);   // the Full / Half Merit GWA ranges set on the Merit program
    $activeSemester = getActiveSemester($pdo);

    $out = [];
    foreach ($scholars as $s) {
        $sid = (string)$s['student_id'];
        $stats = $gradeStats[$sid] ?? [];

        $type = trim((string)($s['scholarship_type'] ?? ''));   // set for scholars added by the Merit scholarship
        if ($type === '') {
            $recordType->execute([$sid]);
            $type = trim((string)$recordType->fetchColumn());
        }
        if ($type === '') {
            $applicantType->execute([$sid]);
            $type = trim((string)$applicantType->fetchColumn());
        }
        $isMerit = isMeritScholarshipType($type);
        $isDeansList = isDeansListType($type);   // Dean's List: recognition by grades, not a scholarship
        // MERIT-BASED is always judged by the Full / Half Merit ranges set on the Merit program,
        // never by whatever GWA happens to be stored on the program.
        if ($isDeansList) {
            $required = DEANS_LIST_GWA_LIMIT;
        } elseif ($isMerit) {
            $required = (float)$meritRanges['half_max'];
        } else {
            $required = $type !== '' ? resolveGwaRequirement($pdo, $type) : SCHOLAR_DEFAULT_GWA_REQUIREMENT;
        }

        $standingSemester = null;
        foreach ($semesterOrder as $sem) {
            if (isset($stats[$sem])) { $standingSemester = $sem; break; }
        }
        // No imported grades yet: fall back to the GWA stored on the scholar.
        $gwa = $standingSemester ? $stats[$standingSemester]['gwa'] : (float)$s['gwa'];
        $maintains = $isMerit
            ? meritTierForGwa((float)$gwa, $meritRanges) !== null   // inside Full or Half Merit range
            : gwaMeetsRequirement((float)$gwa, (float)$required);
        // Dean's List: judged on the active semester's grades (or the latest graded one before it).
        $dlSemester = deansListBasisSemester($stats, $activeSemester);
        if ($isDeansList) {
            $maintains = $dlSemester !== null && deansListQualifies($stats[$dlSemester]);
            if (!$maintains) $s['remarks'] = 'Removed: ' . ($dlSemester !== null ? deansListMissReason($stats[$dlSemester]) . ' in ' . $dlSemester : 'no grades recorded') . '.';
        }

        // MERIT-BASED Academic: 2.00 or worse in any semester of this school year removes the
        // scholar until the next school year, even if a later semester is back within range.
        $lockout = $isMerit ? meritLockout($pdo, $sid, getActiveSchoolYear($pdo)) : null;
        if ($lockout) {
            $maintains = false;
            $s['remarks'] = 'Removed: GWA ' . number_format($lockout['gwa'], 2) . ' in ' . $lockout['semester'] . ' is ' . number_format(MERIT_LOCKOUT_GWA, 2) . ' or worse. Not a scholar again until the next school year.';
        }

        $s['gwa'] = $gwa;
        $s['gwa_first'] = $stats['1st Semester']['gwa'] ?? null;
        $s['gwa_second'] = $stats['2nd Semester']['gwa'] ?? null;
        $s['gwa_summer'] = $stats['Summer Term']['gwa'] ?? null;
        $s['gwaSemester'] = $standingSemester;          // which semester `gwa` is for (null = stored figure)
        $s['scholarshipType'] = $type;
        // Full Merit / Half Merit for a Merit scholar still within the ranges (null otherwise).
        $s['meritTier'] = ($isMerit && $maintains) ? meritTierForGwa((float)$gwa, $meritRanges) : null;
        $s['maintainsGrade'] = $maintains;
        // Dean's Lister (whatever scholarship they hold): newest graded semester meets the Dean's List rule.
        $s['deansLister'] = $dlSemester !== null && deansListQualifies($stats[$dlSemester]);
        $s['thresholdRequirement'] = $required;
        // Standing follows the grades: above their scholarship's requirement = Removed.
        $s['displayStatus'] = $maintains ? 'Active' : 'Removed';
        $out[] = $s;
    }
    return $out;
}

// How many scholars the Scholars page lists as Active right now (after the same automatic syncs).
function countActiveScholars(PDO $pdo): int {
    syncApprovedScholars($pdo);
    $scholars = $pdo->query("SELECT * FROM scholars")->fetchAll(PDO::FETCH_ASSOC);
    return count(array_filter(computeScholarStandings($pdo, $scholars), fn($s) => $s['maintainsGrade']));
}
