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

        if (empty($name) || empty($code)) {
            sendError('Scholarship name and code are required.');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE scholarships SET name = ?, code = ?, description = ?, type = ?, subtype = ?, gwa_requirement = ?, slots = ?, slots_available = ?, unlimited_slots = ?, coverage = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $code, $description, $type, $subtype, $gwaReq, $slots, $slots, $unlimited, $coverage, $status, $id]);
            // The sub-type's required GWA is what Evaluation/Renewal enforce,
            // so editing it from the program keeps the two in sync.
            if ($subtype !== '') {
                $pdo->prepare("UPDATE scholarship_subtypes SET gwa_requirement = ? WHERE name = ? AND type_id = (SELECT id FROM scholarship_types WHERE name = ?)")
                    ->execute([$gwaReq, $subtype, $type]);
            }
            logActivity($pdo, 'Scholarship Updated', 'Scholarships', $name . ' (' . $code . ') was updated.', $id);
            sendJson(['success' => true, 'id' => $id, 'message' => 'Scholarship updated successfully.']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO scholarships (name, code, description, type, subtype, gwa_requirement, slots, slots_available, unlimited_slots, coverage, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $code, $description, $type, $subtype, $gwaReq, $slots, $slots, $unlimited, $coverage, $status]);
            $newId = (int)$pdo->lastInsertId();
            logActivity($pdo, 'Scholarship Added', 'Scholarships', $name . ' (' . $code . ') was added.', $newId);
            sendJson(['success' => true, 'id' => $newId, 'message' => 'Scholarship added successfully.']);
        }
    }

    $stmt = $pdo->query("SELECT * FROM scholarships ORDER BY id DESC");
    $rows = $stmt->fetchAll();

    $data = array_map(function($r) {
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
            'slotsAvailable' => (int)$r['slots_available'],
            'slots_available' => (int)$r['slots_available'],
            'coverage' => $r['coverage'],
            'status' => $r['status']
        ];
    }, $rows);

    sendJson(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
