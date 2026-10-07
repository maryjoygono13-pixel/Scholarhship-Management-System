<?php
/*
 * Evaluation -> Records -> Renewal & Retention flow:
 *
 *   1. Evaluation approves or rejects an applicant. Their Record gets that same status
 *      (approved / rejected), and either way they are added to Renewal & Retention as PENDING
 *      (sendRecordToRenewal), remembering how they entered (`origin`).
 *   2. While the Active Semester is still the row's own term, the row is LOCKED: it can be
 *      viewed but not changed (isRenewalLocked). Once the Active Semester moves on (Settings >
 *      Portal Configuration), it unlocks, and the approved scholars go back to Evaluation for
 *      the new term (see rollScholarsForward in term_helper.php).
 *   3. An unlocked row can be decided:
 *        - "Renew" (approved scholars whose GWA meets their scholarship's requirement);
 *        - "Terminate" (those whose GWA does not meet it, or who were rejected).
 *      Terminating a scholar who held the scholarship blocks them from applying for that same
 *      scholarship again (scholarship_terminations); they can still apply for a different one.
 *      MERIT-BASED Academic is never blocked. A rejected applicant's entry is only closed.
 */

require_once __DIR__ . '/term_helper.php';
require_once __DIR__ . '/grades_helper.php';
require_once __DIR__ . '/gwa_helper.php';
require_once __DIR__ . '/scholarship_type_helper.php';
require_once __DIR__ . '/merit_helper.php';
require_once __DIR__ . '/deans_list_rules.php';
require_once __DIR__ . '/records_helper.php';

/*
 * Adds the record's scholar to Renewal & Retention for the record's term, unless
 * they are already there. $status null = work it out from the GWA (used for records
 * that were already approved); 'pending' = awaiting the renewal decision.
 * Returns the new renewal row id, or null when one already existed.
 */
