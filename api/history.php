<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/settings_helper.php';

checkAuth();

if (getSetting(getDB(), 'history_enabled', '1') !== '1') {
    sendError('The History log is currently disabled in Settings.', 403);
}

try {
    $pdo = getDB();

    $search = trim($_GET['search'] ?? '');
    $date = trim($_GET['date'] ?? '');
    $action = trim($_GET['action'] ?? '');
    $module = trim($_GET['module'] ?? '');
    $user = trim($_GET['user'] ?? '');

    $where = "WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $where .= " AND (description LIKE ? OR user_name LIKE ? OR action LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($date !== '') {
        $where .= " AND DATE(created_at) = ?";
        $params[] = $date;
    }

    if ($action !== '' && strtolower($action) !== 'all') {
        $where .= " AND action = ?";
        $params[] = $action;
    }

    if ($module !== '' && strtolower($module) !== 'all') {
        $where .= " AND module = ?";
        $params[] = $module;
    }

    if ($user !== '' && strtolower($user) !== 'all') {
        $where .= " AND user_name = ?";
        $params[] = $user;
    }

    /* =========================================================
       CSV EXPORT
       Streams every log matching the current filters (no
       pagination) as a CSV download instead of JSON.
    ========================================================= */
    if (($_GET['export'] ?? '') === 'csv') {
        $stmt = $pdo->prepare("SELECT * FROM activity_logs $where ORDER BY created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity-history-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date & Time', 'User', 'Module', 'Action', 'Description', 'Record ID']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['created_at'], $r['user_name'], $r['module'], $r['action'], $r['description'], $r['record_id']]);
        }
        fclose($out);
        exit();
    }

    /* =========================================================
       PAGINATION
    ========================================================= */
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs $where");
    $countStmt->execute($params);
    $totalFiltered = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM activity_logs $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'userId' => (int)$r['user_id'],
            'userName' => $r['user_name'],
            'action' => $r['action'],
            'module' => $r['module'],
            'description' => $r['description'],
            'recordId' => $r['record_id'] !== null ? (int)$r['record_id'] : null,
            'createdAt' => $r['created_at'],
        ];
    }, $rows);

    /* =========================================================
       STATS
    ========================================================= */
    $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

    $todayCountStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = DATE('now')");
    $todayCountStmt->execute();
    $todayCount = (int)$todayCountStmt->fetchColumn();

    $mostActiveStmt = $pdo->query("
        SELECT user_name, COUNT(*) as cnt
        FROM activity_logs
        GROUP BY user_name
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $mostActiveRow = $mostActiveStmt->fetch(PDO::FETCH_ASSOC);

    /* =========================================================
       FILTER OPTIONS
       Distinct values so the frontend can populate the
       Action / Module / User dropdowns from real data.
    ========================================================= */
    $actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
    $modules = $pdo->query("SELECT DISTINCT module FROM activity_logs ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);
    $users = $pdo->query("SELECT DISTINCT user_name FROM activity_logs ORDER BY user_name ASC")->fetchAll(PDO::FETCH_COLUMN);

    sendJson([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalFiltered,
            'totalPages' => (int)ceil($totalFiltered / $limit),
        ],
        'stats' => [
            'total' => $totalCount,
            'today' => $todayCount,
            'mostActiveUser' => $mostActiveRow ? $mostActiveRow['user_name'] : null,
        ],
        'filters' => [
            'actions' => $actions,
            'modules' => $modules,
            'users' => $users,
        ],
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
