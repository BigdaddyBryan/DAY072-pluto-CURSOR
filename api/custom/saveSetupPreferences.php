<?php

require_once __DIR__ . '/cssEditorShared.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('saveSetupPreferencesRespond')) {
    function saveSetupPreferencesRespond($success, $message, $statusCode = 200, $extra = [])
    {
        http_response_code((int) $statusCode);
        echo json_encode(array_merge([
            'success' => (bool) $success,
            'message' => (string) $message,
        ], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    saveSetupPreferencesRespond(false, 'Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    saveSetupPreferencesRespond(false, 'Invalid JSON input', 400);
}

$brandingMode = strtolower(trim((string) ($input['brandingMode'] ?? 'default')));
if ($brandingMode !== 'custom') {
    $brandingMode = 'default';
}

$selectedPreset = trim((string) ($input['selectedPreset'] ?? 'custom'));
if ($selectedPreset === '') {
    $selectedPreset = 'custom';
}

$allowUnauthenticated = false;
try {
    $checkPdo = connectToDatabase();
    $checkStmt = $checkPdo->query('SELECT COUNT(*) FROM users');
    $allowUnauthenticated = ((int) $checkStmt->fetchColumn()) === 0;
    closeConnection($checkPdo);
} catch (Exception $e) {
    $allowUnauthenticated = true;
}

if (!$allowUnauthenticated && (!function_exists('checkSuperAdmin') || !checkSuperAdmin())) {
    saveSetupPreferencesRespond(false, 'Only superadmin can save setup preferences', 403);
}

$payload = [
    'brandingMode' => $brandingMode,
    'selectedPreset' => $selectedPreset,
    'updatedAt' => gmdate('c'),
];

if (isset($_SESSION['user']['email'])) {
    $payload['updatedBy'] = (string) $_SESSION['user']['email'];
}

$jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($jsonPayload)) {
    saveSetupPreferencesRespond(false, 'Failed to encode preferences', 500);
}

$targets = [
    __DIR__ . '/../../custom/custom/json/setup-wizard-preferences.json',
    __DIR__ . '/../../public/custom/json/setup-wizard-preferences.json',
];

foreach ($targets as $targetPath) {
    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        saveSetupPreferencesRespond(false, 'Failed to create preferences directory', 500);
    }

    if (file_put_contents($targetPath, $jsonPayload . PHP_EOL, LOCK_EX) === false) {
        saveSetupPreferencesRespond(false, 'Failed to save setup preferences', 500);
    }
}

customSyncUpdateState();

saveSetupPreferencesRespond(true, 'Setup preferences saved', 200, [
    'brandingMode' => $brandingMode,
    'selectedPreset' => $selectedPreset,
]);
