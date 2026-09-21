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

    // One row per scholarship per term: a student holding two scholarship types has two rows.
    $exists = $pdo->prepare("SELECT id FROM renewal_retention WHERE student_id = ? AND school_year = ? AND semester = ? AND LOWER(TRIM(scholarship_type)) = LOWER(TRIM(?))");
    $exists->execute([$sid, $sy, $semester, (string)$rec['scholarship_type']]);
    if ($exists->fetchColumn()) return null;

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
        $status = ($gwa > 0 && $gwa <= $required) ? 'eligible' : 'at-risk';
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
 * A renewal row's GWA and failing count, taken live from the imported grades of its
 * own semester (so grades imported after the row was created still count), falling
 * back to the figures stored on the row.
 */
function liveRenewalFigures(array $row, array $gradeStats): array {
    $st = $gradeStats[(string)$row['student_id']][normalizeSemesterName($row['semester'])] ?? null;
    return [
        'gwa' => $st ? $st['gwa'] : (float)$row['gwa'],
        'failing' => $st ? $st['failing'] : (int)$row['failing_grades'],
    ];
}

/*
 * Keeps the Record in step with the decision taken in Renewal & Retention.
 * Returns the record status it was set to, or null when no matching record exists.
 */
function syncRecordWithRenewal(PDO $pdo, array $ren, string $action): ?string {
    $map = ['renew' => 'approved', 'terminate' => 'rejected', 'flag' => 'pending'];
    if (!isset($map[$action])) return null;
    $newStatus = $map[$action];

    $sem = normalizeSemesterName($ren['semester']);
    $find = $pdo->prepare("SELECT id, semester, applicant_id, student_id FROM records WHERE student_id = ? AND TRIM(sy) = ? AND LOWER(TRIM(scholarship_type)) = LOWER(TRIM(?)) ORDER BY id DESC");
    $find->execute([trim((string)$ren['student_id']), trim((string)$ren['school_year']), (string)$ren['scholarship_type']]);
    $record = null;
    foreach ($find->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (normalizeSemesterName($r['semester']) === $sem) { $record = $r; break; }
    }
    if (!$record) return null;

    $pdo->prepare("UPDATE records SET status = ? WHERE id = ?")->execute([$newStatus, $record['id']]);

    // Renewed after their term had already ended: go back to Evaluation for the current
    // term now, since the term switch has passed them by.
    if ($action === 'renew' && ($sem !== getActiveSemester($pdo) || trim((string)$ren['school_year']) !== getActiveSchoolYear($pdo))) {
        $app = null;
        if ((int)($record['applicant_id'] ?? 0) > 0) {
            $stmt = $pdo->prepare("SELECT id, status FROM applicants WHERE id = ?");
            $stmt->execute([(int)$record['applicant_id']]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$app) {
            $stmt = $pdo->prepare("SELECT id, status FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$record['student_id']]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        // ...unless they already have a record for the current term.
        if ($app && strtolower((string)$app['status']) === 'approved'
            && !hasRecordForTerm($pdo, (string)$record['student_id'], (string)$ren['scholarship_type'], getActiveSemester($pdo), getActiveSchoolYear($pdo), (int)$record['id'])) {
            $pdo->prepare("UPDATE applicants SET status = 'review', semester = ?, school_year = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([getActiveSemester($pdo), getActiveSchoolYear($pdo), $app['id']]);
            recalculateApplicantGwa($pdo, (string)$record['student_id'], true);
        }
    }

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
        $origin = strtolower(trim((string)$rec['status'])) === 'rejected' ? 'rejected' : 'approved';
        if (sendRecordToRenewal($pdo, $rec, 'pending', null, $origin) !== null) $added++;
    }
    return $added;
}
