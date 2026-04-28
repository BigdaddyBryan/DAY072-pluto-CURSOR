<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/users/users.php';
require_once __DIR__ . '/../../api/groups/deleteGroup.php';

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate 'id' input
if (!isset($input['id']) || !filter_var($input['id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'error' => 'Invalid group ID.']);
    exit;
}

$groupId = (int) $input['id'];

try {
    $result = deleteGroup($groupId);
    if (!is_array($result)) {
        echo json_encode(['success' => false, 'error' => 'Delete request failed.']);
        exit;
    }

    if (!empty($result['success'])) {
        echo json_encode(['success' => true, 'message' => (string) ($result['message'] ?? 'Group deleted successfully.')]);
    } else {
        echo json_encode(['success' => false, 'error' => (string) ($result['message'] ?? 'Failed to delete the group.')]);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
}
