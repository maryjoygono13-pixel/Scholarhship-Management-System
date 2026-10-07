<?php
/*
 * Deletes History (activity log) entries ("Delete Browsing Data" and each row's trash icon).
 *   POST ids[]=1&ids[]=2…                     -> those entries
 *   POST scope=filtered + search/date/action/module/tz
 *                                             -> every entry matching the current filters
 *                                                (all entries when no filter is set)
 * The deletion itself is recorded as a new History entry, so the log still shows that entries
 * were removed, when, and by whom.
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/history_filters.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        sendError('Method not allowed.', 405);
    }
    $pdo = getDB();

    if (($_POST['scope'] ?? '') === 'filtered') {
        [$where, $params] = historyWhereClause($_POST);
        $stmt = $pdo->prepare("DELETE FROM activity_logs $where");
        $stmt->execute($params);
        $deleted = $stmt->rowCount();
        $what = historyHasFilters($_POST) ? 'matching the filters' : 'all history';
    } else {
        $raw = $_POST['ids'] ?? [];
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', (string)$raw)), fn($v) => $v > 0)));
        if (!$ids) {
            sendError('No history entries were selected.');
        }
        if (count($ids) > 1000) {
            sendError('Please delete at most 1,000 entries at a time.');
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id IN ($in)");
        $stmt->execute($ids);
        $deleted = $stmt->rowCount();
        $what = 'selected';
    }

    if ($deleted > 0) {
        logActivity($pdo, 'History Deleted', 'History', $deleted . ' history entr' . ($deleted === 1 ? 'y was' : 'ies were') . " deleted ($what).");
    }
    sendJson(['success' => true, 'deleted' => $deleted, 'message' => $deleted . ' history entr' . ($deleted === 1 ? 'y' : 'ies') . ' deleted.']);
} catch (Throwable $e) {
    sendError($e->getMessage(), 500);
}
