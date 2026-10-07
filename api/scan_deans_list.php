<?php
/*
 * "Scan Registrar Now" (Scholars page) looks through the Registrar's database for:
 *   1. students holding an active scholarship program (includes/registrar_scholars_helper.php) — added to
 *      Scholars under their program with an approved record (answer: "scholarships" => [...]), then
 *   2. Dean's Listers among every officially enrolled student (includes/deans_list_helper.php).
 * Answers with what it found (Dean's List part):
 *   added          new Dean's Listers added to Scholars (addedNames: up to 10 of them)
 *   qualified      every student who meets the Dean's List rule right now
 *   alreadyListed  of those, already on the Scholars list
 *   inTrash        of those, deleted from Scholars (still in the Trash Bin, so not added back)
 *   recordsAdded   approved "Dean's List" Records created for them (Records is where the list is exported)
 *   recordsUpdated their existing Record for this school year moved on to the active semester
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/deans_list_helper.php';
require_once __DIR__ . '/../includes/registrar_scholars_helper.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        sendError('Method not allowed.', 405);
    }
    $pdo = getDB();
    // Scholarship holders first, so a Dean's Lister who holds a scholarship is listed under it.
    $scholarships = syncRegistrarScholars($pdo);
    syncRegistrarDeansListers($pdo, $report);

    if (!$report['reachable']) {
        sendError('Could not connect to the Registrar\'s database (' . REGISTRAR_DB_HOST . ':' . REGISTRAR_DB_PORT . '/' . REGISTRAR_DB_NAME . '). Check config/registrar_database.php.', 502);
    }

    $n = $report['added'];
    $message = $n > 0
        ? "Found $n new Dean's Lister" . ($n === 1 ? '' : 's') . ' — added to Scholars.'
        : "No new Dean's Listers found.";
    sendJson(['success' => true, 'message' => $message, 'semester' => getActiveSemester($pdo), 'schoolYear' => getActiveSchoolYear($pdo), 'scholarships' => $scholarships] + $report);
} catch (Throwable $e) {
    sendError($e->getMessage(), 500);
}
