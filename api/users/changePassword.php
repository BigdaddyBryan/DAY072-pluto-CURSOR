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

function changeProfilePassword()
{
    checkUser();

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $currentPassword = (string) ($raw['currentPassword'] ?? '');
    $newPassword = (string) ($raw['newPassword'] ?? '');
    $confirmPassword = (string) ($raw['confirmPassword'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        jsonResponse(['success' => false, 'message' => 'All password fields are required'], 422);
    }

    if ($newPassword !== $confirmPassword) {
        jsonResponse(['success' => false, 'message' => 'New passwords do not match'], 422);
    }

    if (strlen($newPassword) < 8) {
        jsonResponse(['success' => false, 'message' => 'New password must be at least 8 characters'], 422);
    }

    $pdo = connectToDatabase();
    $sql = 'SELECT password FROM users WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $_SESSION['user']['id']]);
    $storedPasswordHash = (string) $stmt->fetchColumn();

    if ($storedPasswordHash === '' || !password_verify($currentPassword, $storedPasswordHash)) {
        closeConnection($pdo);
        jsonResponse(['success' => false, 'message' => 'Current password is incorrect'], 401);
    }

    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateSql = 'UPDATE users SET password = :password, modifier = :modifier, modified_at = :modified_at WHERE id = :id';
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        'password' => $newPasswordHash,
        'modifier' => $_SESSION['user']['id'],
        'modified_at' => date('Y-m-d H:i:s'),
        'id' => $_SESSION['user']['id'],
    ]);
    closeConnection($pdo);

    $_SESSION['user']['password'] = $newPasswordHash;

    jsonResponse(['success' => true, 'message' => 'Password updated successfully']);
}
