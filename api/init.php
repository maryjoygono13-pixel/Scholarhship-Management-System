<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/name_helper.php';
require_once __DIR__ . '/../includes/gwa_helper.php';

header('Content-Type: application/json; charset=utf-8');

function sendJson($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function sendError(string $message, int $statusCode = 400): void
{
    sendJson(
        [
            'success' => false,
            'message' => $message
        ],
        $statusCode
    );
}

/*
 * All API endpoints that load this file require
 * an authenticated Registrar session.
 */
if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_role']) ||
    strtolower($_SESSION['user_role']) !== 'registrar'
) {
    sendJson(
        [
            'success' => false,
            'message' => 'Unauthorized.'
        ],
        401
    );
}
