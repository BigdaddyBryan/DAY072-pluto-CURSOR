<?php

function jsonResponse($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function requireLoggedInUserIdForSessions()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (string) ($_SESSION['user']['id'] ?? '');
    if ($userId === '' || $userId === 'tempUser') {
        jsonResponse(['success' => false, 'message' => 'Session control unavailable for this account'], 403);
    }

    return $userId;
}

function resolveTargetUserIdForSessions($actorUserId, $rawTargetUserId)
{
    $targetUserId = trim((string) $rawTargetUserId);
    if ($targetUserId === '') {
        return (string) $actorUserId;
    }

    if ($targetUserId === (string) $actorUserId) {
        return (string) $actorUserId;
    }

    $role = (string) ($_SESSION['user']['role'] ?? '');
    $isSuperAdmin = $role === 'superadmin';
    if (!$isSuperAdmin) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    return $targetUserId;
}

function getDeviceSessions()
{
    $actorUserId = requireLoggedInUserIdForSessions();
    $requestedUserId = $_GET['user_id'] ?? $_POST['user_id'] ?? '';
    $targetUserId = resolveTargetUserIdForSessions($actorUserId, $requestedUserId);

    $sessions = getUserDeviceSessions($targetUserId);
    $currentSessionId = getCurrentDeviceSessionId();

    foreach ($sessions as &$sessionData) {
        $sessionData['is_current'] = $targetUserId === (string) $actorUserId && $currentSessionId !== '' && $sessionData['session_id'] === $currentSessionId;
    }
    unset($sessionData);

    jsonResponse(['success' => true, 'sessions' => $sessions, 'user_id' => $targetUserId]);
}

function revokeDeviceSession()
{
    $actorUserId = requireLoggedInUserIdForSessions();

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $targetUserId = resolveTargetUserIdForSessions($actorUserId, $raw['user_id'] ?? ($_POST['user_id'] ?? ''));

    $sessionId = trim((string) ($raw['session_id'] ?? ($_POST['session_id'] ?? '')));
    if ($sessionId === '') {
        jsonResponse(['success' => false, 'message' => 'session_id is required'], 422);
    }

    $revoked = revokeUserDeviceSessionById($targetUserId, $sessionId);
    if (!$revoked) {
        jsonResponse(['success' => false, 'message' => 'Session not found'], 404);
    }

    if ($targetUserId === (string) $actorUserId && $sessionId === getCurrentDeviceSessionId()) {
        revokeCurrentDeviceSession();
        session_unset();
        setcookie('device_session', '', time() - 3600, '/', '', false, true);
        jsonResponse(['success' => true, 'message' => 'Current session revoked', 'logout' => true]);
    }

    jsonResponse(['success' => true, 'message' => 'Session revoked']);
}

function revokeOtherDeviceSessions()
{
    $userId = requireLoggedInUserIdForSessions();

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $count = revokeOtherUserDeviceSessions($userId, getCurrentDeviceSessionId());
    jsonResponse(['success' => true, 'message' => $count . ' device session(s) revoked', 'count' => $count]);
}

function revokeAllDeviceSessionsForUser()
{
    $actorUserId = requireLoggedInUserIdForSessions();

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $targetUserId = trim((string) ($raw['user_id'] ?? $actorUserId));
    if ($targetUserId === '') {
        jsonResponse(['success' => false, 'message' => 'user_id is required'], 422);
    }

    $role = (string) ($_SESSION['user']['role'] ?? '');
    $isSuperAdmin = $role === 'superadmin';

    if ($targetUserId !== $actorUserId && !$isSuperAdmin) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $count = revokeAllUserDeviceSessions($targetUserId);

    if ($targetUserId === $actorUserId) {
        revokeCurrentDeviceSession();
        session_unset();
        setcookie('device_session', '', time() - 3600, '/', '', false, true);
        jsonResponse([
            'success' => true,
            'message' => $count . ' device session(s) revoked',
            'count' => $count,
            'logout' => true,
        ]);
    }

    jsonResponse([
        'success' => true,
        'message' => $count . ' device session(s) revoked',
        'count' => $count,
        'logout' => false,
    ]);
}
