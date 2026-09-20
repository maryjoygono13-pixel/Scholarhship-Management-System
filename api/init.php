<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/name_helper.php';
require_once __DIR__ . '/../includes/gwa_helper.php';
require_once __DIR__ . '/../includes/term_helper.php';
require_once __DIR__ . '/../includes/grades_helper.php';
require_once __DIR__ . '/../includes/renewal_helper.php';
require_once __DIR__ . '/../includes/programs_helper.php';
require_once __DIR__ . '/../includes/merit_helper.php';

header('Content-Type: application/json; charset=utf-8');

function sendJson($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function sendError(string $message, int $statusCode = 400): void {
    sendJson(['success' => false, 'message' => $message], $statusCode);
}
