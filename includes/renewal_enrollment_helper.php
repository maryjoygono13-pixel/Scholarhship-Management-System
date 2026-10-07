<?php
/*
 * Renewal & Retention: is each scholar still officially enrolled for the active term? Answered by the
 * Registrar's database, checked whenever the Active Semester or Academic Year changes in Settings >
 * Portal Configuration (changeActiveTerm() in term_helper.php), and for any entry not yet checked for
 * the current term when the Renewal & Retention page loads.
 *
 * Source: the Registrar's per-semester enrollments table (REGISTRAR_ENROLLMENTS_TABLE): a row for the
 * term with an enrolled status = enrolled; a "Dropped" (or other) row = dropped; no row = didn't enroll.
 * Without that table, the students table's enrollment_status / school_year is used instead.
 *
 * The result is stored on the entry (enrolled, enrollment_status, enrollment_term). A scholar the
 * Registrar says isn't enrolled for the active term can't be renewed — only terminated.
 */

require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/enrollment_verification_helper.php';   // evRegistrarEnrollment(), term helpers

// Whether the Registrar's database has the per-semester enrollments table.
function registrarHasEnrollmentsTable(PDO $reg): bool {
    static $has = null;
    if ($has === null) {
        $stmt = $reg->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([REGISTRAR_ENROLLMENTS_TABLE]);
        $has = (int)$stmt->fetchColumn() > 0;
    }
    return $has;
}

/*
 * One student's enrollment for a term, per the Registrar's database:
 *   ['found' => bool (in the Registrar's database at all), 'enrolled' => bool, 'label' => "Enrolled" / "Dropped" / ...]
 */
function registrarTermEnrollment(PDO $reg, string $studentId, string $schoolYear, string $semester): array {
    $stu = $reg->prepare('SELECT * FROM ' . REGISTRAR_STUDENTS_TABLE . ' WHERE student_id = ?');
    $stu->execute([trim($studentId)]);
    $student = $stu->fetch(PDO::FETCH_ASSOC);
    if (!$student) return ['found' => false, 'enrolled' => false, 'label' => "Not in the Registrar's database"];

    if (registrarHasEnrollmentsTable($reg)) {
        $term = $reg->prepare('SELECT enrollment_status FROM ' . REGISTRAR_ENROLLMENTS_TABLE . ' WHERE student_id = ? AND school_year = ? AND semester = ? ORDER BY id DESC LIMIT 1');
        $term->execute([trim($studentId), $schoolYear, $semester]);
        $status = $term->fetchColumn();
        if ($status === false) return ['found' => true, 'enrolled' => false, 'label' => 'Not enrolled'];
        $status = trim((string)$status);
        $enrolled = in_array(strtolower($status), array_map('strtolower', REGISTRAR_ENROLLED_STATUSES), true);
        return ['found' => true, 'enrolled' => $enrolled, 'label' => $enrolled ? 'Officially enrolled' : ($status !== '' ? $status : 'Not enrolled')];
    }

    // No per-semester table: the student's overall status for the Academic Year.
    [$enrolled, $why] = evRegistrarEnrollment($student, $schoolYear);
    return ['found' => true, 'enrolled' => $enrolled, 'label' => $enrolled ? 'Officially enrolled' : ($why ?: 'Not enrolled')];
}

/*
 * Checks every Renewal & Retention entry (or, with $onlyUnchecked, only those not yet checked for the
 * active term) against the Registrar's database and stores the result. Returns
 *   ['reachable' => bool, 'checked' => n, 'enrolled' => n, 'notEnrolled' => n, 'notFound' => n, 'term' => "2nd Semester 2026-2027"].
 * A student who isn't in the Registrar's database keeps their previous enrollment flag.
 */
function syncRenewalEnrollment(PDO $pdo, bool $onlyUnchecked = false): array {
    $semester = getActiveSemester($pdo);
    $schoolYear = getActiveSchoolYear($pdo);
    $termLabel = "$semester $schoolYear";
    $result = ['reachable' => true, 'checked' => 0, 'enrolled' => 0, 'notEnrolled' => 0, 'notFound' => 0, 'term' => $termLabel];

    $sql = 'SELECT id, student_id FROM renewal_retention' . ($onlyUnchecked ? ' WHERE enrollment_term IS NULL OR enrollment_term != ?' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($onlyUnchecked ? [$termLabel] : []);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return $result;

    try {
        $reg = getRegistrarDB();
    } catch (Throwable $e) {
        $result['reachable'] = false;
        return $result;
    }

    $save = $pdo->prepare('UPDATE renewal_retention SET enrolled = ?, enrollment_status = ?, enrollment_term = ?, enrollment_checked_at = UTC_TIMESTAMP() WHERE id = ?');
    $saveNotFound = $pdo->prepare('UPDATE renewal_retention SET enrollment_status = ?, enrollment_term = ?, enrollment_checked_at = UTC_TIMESTAMP() WHERE id = ?');
    $cache = [];
    foreach ($rows as $r) {
        $sid = trim((string)$r['student_id']);
        $cache[$sid] ??= registrarTermEnrollment($reg, $sid, $schoolYear, $semester);
        $e = $cache[$sid];
        $result['checked']++;
        if (!$e['found']) {
            $result['notFound']++;
            $saveNotFound->execute([$e['label'], $termLabel, (int)$r['id']]);
            continue;
        }
        $result[$e['enrolled'] ? 'enrolled' : 'notEnrolled']++;
        $save->execute([$e['enrolled'] ? 1 : 0, $e['label'], $termLabel, (int)$r['id']]);
    }
    return $result;
}

// Whether an entry's stored check says the Registrar doesn't have them enrolled for the active term.
function renewalRegistrarNotEnrolled(PDO $pdo, array $row): bool {
    $term = getActiveSemester($pdo) . ' ' . getActiveSchoolYear($pdo);
    $label = (string)($row['enrollment_status'] ?? '');
    return ($row['enrollment_term'] ?? '') === $term && $label !== '' && $label !== "Not in the Registrar's database" && empty($row['enrolled']);
}
