<?php
require_once __DIR__ . '/init.php';

require_once __DIR__ . '/../includes/scholar_standing_helper.php';   // shared with the Dashboard

try {
    $pdo = getDB();

    // Dean's Listers from the Registrar's database are added by "Scan Registrar Now"
    // (api/scan_deans_list.php), so the page can say how many new ones were found.
    // Anyone approved in Records for any other scholarship belongs here too.
    syncApprovedScholars($pdo);

    $department = trim($_GET['department'] ?? '');
    $yearLevel = (int)($_GET['year_level'] ?? $_GET['year'] ?? 0);
    $status = trim($_GET['status'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $sql = "SELECT * FROM scholars WHERE 1=1";
    $params = [];

    if (!empty($department) && strtolower($department) !== 'all') {
        $sql .= " AND LOWER(department) = LOWER(?)";
        $params[] = $department;
    }

    if ($yearLevel > 0) {
        $sql .= " AND year_level = ?";
        $params[] = $yearLevel;
    }

    if (!empty($search)) {
        $sql .= " AND (LOWER(name) LIKE LOWER(?) OR LOWER(student_id) LIKE LOWER(?))";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $scholars = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $wantAbove = in_array(strtolower($status), ['', 'all', 'above', 'active'], true);
    $wantBelow = in_array(strtolower($status), ['below', 'removed'], true);

    // Standing (Active / Removed) from the imported grades — the same calculation the
    // Dashboard's "Active Scholars" card uses (includes/scholar_standing_helper.php).
    $result = [];
    foreach (computeScholarStandings($pdo, $scholars) as $s) {
        // The Dean's List is per semester: listed only while the active semester's GWA qualifies
        // (no grades yet for it, or not meeting the rule = not a Dean's Lister this semester).
        if (isDeansListType((string)$s['scholarshipType']) && !$s['deansLister']) continue;
        if ($wantAbove && !$s['maintainsGrade']) continue;
        if ($wantBelow && $s['maintainsGrade']) continue;
        $result[] = $s;
    }

    sendJson(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
