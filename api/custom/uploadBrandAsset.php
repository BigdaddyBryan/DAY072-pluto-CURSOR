<?php

if (!function_exists('uploadBrandAssetRespond')) {
    function uploadBrandAssetRespond($message, $statusCode = 400, $extra = [])
    {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $statusCode >= 200 && $statusCode < 300,
            'message' => (string) $message,
        ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('checkSuperAdmin') || !checkSuperAdmin()) {
    // Allow upload during fresh install (no users in DB)
    $allowFreshInstall = false;
    try {
        $chkPdo = connectToDatabase();
        $chkStmt = $chkPdo->query("SELECT COUNT(*) FROM users");
        $allowFreshInstall = ((int) $chkStmt->fetchColumn()) === 0;
        closeConnection($chkPdo);
    } catch (Exception $e) {
        $allowFreshInstall = true;
    }
    if (!$allowFreshInstall) {
        uploadBrandAssetRespond('Forbidden', 403);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    uploadBrandAssetRespond('Method Not Allowed', 405);
}

$type = strtolower(trim((string) ($_GET['type'] ?? ($_POST['type'] ?? ''))));
$theme = strtolower(trim((string) ($_GET['theme'] ?? ($_POST['theme'] ?? ''))));

if (!in_array($type, ['logo', 'favicon'], true)) {
    uploadBrandAssetRespond('Invalid asset type.', 400);
}

if (!in_array($theme, ['light', 'dark'], true)) {
    uploadBrandAssetRespond('Invalid theme.', 400);
}

if (!isset($_FILES['assetFile']) || ($_FILES['assetFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    uploadBrandAssetRespond('No file uploaded or upload error.', 400);
}

$fileName = strtolower((string) ($_FILES['assetFile']['name'] ?? ''));
if (!str_ends_with($fileName, '.svg')) {
    uploadBrandAssetRespond('Please upload a valid SVG file.', 400);
}

$maxUploadBytes = 512 * 1024;
$fileSize = (int) ($_FILES['assetFile']['size'] ?? 0);
if ($fileSize <= 0) {
    uploadBrandAssetRespond('Uploaded SVG file is empty.', 400);
}
if ($fileSize > $maxUploadBytes) {
    uploadBrandAssetRespond('SVG file is too large.', 413);
}

$tmpPath = (string) ($_FILES['assetFile']['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    uploadBrandAssetRespond('Invalid uploaded file.', 400);
}

$uploadedContent = (string) @file_get_contents($tmpPath);
if ($uploadedContent === '') {
    uploadBrandAssetRespond('Uploaded SVG file is empty.', 400);
}

if (stripos($uploadedContent, '<svg') === false) {
    uploadBrandAssetRespond('Invalid SVG file.', 400);
}

$relativeDir = $type === 'logo' ? '/images/logo' : '/images/icons';
$targetBaseName = $type === 'logo' ? 'logo-' . $theme : 'favicon-' . $theme;
$relativePath = $relativeDir . '/' . $targetBaseName . '.svg';

$sourcePath = __DIR__ . '/../../custom/custom' . $relativePath;
$publicPath = __DIR__ . '/../../public/custom' . $relativePath;
$targetPaths = [$sourcePath, $publicPath];

if ($type === 'favicon' && $theme === 'light') {
    $targetPaths[] = __DIR__ . '/../../custom/custom/images/icons/favicon.svg';
    $targetPaths[] = __DIR__ . '/../../public/custom/images/icons/favicon.svg';
}

foreach ($targetPaths as $targetPath) {
    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        uploadBrandAssetRespond('Failed to create asset directory.', 500);
    }

    if (file_put_contents($targetPath, $uploadedContent, LOCK_EX) === false) {
        uploadBrandAssetRespond('Failed to save asset file.', 500);
    }
}

require_once __DIR__ . '/cssEditorShared.php';
customSyncUpdateState();

$assetLabel = $type === 'logo' ? 'logo' : 'favicon';
$themeLabel = $theme === 'dark' ? 'dark' : 'light';

uploadBrandAssetRespond(
    ucfirst($themeLabel) . ' ' . $assetLabel . ' updated successfully.',
    200,
    [
        'type' => $type,
        'theme' => $theme,
    ]
);
