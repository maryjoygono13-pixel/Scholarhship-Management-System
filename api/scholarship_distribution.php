<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/scholarship_distribution.php';

header('Cache-Control: no-store');

try {
    sendJson(['success' => true] + getScholarshipDistribution(getDB()));
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
