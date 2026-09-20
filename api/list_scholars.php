<?php
require_once __DIR__ . '/init.php';

// Used only for scholars whose scholarship can't be found (e.g. entered by hand).
const SCHOLAR_DEFAULT_GWA_REQUIREMENT = 1.50;

try {
    $pdo = getDB();

    // Students who meet the MERIT-BASED Academic requirement are scholars automatically.
    syncMeritScholars($pdo);

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

    // GWA comes from the imported academic records, kept separately for each semester.
    $gradeStats = getSemesterGradeStats($pdo, array_column($scholars, 'student_id'));

    // The scholarship a scholar holds decides the GWA they must keep: the latest
    // evaluation record first, then their application.
    $recordType = $pdo->prepare("SELECT scholarship_type FROM records WHERE student_id = ? AND LOWER(TRIM(status)) = 'approved' ORDER BY id DESC LIMIT 1");
    $applicantType = $pdo->prepare("SELECT scholarship_type FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");

    // Newest graded semester first: that is the standing the scholar is judged on.
    $semesterOrder = ['Summer Term', '2nd Semester', '1st Semester'];

    $wantAbove = in_array(strtolower($status), ['', 'all', 'above', 'active'], true);
    $wantBelow = in_array(strtolower($status), ['below', 'removed'], true);

    $result = [];
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
        $required = $type !== '' ? resolveGwaRequirement($pdo, $type) : SCHOLAR_DEFAULT_GWA_REQUIREMENT;

        $standingSemester = null;
        foreach ($semesterOrder as $sem) {
            if (isset($stats[$sem])) { $standingSemester = $sem; break; }
        }
        // No imported grades yet: fall back to the GWA stored on the scholar.
        $gwa = $standingSemester ? $stats[$standingSemester]['gwa'] : (float)$s['gwa'];
        $maintains = $gwa <= $required;

        if ($wantAbove && !$maintains) continue;
        if ($wantBelow && $maintains) continue;

        $s['gwa'] = $gwa;
        $s['gwa_first'] = $stats['1st Semester']['gwa'] ?? null;
        $s['gwa_second'] = $stats['2nd Semester']['gwa'] ?? null;
        $s['gwa_summer'] = $stats['Summer Term']['gwa'] ?? null;
        $s['gwaSemester'] = $standingSemester;          // which semester `gwa` is for (null = stored figure)
        $s['scholarshipType'] = $type;
        $s['maintainsGrade'] = $maintains;
        $s['thresholdRequirement'] = $required;
        // Standing follows the grades: above their scholarship's requirement = Removed.
        $s['displayStatus'] = $maintains ? 'Active' : 'Removed';
        $result[] = $s;
    }

    sendJson(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
