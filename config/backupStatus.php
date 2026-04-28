<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method Not Allowed',
    ]);
    exit;
}

if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Forbidden',
    ]);
    exit;
}

require_once __DIR__ . '/backupService.php';

echo json_encode([
    'status' => 'success',
    'data' => backupGetAdminStatus(),
]);
