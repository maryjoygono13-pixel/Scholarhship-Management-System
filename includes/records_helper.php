<?php
/*
 * Records are the permanent history of every scholarship (and Dean's List) decision:
 *   - exactly ONE record per student + scholarship type + school year + semester (never a duplicate),
 *   - a new school year or semester gets its own record (e.g. 1st Semester, then the 2nd Semester
 *     renewal), so every term stays on file and can be searched / exported,
 *   - each record keeps the student's program and year level AT THAT TIME (snapshot), so a later
 *     year level never rewrites an older record.
 * Every place that creates a record goes through saveTermRecord().
 */

require_once __DIR__ . '/term_helper.php';
require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/programs_helper.php';

// The record for this exact student / scholarship / school year / semester, or null.
function findTermRecord(PDO $pdo, string $studentId, string $scholarshipType, string $schoolYear, string $semester): ?array {
    $stmt = $pdo->prepare("SELECT * FROM records WHERE student_id = ? AND TRIM(sy) = ? AND LOWER(TRIM(scholarship_type)) = LOWER(TRIM(?)) ORDER BY id DESC");
    $stmt->execute([trim($studentId), trim($schoolYear), $scholarshipType]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (normalizeSemesterName($r['semester']) === normalizeSemesterName($semester)) return $r;
    }
    return null;
}

/*
 * The student's program and year level right now — saved on a record as the snapshot for its term.
 * From their application, else their Scholars entry, else the Registrar's database.
 * Returns ['program' => string, 'year_level' => string] (empty strings when unknown).
 */
function studentProgramSnapshot(PDO $pdo, string $studentId, ?int $applicantId = null): array {
    $app = null;
    if ($applicantId) {
        $s = $pdo->prepare("SELECT program, year_level FROM applicants WHERE id = ?");
        $s->execute([$applicantId]);
        $app = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$app) {
        $s = $pdo->prepare("SELECT program, year_level FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");
        $s->execute([trim($studentId)]);
        $app = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($app && trim((string)$app['program']) !== '') {
        return ['program' => trim((string)$app['program']), 'year_level' => trim((string)$app['year_level'])];
    }

    $s = $pdo->prepare("SELECT department, year_level FROM scholars WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $s->execute([trim($studentId)]);
    if ($sch = $s->fetch(PDO::FETCH_ASSOC)) {
        $dept = trim((string)$sch['department']);
        $program = function_exists('resolveProgramName') ? (resolveProgramName($dept) ?? (array_search($dept, PROGRAM_DISPLAY_NAMES, true) ?: $dept)) : $dept;
        $year = (int)$sch['year_level'] > 0 ? ['', '1st Year', '2nd Year', '3rd Year', '4th Year'][min(4, (int)$sch['year_level'])] : '';
        return ['program' => $program, 'year_level' => $year];
    }

    try {
        $s = getRegistrarDB()->prepare('SELECT program, year_level FROM ' . REGISTRAR_STUDENTS_TABLE . ' WHERE student_id = ?');
        $s->execute([trim($studentId)]);
        if ($reg = $s->fetch(PDO::FETCH_ASSOC)) return ['program' => (string)$reg['program'], 'year_level' => (string)$reg['year_level']];
    } catch (Throwable $e) {
        // Registrar's database unreachable: leave them blank.
    }
    return ['program' => '', 'year_level' => ''];
}

/*
 * Saves the record for one term without ever duplicating it.
 * $f: student_id, name, scholarship_type, status, semester, sy, remarks, plus optional applicant_id,
 *     origin ('evaluation' / 'renewal' / 'deans_list'), program, year_level, date_evaluated.
 * $updateExisting: when the term already has a record, update its status / remarks (true) or leave
 *     it exactly as it is (false).
 * Returns ['id' => int, 'created' => bool, 'record' => array].
 */
function saveTermRecord(PDO $pdo, array $f, bool $updateExisting = false): array {
    $semester = normalizeSemesterName((string)$f['semester']);
    $existing = findTermRecord($pdo, (string)$f['student_id'], (string)$f['scholarship_type'], (string)$f['sy'], $semester);
    if ($existing) {
        if ($updateExisting) {
            $pdo->prepare("UPDATE records SET status = ?, remarks = ?, date_evaluated = ? WHERE id = ?")
                ->execute([$f['status'], $f['remarks'] ?? $existing['remarks'], $f['date_evaluated'] ?? date('Y-m-d'), (int)$existing['id']]);
            $existing = array_merge($existing, ['status' => $f['status'], 'remarks' => $f['remarks'] ?? $existing['remarks']]);
        }
        return ['id' => (int)$existing['id'], 'created' => false, 'record' => $existing];
    }

    $snapshot = (isset($f['program']) && trim((string)$f['program']) !== '')
        ? ['program' => trim((string)$f['program']), 'year_level' => trim((string)($f['year_level'] ?? ''))]
        : studentProgramSnapshot($pdo, (string)$f['student_id'], isset($f['applicant_id']) ? (int)$f['applicant_id'] : null);

    $pdo->prepare("INSERT INTO records (applicant_id, student_id, name, scholarship_type, status, semester, sy, date_evaluated, remarks, program, year_level, origin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            !empty($f['applicant_id']) ? (int)$f['applicant_id'] : null,
            trim((string)$f['student_id']),
            trim((string)$f['name']),
            (string)$f['scholarship_type'],
            (string)$f['status'],
            $semester,
            trim((string)$f['sy']),
            $f['date_evaluated'] ?? date('Y-m-d'),
            (string)($f['remarks'] ?? ''),
            $snapshot['program'] !== '' ? $snapshot['program'] : null,
            $snapshot['year_level'] !== '' ? $snapshot['year_level'] : null,
            (string)($f['origin'] ?? 'evaluation'),
        ]);
    $id = (int)$pdo->lastInsertId();
    $row = $pdo->prepare("SELECT * FROM records WHERE id = ?");
    $row->execute([$id]);
    return ['id' => $id, 'created' => true, 'record' => $row->fetch(PDO::FETCH_ASSOC)];
}
