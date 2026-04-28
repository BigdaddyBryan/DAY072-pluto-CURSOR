<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/backupService.php';

$input = json_decode((string) file_get_contents('php://input'), true);
$snapshotId = is_array($input) ? trim((string) ($input['snapshotId'] ?? '')) : '';

if ($snapshotId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing snapshot ID.']);
    exit;
}

$result = backupDeleteSnapshot($snapshotId);

if ($result['status'] !== 'success') {
    http_response_code(400);
}

echo json_encode($result);
