<?php
/*
 * Evaluation -> Records -> Renewal & Retention flow:
 *
 *   1. Evaluation approves an applicant. Their Record is created as PENDING and
 *      they are sent straight to Renewal & Retention (sendRecordToRenewal).
 *   2. Renewal & Retention checks the scholar's GWA against the requirement of
 *      their scholarship type.
 *   3. "Renew Scholarship" moves the Record to APPROVED (syncRecordWithRenewal).
 *      "Terminate" turns it REJECTED; "Flag for Review" keeps it PENDING.
 */

require_once __DIR__ . '/term_helper.php';
require_once __DIR__ . '/grades_helper.php';
require_once __DIR__ . '/gwa_helper.php';

/*
 * Adds the record's scholar to Renewal & Retention for the record's term, unless
 * they are already there. $status null = work it out from the GWA (used for records
 * that were already approved); 'pending' = awaiting the renewal decision.
 * Returns the new renewal row id, or null when one already existed.
 */
function sendRecordToRenewal(PDO $pdo, array $rec, ?string $status = 'pending', ?string $remarks = null): ?int {
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
        INSERT INTO renewal_retention (student_id, name, gwa, failing_grades, enrolled, status, school_year, semester, scholarship_type, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        $remarks ?? 'Awaiting renewal check: GWA against the scholarship requirement.',
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
