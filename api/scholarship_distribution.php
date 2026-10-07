<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/scholarship_distribution.php';

header('Cache-Control: no-store');

try {
    $pdo = getDB();
    $sy = trim((string)($_GET['sy'] ?? ''));
    sendJson(['success' => true] + getScholarshipDistribution($pdo, ($sy === '' || $sy === 'all') ? null : $sy));
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
