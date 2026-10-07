<?php
/*
 * The History page's filters as a SQL WHERE clause — shared by the list (api/history.php) and
 * "Delete Browsing Data" (api/delete_history.php), so deleting "everything matching the current
 * filters" removes exactly the entries the list shows.
 *
 * $src: the request values (search, date, action, module, user, tz). Returns [where, params].
 */
function historyWhereClause(array $src): array {
    $search = trim((string)($src['search'] ?? ''));
    $date = trim((string)($src['date'] ?? ''));
    $action = trim((string)($src['action'] ?? ''));
    $module = trim((string)($src['module'] ?? ''));
    $user = trim((string)($src['user'] ?? ''));

    // created_at is stored in UTC; the browser sends its offset (minutes east of UTC) so the
    // date filter follows the viewer's calendar day.
    $tzMinutes = max(-840, min(840, (int)($src['tz'] ?? 0)));
    $localDate = sprintf("DATE(created_at + INTERVAL %d MINUTE)", $tzMinutes);

    $where = "WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $where .= " AND (description LIKE ? OR user_name LIKE ? OR action LIKE ?)";
        array_push($params, "%$search%", "%$search%", "%$search%");
    }
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $where .= " AND $localDate = ?";
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
    return [$where, $params];
}

// Whether any filter is set (deleting with no filter means deleting the whole History).
function historyHasFilters(array $src): bool {
    foreach (['search', 'date', 'action', 'module', 'user'] as $k) {
        $v = trim((string)($src[$k] ?? ''));
        if ($v !== '' && strtolower($v) !== 'all') return true;
    }
    return false;
}
