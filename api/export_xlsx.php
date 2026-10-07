<?php
/*
 * Turns a page's export (Scholars, Records, Renewal & Retention) into a real Excel file, all with the
 * same look: centered cells and header, columns sized to their contents, Student IDs kept as text (no
 * "2E+07"), and the number columns given (GWAs) as numbers with 2 decimals.
 *
 * POST JSON: { "filename": "scholars-2026-10-03", "sheet": "Scholars",
 *              "rows": [[header...], [cells...], ...], "numberCols": [5, 6] }
 * Answers with the .xlsx file (assets/js/export-xlsx.js downloads it).
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/xlsx_helper.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        sendError('Method not allowed.', 405);
    }
    $in = json_decode((string)file_get_contents('php://input'), true);
    $rows = is_array($in['rows'] ?? null) ? array_values($in['rows']) : [];
    if (count($rows) < 2) sendError('There is nothing to export.');
    if (count($rows) > 50001) sendError('Too many rows to export at once (50,000 max).');

    $width = count((array)$rows[0]);
    $clean = [];
    foreach ($rows as $row) {
        $row = array_slice(array_values(is_array($row) ? $row : []), 0, $width);
        $clean[] = array_map(fn($v) => is_scalar($v) || $v === null ? (string)$v : '', array_pad($row, $width, ''));
    }

    // Columns sized to their longest value (within reason).
    $widths = [];
    for ($c = 0; $c < $width; $c++) {
        $longest = 0;
        foreach ($clean as $row) $longest = max($longest, mb_strlen($row[$c]));
        $widths[] = max(10, min(60, $longest + 4));
    }

    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)($in['filename'] ?? 'export')) ?: 'export';
    $name = preg_replace('/\.(xlsx|csv)$/i', '', $name) . '.xlsx';
    $sheet = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', (string)($in['sheet'] ?? 'Export')), 0, 31) ?: 'Export';

    $tmp = tempnam(sys_get_temp_dir(), 'sms_export_');
    writeXlsx($tmp, $clean, $sheet, $widths, ['center' => true, 'numberCols' => (array)($in['numberCols'] ?? [])]);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit();
} catch (Throwable $e) {
    sendError($e->getMessage(), 500);
}
