<?php

if (!function_exists('jsonResponse')) {
    function jsonResponse($payload, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

function saveProfilePreferences()
{
    checkUser();

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $allowedLimits = [10, 20, 50, 100];
    $limit = isset($raw['limit']) ? (int) $raw['limit'] : (int) ($_SESSION['user']['limit'] ?? 10);
    if (!in_array($limit, $allowedLimits, true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid limit value'], 422);
    }

    $compactView = (bool) ($raw['compactView'] ?? false);
    $viewValue = $compactView ? 'view' : 'basic';

    $pdo = connectToDatabase();
    $sql = 'UPDATE users SET view = :view, `limit` = :limit, modifier = :modifier, modified_at = :modified_at WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'view' => $viewValue,
        'limit' => $limit,
        'modifier' => $_SESSION['user']['id'],
        'modified_at' => date('Y-m-d H:i:s'),
        'id' => $_SESSION['user']['id'],
    ]);
    closeConnection($pdo);

    $_SESSION['user']['view'] = $viewValue;
    $_SESSION['user']['limit'] = $limit;

    jsonResponse([
        'success' => true,
        'message' => 'Profile preferences updated',
        'view' => $viewValue,
        'limit' => $limit,
    ]);
}
