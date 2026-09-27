<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $action = trim($_POST['action'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($id <= 0) {
            sendError('Invalid action request.');
        }

        if (!in_array($action, ['renew', 'terminate'])) {
            sendError('Invalid action request.');
        }

        $rowStmt = $pdo->prepare("SELECT * FROM renewal_retention WHERE id = ?");
        $rowStmt->execute([$id]);
        $cand = $rowStmt->fetch();
        if (!$cand) {
            sendError('This renewal entry no longer exists.', 404);
        }

        // View-only while the Active Semester is still the row's own term, or (once already
        // decided) until a brand-new Academic Year begins — see isRenewalActionable().
        if (!isRenewalActionable($pdo, $cand)) {
            $statusVal = strtolower(trim((string)$cand['status']));
            $msg = in_array($statusVal, ['eligible', 'terminated'], true)
                ? 'Locked: this entry can only be viewed until a new Academic Year begins (Settings > Portal Configuration).'
                : 'Locked: this entry can only be viewed until the next semester begins (change the Active Semester in Settings > Portal Configuration).';
            sendError($msg, 423);
        }

        $origin = strtolower((string)($cand['origin'] ?? 'approved'));
        $required = resolveGwaRequirement($pdo, (string)$cand['scholarship_type']);
        // Reaching here means this entry is actionable now, so its GWA basis is always the
        // term right before the current one (see the identical logic in the GET listing below).
        [$gwaSemester] = renewalPreviousTerm(getActiveSemester($pdo), getActiveSchoolYear($pdo));
        $figures = liveRenewalFigures($cand, getSemesterGradeStats($pdo, [$cand['student_id']]), $gwaSemester);

        // Renew/Terminate are always clickable and always succeed regardless of GWA status
        // (missing grades, or GWA above/below the requirement) — staff decide manually now.
        if ($action === 'renew' && $origin === 'rejected') {
            sendError('Cannot renew: this applicant was rejected in Evaluation.', 422);
        }
        // Keep the row's stored GWA in step with the latest imported grades.
        $pdo->prepare("UPDATE renewal_retention SET gwa = ?, failing_grades = ? WHERE id = ?")->execute([$figures['gwa'], $figures['failing'], $id]);

        $newStatus = $action === 'renew' ? 'eligible' : 'terminated';
        // decided_semester/decided_school_year record WHEN this decision was made, separately
        // from the row's origin semester/school_year (which must stay fixed — it's how this row
        // is matched back to its Record). isRenewalActionable() compares against these to know
        // whether the decision is still within its own school year (locked) or a brand-new one
        // has begun since (reassessable) — without them, the decision would immediately display
        // back as "Pending" on the very next load instead of "Renewed"/"Terminated".
        $stmt = $pdo->prepare("UPDATE renewal_retention SET status = ?, remarks = ?, decided_semester = ?, decided_school_year = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $remarks ?: "Status updated to $newStatus", getActiveSemester($pdo), getActiveSchoolYear($pdo), $id]);

        $stmtWho = $pdo->prepare("SELECT student_id, name FROM renewal_retention WHERE id = ?");
        $stmtWho->execute([$id]);
        $who = $stmtWho->fetch();
        $whoName = $who ? ($who['name'] . ' (Student ID: ' . $who['student_id'] . ')') : ('Scholar #' . $id);

        // Terminating a scholar who held the scholarship bars them from applying for that same
        // scholarship again (they may still apply for another). A rejected applicant's entry is
        // only closed, and MERIT-BASED Academic is never barred.
        $barred = false;
        if ($action === 'terminate' && $origin !== 'rejected') {
            $barred = recordScholarshipTermination($pdo, (string)$cand['student_id'], (string)$cand['scholarship_type'], $id,
                'GWA ' . number_format($figures['gwa'], 2) . ' did not meet the required ' . number_format($required, 2) . ' (' . normalizeSemesterName($cand['semester']) . ' ' . $cand['school_year'] . ').');
        }

        // The Record follows the decision: renewed = approved, terminated = rejected. Matched
        // using $cand's ORIGINAL semester/school_year (captured before the update above) — that
        // is the term the underlying Record itself was actually filed under.
        $recordStatus = syncRecordWithRenewal($pdo, $cand, $action);

        if ($action === 'renew') {
            logActivity($pdo, 'Scholarship Renewal', 'Renewal & Retention', $whoName . ' scholarship was renewed (eligible)' . ($recordStatus ? '; their record is now ' . $recordStatus . '.' : '.'), $id);
        } else {
            logActivity($pdo, 'Scholarship Terminated', 'Renewal & Retention', $whoName . ' was terminated' . ($recordStatus ? '; their record is now ' . $recordStatus : '') . ($barred ? '; they can no longer apply for ' . $cand['scholarship_type'] . '.' : '.'), $id);
        }

        $message = $action === 'renew'
            ? 'Scholarship renewed.'
            : ($barred ? 'Scholar terminated. They can no longer apply for ' . $cand['scholarship_type'] . ' but may apply for a different scholarship.' : 'Entry terminated.');
        sendJson(['success' => true, 'message' => $message, 'recordStatus' => $recordStatus]);
    }

    // Every record of the active term has its (locked) row here, including older ones and rejections.
    backfillCurrentTermRenewals($pdo);

    $statusFilter = trim($_GET['status'] ?? '');
    $syFilter = trim($_GET['sy'] ?? '');
    $semFilter = trim($_GET['sem'] ?? '');
    $typeFilter = trim($_GET['type'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $query = "SELECT * FROM renewal_retention WHERE 1=1";
    $params = [];

    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
    }
    if ($syFilter !== '' && $syFilter !== 'all') {
        $query .= " AND school_year LIKE ?";
        $params[] = "%$syFilter%";
    }
    if ($semFilter !== '' && $semFilter !== 'all') {
        $query .= " AND LOWER(semester) LIKE LOWER(?)";
        $params[] = "%$semFilter%";
    }
    if ($typeFilter !== '' && $typeFilter !== 'all') {
        $query .= " AND LOWER(scholarship_type) LIKE LOWER(?)";
        $params[] = "%$typeFilter%";
    }
    if ($search !== '') {
        $query .= " AND (name LIKE ? OR student_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // GWA and failing grades are read live from the imported grades of each row's own semester.
    $gradeStats = getSemesterGradeStats($pdo, array_column($rows, 'student_id'));

    $programStmt = $pdo->prepare("SELECT program FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");

    // Renewal & Retention only ever shows scholars who are still on Records — it has no
    // existence of its own. Every row here is matched back to its Record (same student,
    // school year, semester and scholarship type — the same key sendRecordToRenewal() uses),
    // so a Records edit (name, etc.) is reflected immediately and a deleted Record's shadow
    // row disappears from view instead of lingering as an orphan.
    $recordByKey = [];
    foreach ($pdo->query("SELECT student_id, sy, semester, scholarship_type, name FROM records ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) as $rec) {
        $key = trim((string)$rec['student_id']) . '|' . trim((string)$rec['sy']) . '|' . normalizeSemesterName($rec['semester']) . '|' . strtolower(trim((string)$rec['scholarship_type']));
        $recordByKey[$key] = $rec; // later (higher id) rows win on a duplicate key
    }

    $data = [];
    foreach ($rows as $r) {
        $recordKey = trim((string)$r['student_id']) . '|' . trim((string)$r['school_year']) . '|' . normalizeSemesterName($r['semester']) . '|' . strtolower(trim((string)$r['scholarship_type']));
        $matchedRecord = $recordByKey[$recordKey] ?? null;
        if ($matchedRecord === null) {
            continue; // No Record behind this entry (deleted, or old data) — don't show it.
        }

        $programStmt->execute([$r['student_id']]);
        $programName = (string)$programStmt->fetchColumn();

        // Pending: locked only while the Active Semester is still its own term. Decided
        // (renewed/terminated): locked for the rest of that school year, then opens back up
        // for reassessment once a brand-new Academic Year begins — see isRenewalActionable().
        $decidable = isRenewalActionable($pdo, $r);
        $locked = !$decidable;
        $originVal = strtolower((string)($r['origin'] ?? 'approved'));
        $required = resolveGwaRequirement($pdo, (string)$r['scholarship_type']);

        $activeSem = getActiveSemester($pdo);
        $activeSy = getActiveSchoolYear($pdo);

        // The "Semester" column always mirrors the current Active Semester in Portal
        // Configuration — whatever term the whole portal is in right now, live, whether this
        // row is still locked in it or already actionable.
        $decisionSemester = $activeSem;
        $decisionSchoolYear = $activeSy;

        if ($locked) {
            // Not actionable yet: the row's own term IS the current active term (that's what
            // "locked" means here) — that's also the GWA basis.
            $gwaSemester = (string)$r['semester'];
            $gwaSchoolYear = (string)$r['school_year'];
        } else {
            // Actionable right now — whether still pending, or a decided entry reopened for a
            // new school year's reassessment. The GWA basis rolls back to the term right before
            // the current one (the most recently completed regular semester), not the row's
            // original evaluation term, which could be a full year stale by now.
            [$gwaSemester, $gwaSchoolYear] = renewalPreviousTerm($activeSem, $activeSy);
        }

        $live = liveRenewalFigures($r, $gradeStats, $gwaSemester);
        $r['gwa'] = $live['gwa'];
        $r['failing_grades'] = $live['failing'];

        $statusVal = strtolower(trim((string)$r['status']));
        // A decided entry that's now open for reassessment reads as "Pending" again for the
        // new cycle — last year's real decision stays on file, only the display resets.
        $displayStatus = (in_array($statusVal, ['eligible', 'terminated'], true) && $decidable) ? 'pending' : $r['status'];

        $data[] = [
            'gwaRequirement' => $required,
            'decisionSemester' => $decisionSemester,
            'decisionSchoolYear' => $decisionSchoolYear,
            // The school year a Renew/Terminate decision was actually made in — not the row's
            // origin year (that one never changes; it's how the row is matched to its Record).
            'decidedSchoolYear' => $r['decided_school_year'] ?: $r['school_year'],
            'gwaSemester' => $gwaSemester,
            'gwaSchoolYear' => $gwaSchoolYear,
            // No grades on file yet isn't a failure (e.g. a first-year scholar whose eligibility
            // still follows their Grade 12 record) — only a recorded GWA above the requirement is.
            'meetsGwa' => (float)$r['gwa'] <= 0 || (float)$r['gwa'] <= $required,
            'id' => (int)$r['id'],
            'studentId' => $r['student_id'],
            'student_id' => $r['student_id'],
            // The Record's own current name — never the stale copy taken when this row was made.
            'name' => $matchedRecord['name'],
            'gwa' => (float)$r['gwa'],
            'failingGrades' => (int)$r['failing_grades'],
            'failing_grades' => (int)$r['failing_grades'],
            'enrolled' => (bool)$r['enrolled'],
            'status' => $displayStatus,
            'schoolYear' => $r['school_year'],
            'school_year' => $r['school_year'],
            'semester' => $r['semester'],
            'scholarshipType' => $r['scholarship_type'],
            'scholarship_type' => $r['scholarship_type'],
            'remarks' => $r['remarks'],
            'program' => $programName,
            'programCode' => programAcronym($programName),
            'origin' => $originVal,
            'locked' => $locked,
            // Both buttons are enabled regardless of GWA status — staff decide manually now.
            'canRenew' => $decidable && $originVal !== 'rejected',
            'canTerminate' => $decidable,
        ];
    }

    $activeTerm = getActiveSemester($pdo) . ' ' . getActiveSchoolYear($pdo);

    $pendingCount = 0; $eligibleCount = 0; $atRiskCount = 0; $terminatedCount = 0;
    foreach ($data as $d) {
        switch (strtolower(trim((string)$d['status']))) {
            case 'pending': $pendingCount++; break;
            case 'at-risk': $pendingCount++; $atRiskCount++; break;
            case 'eligible': $eligibleCount++; break;
            case 'terminated': $terminatedCount++; break;
        }
    }

    sendJson([
        'success' => true,
        'data' => $data,
        'activeTerm' => $activeTerm,
        'summary' => [
            'pending' => $pendingCount,
            'eligible' => $eligibleCount,
            'at_risk' => $atRiskCount,
            'terminated' => $terminatedCount,
            'total' => count($data)
        ]
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
