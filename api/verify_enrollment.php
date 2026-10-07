<?php
/*
 * Verify CHED Enrollment List (Data Management).
 *   GET          -> past checks (newest first)
 *   POST file=…  -> check an uploaded CHED list against the Registrar's database
 *                   (mark=1, the default, also marks confirmed students enrolled in this system)
 * See includes/enrollment_verification_helper.php.
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../config/registrar_database.php';
require_once __DIR__ . '/../includes/enrollment_verification_helper.php';

try {
    $pdo = getDB();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $rows = $pdo->query("SELECT * FROM enrollment_verifications ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        sendJson(['success' => true, 'data' => array_map(fn($r) => [
            'id' => (int)$r['id'],
            'fileName' => $r['file_name'],
            'schoolYear' => $r['school_year'],
            'total' => (int)$r['total_rows'],
            'enrolled' => (int)$r['enrolled_count'],
            'notEnrolled' => (int)$r['not_enrolled_count'],
            'notFound' => (int)$r['not_found_count'],
            'review' => (int)$r['review_count'],
            'marked' => (int)$r['marked_count'],
            'checkedBy' => $r['checked_by'],
            'createdAt' => $r['created_at'],   // UTC
            'hasResultFile' => !empty($r['result_path']),
        ], $rows)]);
    }

    if (empty($_FILES['file']['name']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        sendError('Please choose the CHED list (Excel .xlsx or CSV).');
    }
    $fileName = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        sendError($ext === 'xls'
            ? 'Old .xls files can\'t be read. Open it in Excel and save it as .xlsx (Excel Workbook), then upload again.'
            : 'Please upload an .xlsx or .csv file.');
    }
    $mark = !isset($_POST['mark']) || $_POST['mark'] === '1';

    $rows = [];
    try {
        if ($ext === 'xlsx') {
            $rows = readXlsxRows($_FILES['file']['tmp_name']);
        } elseif (($h = fopen($_FILES['file']['tmp_name'], 'r')) !== false) {
            while (($r = fgetcsv($h, 8000, ',')) !== false) $rows[] = array_map(fn($v) => trim((string)$v), $r);
            fclose($h);
        }
    } catch (Throwable $e) {
        sendError('The file could not be read: ' . $e->getMessage());
    }
    if (count($rows) < 2) {
        sendError('The file has no student rows.');
    }

    try {
        $reg = getRegistrarDB();
    } catch (Throwable $e) {
        sendError('Could not connect to the Registrar\'s database (' . REGISTRAR_DB_HOST . ':' . REGISTRAR_DB_PORT . '/' . REGISTRAR_DB_NAME . '). Check config/registrar_database.php.', 502);
    }

    try {
        $out = verifyEnrollmentList($pdo, $reg, $rows, $fileName, $_SESSION['user_name'] ?? 'Registrar Staff', $mark);
    } catch (InvalidArgumentException $e) {
        sendError($e->getMessage());
    }

    $s = $out['summary'];
    logActivity($pdo, 'Enrollment List Verified', 'Data Management',
        "Checked \"$fileName\" against the Registrar's records: {$s['enrolled']} enrolled, {$s['not_enrolled']} not enrolled, {$s['not_found']} not found, {$s['review']} to check manually"
        . ($mark ? "; {$s['marked']} applicant record(s) marked enrolled." : '.'), $out['verificationId']);

    sendJson(['success' => true, 'marked' => $mark] + $out);
} catch (Throwable $e) {
    sendError($e->getMessage(), 500);
}