function sendRecordToRenewal(PDO $pdo, array $rec, ?string $status = 'pending', ?string $remarks = null, string $origin = 'approved'): ?int {
    $sid = trim((string)$rec['student_id']);
    $semester = normalizeSemesterName($rec['semester'] ?? '');
    $sy = trim((string)($rec['sy'] ?? '')) !== '' ? trim($rec['sy']) : getActiveSchoolYear($pdo);

    // A scholarship configured with "requires_renewal = No" never enters Renewal & Retention
    // at all — once approved, it stays approved with no further per-term reassessment.
    if (function_exists('resolveScholarshipIdForType') && function_exists('getScholarshipRenewalRules')) {
        $scholarshipId = resolveScholarshipIdForType($pdo, (string)$rec['scholarship_type']);
        if ($scholarshipId) {
            $rules = getScholarshipRenewalRules($pdo, $scholarshipId);
            if ($rules && !$rules['requires_renewal']) return null;
        }
    }

    // One row per scholarship per term: a student holding two scholarship types has two rows.
    $exists = $pdo->prepare("SELECT id FROM renewal_retention WHERE student_id = ? AND school_year = ? AND semester = ? AND LOWER(TRIM(scholarship_type)) = LOWER(TRIM(?))");
    $exists->execute([$sid, $sy, $semester, (string)$rec['scholarship_type']]);
    if ($exists->fetchColumn()) return null;

    // A registrar explicitly deleted this exact entry before — respect that instead of
    // silently recreating it the next time records/term-rollover runs (see delete_renewal.php).
    if (wasRenewalDeleted($pdo, $sid, $sy, $semester, (string)$rec['scholarship_type'])) return null;

    $applicant = null;
    if ((int)($rec['applicant_id'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT gwa, gwa_req, failing_grades, enrolled FROM applicants WHERE id = ?");
        $stmt->execute([(int)$rec['applicant_id']]);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$applicant) {
        $stmt = $pdo->prepare("SELECT gwa, gwa_req, failing_grades, enrolled FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sid]);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // The record's own semester GWA (from its imported grades); the applicant's stored
    // figure is only a fallback for scholars with no grades on file.
    $termStats = getSemesterGradeStats($pdo, [$sid])[$sid][$semester] ?? null;
    $gwa = $termStats ? $termStats['gwa'] : ($applicant ? (float)$applicant['gwa'] : 0.0);
    $failing = $termStats ? $termStats['failing'] : ($applicant ? (int)$applicant['failing_grades'] : 0);

    if ($status === null) {
        $required = resolveGwaRequirement($pdo, (string)$rec['scholarship_type'], $applicant ? (float)$applicant['gwa_req'] : null);
        $status = ($required <= 0 || ($gwa > 0 && $gwa <= $required)) ? 'eligible' : 'at-risk';
    }

    $insert = $pdo->prepare("
        INSERT INTO renewal_retention (student_id, name, gwa, failing_grades, enrolled, status, school_year, semester, scholarship_type, remarks, origin)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([
        $sid,
        $rec['name'],
        $gwa,
        $failing,
        $applicant ? (int)$applicant['enrolled'] : 1,
        $status,
        $sy,
        $semester,
        $rec['scholarship_type'],
        $remarks ?? ($origin === 'rejected'
            ? 'Rejected in Evaluation. Locked until the next semester begins.'
            : 'Approved in Evaluation. Locked until the next semester begins, then renew or terminate by GWA.'),
        $origin === 'rejected' ? 'rejected' : 'approved',
    ]);
    return (int)$pdo->lastInsertId();
}

/*
 * Whether a registrar explicitly deleted the Renewal & Retention entry for this exact
 * student + term + scholarship (recorded in deleted_items by delete_renewal.php). Prevents
 * sendRecordToRenewal() from silently recreating something that was deliberately removed.
 */
function wasRenewalDeleted(PDO $pdo, string $studentId, string $schoolYear, string $semester, string $scholarshipType): bool {
    $stmt = $pdo->prepare("SELECT item_data FROM deleted_items WHERE item_type = 'renewal'");
    $stmt->execute();
    $sem = normalizeSemesterName($semester);
    $typeKey = strtolower(trim($scholarshipType));
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $d = json_decode((string)$json, true);
        if (!is_array($d)) continue;
        if (trim((string)($d['student_id'] ?? '')) !== trim($studentId)) continue;
        if (trim((string)($d['school_year'] ?? '')) !== trim($schoolYear)) continue;
        if (normalizeSemesterName((string)($d['semester'] ?? '')) !== $sem) continue;
        if (strtolower(trim((string)($d['scholarship_type'] ?? ''))) !== $typeKey) continue;
        return true;
    }
    return false;
}

/*
 * A renewal row's GWA and failing count, taken live from the imported grades of its
 * own semester (so grades imported after the row was created still count), falling
 * back to the figures stored on the row.
 */
function liveRenewalFigures(array $row, array $gradeStats, ?string $semester = null): array {
    $sem = normalizeSemesterName($semester ?? (string)$row['semester']);
    $st = $gradeStats[(string)$row['student_id']][$sem] ?? null;
    return [
        'gwa' => $st ? $st['gwa'] : (float)$row['gwa'],
        'failing' => $st ? $st['failing'] : (int)$row['failing_grades'],
    ];
}

/*
 * Renewal & Retention's own GWA-basis cycle — a strict 1st <-> 2nd Semester alternation
 * across the year boundary. Summer Term is not part of this cycle: a renewal decision's basis
 * is always the most recently completed REGULAR semester, and Summer terms are too sparsely
 * enrolled to be a reliable renewal basis on their own.
 */
function renewalPreviousTerm(string $semester, string $schoolYear): array {
    $schoolYear = trim($schoolYear);
    if (normalizeSemesterName($semester) === '1st Semester') {
        return ['2nd Semester', previousSchoolYear($schoolYear)];
    }
    return ['1st Semester', $schoolYear];
}

/*
 * Records the decision taken in Renewal & Retention as the scholar's record for the term it was made
 * in (the active term): renewed = approved, terminated = rejected. The earlier term's record stays as
 * it was, so Records keeps the full history (e.g. 1st Semester approved, 2nd Semester renewed). One
 * record per term: deciding again in the same term updates that record. Such a record doesn't start a
 * Renewal & Retention entry of its own — the original entry carries the scholar from year to year.
 * Returns the record status it was set to.
 */
function syncRecordWithRenewal(PDO $pdo, array $ren, string $action): ?string {
    $map = ['renew' => 'approved', 'terminate' => 'rejected', 'flag' => 'pending'];
    if (!isset($map[$action])) return null;
    $newStatus = $map[$action];
    $semester = getActiveSemester($pdo);
    $schoolYear = getActiveSchoolYear($pdo);

    // The original record (name / applicant link), matched on the entry's own term.
    $original = findTermRecord($pdo, (string)$ren['student_id'], (string)$ren['scholarship_type'], (string)$ren['school_year'], (string)$ren['semester']);
    saveTermRecord($pdo, [
        'applicant_id' => $original['applicant_id'] ?? null,
        'student_id' => (string)$ren['student_id'],
        'name' => (string)($original['name'] ?? $ren['name']),
        'scholarship_type' => (string)$ren['scholarship_type'],
        'status' => $newStatus,
        'semester' => $semester,
        'sy' => $schoolYear,
        'remarks' => ($action === 'renew' ? 'Renewed' : 'Terminated') . " in Renewal & Retention for $semester $schoolYear.",
        'origin' => 'renewal',
    ], true);
    return $newStatus;
}

/*
 * Whether this student already has a Record of this scholarship for the given term
 * (any status). A scholar who already has one is not sent back to Evaluation for that
 * term again; only a semester with no record yet (e.g. Summer) starts a new evaluation.
 */
function hasRecordForTerm(PDO $pdo, string $studentId, string $scholarshipType, string $semester, string $schoolYear, int $excludeRecordId = 0): bool {
    $stmt = $pdo->prepare("SELECT id, semester FROM records WHERE student_id = ? AND TRIM(sy) = ? AND LOWER(TRIM(scholarship_type)) = LOWER(TRIM(?)) AND id != ?");
    $stmt->execute([trim($studentId), trim($schoolYear), $scholarshipType, $excludeRecordId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (normalizeSemesterName($r['semester']) === normalizeSemesterName($semester)) return true;
    }
    return false;
}

/*
 * A pending row is locked (view only) while the Active Semester and School Year are still the
 * row's own term. When the term moves on, it unlocks and can be renewed or terminated.
 */
function isRenewalLocked(PDO $pdo, array $row): bool {
    return strtolower(trim((string)$row['status'])) === 'pending'
        && normalizeSemesterName($row['semester']) === getActiveSemester($pdo)
        && trim((string)$row['school_year']) === getActiveSchoolYear($pdo);
}

/*
 * Whether a Renewal & Retention row can be decided (Renew / Terminate) right now.
 *   - Still pending/at-risk: only once the Active Semester has moved past the row's own term
 *     (see isRenewalLocked).
 *   - Already decided (renewed/terminated): scholarships are re-assessed every academic year,
 *     not once and forever — the SAME row is locked for the rest of the school year the
 *     decision was made in, then opens back up for reassessment the moment a brand-new
 *     Academic Year begins in Settings > Portal Configuration (even if the Active Semester
 *     itself was only just moved on within the same year, it stays locked until the year
 *     actually rolls over).
 */
function isRenewalActionable(PDO $pdo, array $row): bool {
    $status = strtolower(trim((string)$row['status']));
    if (in_array($status, ['pending', 'at-risk'], true)) {
        return !isRenewalLocked($pdo, $row);
    }
    if (in_array($status, ['eligible', 'terminated'], true)) {
        // Compared against the school year the decision itself was made in (decided_school_year)
        // — NOT the row's origin school_year, which stays fixed to the original Record and would
        // otherwise make this look "reassessable" forever after the very first decision.
        // Older rows decided before this column existed fall back to the origin year.
        $decidedYear = trim((string)($row['decided_school_year'] ?? '')) !== ''
            ? trim((string)$row['decided_school_year'])
            : trim((string)$row['school_year']);
        return $decidedYear !== getActiveSchoolYear($pdo);
    }
    return false;
}

// Lower-cased acronym form of a scholarship type, so "CMSP", "cmsp" and the long name compare equal.
function scholarshipTypeKey(PDO $pdo, string $type): string {
    return strtolower(normalizeScholarshipType($pdo, $type));
}

/*
 * Blocks a scholar from applying for this scholarship again. MERIT-BASED Academic is exempt
 * (its standing follows the GWA every semester). Returns false when nothing was recorded.
 */
function recordScholarshipTermination(PDO $pdo, string $studentId, string $scholarshipType, ?int $renewalId, string $reason): bool {
    $sid = trim($studentId);
    $key = scholarshipTypeKey($pdo, $scholarshipType);
    if ($sid === '' || $key === '' || isMeritScholarshipType($key)) return false;
    if (findScholarshipTermination($pdo, $sid, $scholarshipType)) return true;

    $pdo->prepare("INSERT INTO scholarship_terminations (student_id, scholarship_type, renewal_id, reason) VALUES (?, ?, ?, ?)")
        ->execute([$sid, normalizeScholarshipType($pdo, $scholarshipType), $renewalId, $reason]);
    return true;
}

// The termination that bars this student from this scholarship, or null when they may apply.
function findScholarshipTermination(PDO $pdo, string $studentId, string $scholarshipType): ?array {
    $sid = trim($studentId);
    $key = scholarshipTypeKey($pdo, $scholarshipType);
    if ($sid === '' || $key === '' || isMeritScholarshipType($key)) return null;

    $stmt = $pdo->prepare("SELECT * FROM scholarship_terminations WHERE student_id = ?");
    $stmt->execute([$sid]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        if (scholarshipTypeKey($pdo, (string)$t['scholarship_type']) === $key) return $t;
    }
    return null;
}

function terminationBlockMessage(string $scholarshipType): string {
    return 'This student was terminated from the ' . $scholarshipType . ' scholarship and cannot apply for it again. They can still apply for a different scholarship.';
}

/*
 * Makes sure every record of the ACTIVE term has its Renewal & Retention row (pending, locked
 * for the rest of the term). Older approvals and all rejections made before rejected applicants
 * were sent to Renewal have none yet. Returns how many rows were added.
 */
function backfillCurrentTermRenewals(PDO $pdo): int {
    $semester = getActiveSemester($pdo);
    $stmt = $pdo->prepare("SELECT * FROM records WHERE LOWER(TRIM(status)) IN ('approved', 'rejected', 'pending') AND TRIM(sy) = ?");
    $stmt->execute([getActiveSchoolYear($pdo)]);

    $added = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rec) {
        if (normalizeSemesterName($rec['semester']) !== $semester) continue;
        // The Dean's List isn't a scholarship: it's judged on the Scholars list by grades, never renewed.
        if (isDeansListType((string)$rec['scholarship_type'])) continue;
        // A record made by a Renew / Terminate decision: the original entry already tracks this scholar.
        if (($rec['origin'] ?? '') === 'renewal') continue;
        $origin = strtolower(trim((string)$rec['status'])) === 'rejected' ? 'rejected' : 'approved';
        if (sendRecordToRenewal($pdo, $rec, 'pending', null, $origin) !== null) $added++;
    }
    return $added;
}

/*
 * Renewal & Retention ledger rules shared by its page (api/list_renewal.php) and the
 * Dashboard, so both always show the same numbers.
 */

// A row's link back to its Record: same student, school year, semester and scholarship type.
function renewalRecordKey(string $studentId, string $schoolYear, string $semester, string $scholarshipType): string {
    return trim($studentId) . '|' . trim($schoolYear) . '|' . normalizeSemesterName($semester) . '|' . strtolower(trim($scholarshipType));
}

// Every Record, keyed by renewalRecordKey() (later rows win on a duplicate key).
function renewalRecordIndex(PDO $pdo): array {
    $index = [];
    foreach ($pdo->query("SELECT student_id, sy, semester, scholarship_type, name FROM records ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) as $rec) {
        $index[renewalRecordKey((string)$rec['student_id'], (string)$rec['sy'], (string)$rec['semester'], (string)$rec['scholarship_type'])] = $rec;
    }
    return $index;
}

// The status the ledger shows: a decided entry (renewed/terminated) that has reopened for a new
// school year's reassessment reads as "pending" again; everything else shows as stored.
function renewalDisplayStatus(PDO $pdo, array $row): string {
    $status = strtolower(trim((string)$row['status']));
    return (in_array($status, ['eligible', 'terminated'], true) && isRenewalActionable($pdo, $row)) ? 'pending' : (string)$row['status'];
}

// Ledger counts exactly as the Renewal & Retention page's summary shows them (no filters).
// "pending" includes at-risk entries — both still await a Renew/Terminate decision.
function renewalLedgerSummary(PDO $pdo): array {
    backfillCurrentTermRenewals($pdo);
    $records = renewalRecordIndex($pdo);
    $summary = ['pending' => 0, 'eligible' => 0, 'at_risk' => 0, 'terminated' => 0, 'total' => 0];
    foreach ($pdo->query("SELECT * FROM renewal_retention")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($records[renewalRecordKey((string)$r['student_id'], (string)$r['school_year'], (string)$r['semester'], (string)$r['scholarship_type'])])) {
            continue; // no Record behind it — the ledger doesn't show it
        }
        $summary['total']++;
        switch (strtolower(trim(renewalDisplayStatus($pdo, $r)))) {
            case 'pending': $summary['pending']++; break;
            case 'at-risk': $summary['pending']++; $summary['at_risk']++; break;
            case 'eligible': $summary['eligible']++; break;
            case 'terminated': $summary['terminated']++; break;
        }
    }
    return $summary;
}
