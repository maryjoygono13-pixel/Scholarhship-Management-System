<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = trim($_POST['type'] ?? 'Academic');
        $subtype = trim($_POST['subtype'] ?? '');
        $gwaReq = (float)($_POST['gwa_requirement'] ?? $_POST['gwaReq'] ?? 2.0);
        $slots = (int)($_POST['slots'] ?? 0);
        // "No slot limit": anyone who meets the GWA requirement can hold it.
        $unlimited = !empty($_POST['unlimited_slots']) && $_POST['unlimited_slots'] !== '0' ? 1 : 0;
        if ($unlimited) {
            $slots = 0;
        }
        $coverage = trim($_POST['coverage'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $educationLevel = trim($_POST['education_level'] ?? 'Collegiate') ?: 'Collegiate';
        $schoolYear = trim($_POST['school_year'] ?? '');
        $applicationStart = trim($_POST['application_start'] ?? '') ?: null;
        $applicationDeadline = trim($_POST['application_deadline'] ?? '') ?: null;

        if (empty($name) || empty($code)) {
            sendError('Scholarship name and code are required.');
        }

        // MERIT-BASED: the Full / Half Merit GWA ranges and the Basic Education requirement are
        // edited on the form and become the rules every Merit decision uses. The GWA requirement
        // then follows them: collegiate = the Half Merit limit; Basic Education = none.
        $meritRanges = null;
        if (isMeritScholarshipType($type)) {
            $num = fn($k, $def) => is_numeric($_POST[$k] ?? null) ? round((float)$_POST[$k], 2) : $def;
            $meritRanges = [
                'full_min' => $num('merit_full_min', MERIT_DEFAULT_RANGES['full_min']),
                'full_max' => $num('merit_full_max', MERIT_DEFAULT_RANGES['full_max']),
                'half_min' => $num('merit_half_min', MERIT_DEFAULT_RANGES['half_min']),
                'half_max' => $num('merit_half_max', MERIT_DEFAULT_RANGES['half_max']),
                'basic_criteria' => trim((string)($_POST['merit_basic_criteria'] ?? '')) ?: MERIT_DEFAULT_RANGES['basic_criteria'],
            ];
            foreach (['full_min', 'full_max', 'half_min', 'half_max'] as $k) {
                if ($meritRanges[$k] < 1.00 || $meritRanges[$k] > 5.00) {
                    sendError('Merit GWA values must be between 1.00 and 5.00.');
                }
            }
            if ($meritRanges['full_min'] > $meritRanges['full_max'] || $meritRanges['half_min'] > $meritRanges['half_max']) {
                sendError('In each Merit range, the "from" GWA cannot be higher than the "to" GWA.');
            }
            if ($meritRanges['half_min'] <= $meritRanges['full_max']) {
                sendError('Half Merit must start after Full Merit ends (e.g. Full Merit up to 1.30, Half Merit from 1.31).');
            }
            if (mb_strlen($meritRanges['basic_criteria']) > 255) {
                sendError('The Basic Education requirement is too long (255 characters max).');
            }
            $gwaReq = meritGwaRequirementFor($educationLevel, $meritRanges);
        }
        // Stores the Merit rules on the program (only for MERIT-BASED programs).
        $saveMeritRanges = function (int $programId) use ($pdo, $meritRanges): void {
            if ($meritRanges === null) return;
            $pdo->prepare("UPDATE scholarships SET merit_full_min = ?, merit_full_max = ?, merit_half_min = ?, merit_half_max = ?, merit_basic_criteria = ? WHERE id = ?")
                ->execute([$meritRanges['full_min'], $meritRanges['full_max'], $meritRanges['half_min'], $meritRanges['half_max'], $meritRanges['basic_criteria'], $programId]);
        };

        // Capacity taken is always counted live from actual applicants (scholarshipTakenCount),
        // never trusted from a stored counter — that counter is the exact thing that drifted out
        // of sync before (showing "0 available" on programs nobody had actually applied to). The
        // stored slots_available column is still written, only so any other reader that queries
        // the table directly sees a sensible number, not because it's the source of truth.
        $liveAvailable = $unlimited ? $slots : max(0, $slots - scholarshipTakenCount($pdo, $subtype, $name));

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE scholarships SET name = ?, code = ?, description = ?, type = ?, subtype = ?, gwa_requirement = ?, slots = ?, slots_available = ?, unlimited_slots = ?, coverage = ?, status = ?, education_level = ?, school_year = ?, application_start = ?, application_deadline = ? WHERE id = ?");
            $stmt->execute([$name, $code, $description, $type, $subtype, $gwaReq, $slots, $liveAvailable, $unlimited, $coverage, $status, $educationLevel, $schoolYear, $applicationStart, $applicationDeadline, $id]);
            // The sub-type's required GWA is what Evaluation/Renewal enforce,
            // so editing it from the program keeps the two in sync.
            if ($subtype !== '') {
                $pdo->prepare("UPDATE scholarship_subtypes SET gwa_requirement = ? WHERE name = ? AND type_id = (SELECT id FROM scholarship_types WHERE name = ?)")
                    ->execute([$gwaReq, $subtype, $type]);
            }
            $saveMeritRanges($id);
            logActivity($pdo, 'Scholarship Updated', 'Scholarships', $name . ' (' . $code . ') was updated.', $id);
            sendJson(['success' => true, 'id' => $id, 'message' => 'Scholarship updated successfully.']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO scholarships (name, code, description, type, subtype, gwa_requirement, slots, slots_available, unlimited_slots, coverage, status, education_level, school_year, application_start, application_deadline)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $code, $description, $type, $subtype, $gwaReq, $slots, $liveAvailable, $unlimited, $coverage, $status, $educationLevel, $schoolYear, $applicationStart, $applicationDeadline]);
            $newId = (int)$pdo->lastInsertId();
            $saveMeritRanges($newId);
            logActivity($pdo, 'Scholarship Added', 'Scholarships', $name . ' (' . $code . ') was added.', $newId);
            sendJson(['success' => true, 'id' => $newId, 'message' => 'Scholarship added successfully.']);
        }
    }

    $stmt = $pdo->query("SELECT * FROM scholarships ORDER BY id DESC");
    $rows = $stmt->fetchAll();

    $data = array_map(function($r) use ($pdo) {
        $available = scholarshipSlotsAvailable($pdo, $r);
        $taken = scholarshipTakenCount($pdo, (string)($r['subtype'] ?? ''), (string)$r['name']);
        return [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'code' => $r['code'],
            'description' => $r['description'],
            'type' => $r['type'],
            'subtype' => $r['subtype'] ?? '',
            'gwaRequirement' => (float)$r['gwa_requirement'],
            'gwa_requirement' => (float)$r['gwa_requirement'],
            'slots' => (int)$r['slots'],
            'unlimitedSlots' => !empty($r['unlimited_slots']),
            // How many applicants currently hold a slot — counted live, never a stored counter.
            'slotsTaken' => $taken,
            'slots_taken' => $taken,
            'slotsAvailable' => $available,
            'slots_available' => $available,
            'coverage' => $r['coverage'],
            'status' => $r['status'],
            'educationLevel' => $r['education_level'] ?? 'Collegiate',
            'schoolYear' => $r['school_year'] ?? '',
            'applicationStart' => $r['application_start'] ?? '',
            'applicationDeadline' => $r['application_deadline'] ?? '',
            // MERIT-BASED rules as set on this program (defaults until edited).
            'meritRanges' => meritRangesFromRow($r),
        ];
    }, $rows);

    sendJson(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
