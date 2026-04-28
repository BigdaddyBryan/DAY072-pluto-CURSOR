<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$requestedBy = isset($_SESSION['user']['email'])
    ? (string) $_SESSION['user']['email']
    : 'admin';

$result = backupRunManual($requestedBy);

if (($result['status'] ?? '') === 'locked') {
    http_response_code(409);
} elseif (($result['status'] ?? '') === 'error') {
    http_response_code(500);
}

echo json_encode($result);
