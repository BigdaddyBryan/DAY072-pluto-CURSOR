<?php

function handleMultiSelect()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        include __DIR__ . '/../../pages/errors/404.php';
        return;
    }

    if (!checkAdmin()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $data = $payload['data'] ?? null;

    if (!$data || !isset($data['type']) || !isset($data['ids']) || !is_array($data['ids'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    switch ($data['type']) {
        case 'delete':
            include __DIR__ . '/deleteGroup.php';
            $deletedIds = [];
            foreach ($data['ids'] as $id) {
                $result = deleteGroup($id);
                if ($result['success']) {
                    $deletedIds[] = (int) $id;
                }
            }
            echo json_encode(['status' => 'success', 'deletedIds' => $deletedIds]);
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unsupported action for groups']);
            break;
    }
}
