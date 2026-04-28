<?php
require_once __DIR__ . '/cssEditorShared.php';

if (!function_exists('customCssUploadThemeRespond')) {
    function customCssUploadThemeRespond($message, $statusCode = 400, $extra = [])
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

if (!checkAdmin()) {
    customCssUploadThemeRespond('Forbidden', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customCssUploadThemeRespond('Method Not Allowed', 405);
}

$theme = strtolower(trim((string) ($_GET['theme'] ?? ($_POST['theme'] ?? 'light'))));
if ($theme !== 'light' && $theme !== 'dark') {
    customCssUploadThemeRespond('Invalid theme.', 400);
}

if (!isset($_FILES['themeCss']) || $_FILES['themeCss']['error'] !== UPLOAD_ERR_OK) {
    customCssUploadThemeRespond('No CSS file uploaded or upload error.', 400);
}

$fileName = strtolower((string) ($_FILES['themeCss']['name'] ?? ''));
if (!str_ends_with($fileName, '.css')) {
    customCssUploadThemeRespond('Please upload a valid CSS file.', 400);
}

$maxUploadBytes = 512 * 1024;
if ((int) ($_FILES['themeCss']['size'] ?? 0) > $maxUploadBytes) {
    customCssUploadThemeRespond('CSS file is too large.', 413);
}

$tmpPath = (string) ($_FILES['themeCss']['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    customCssUploadThemeRespond('Invalid uploaded file.', 400);
}

$uploadedContent = (string) @file_get_contents($tmpPath);
if ($uploadedContent === '') {
    customCssUploadThemeRespond('Uploaded CSS file is empty.', 400);
}

$parsedVariables = customCssEditorParseVariables($uploadedContent);
if (empty($parsedVariables)) {
    customCssUploadThemeRespond('No CSS variables found in uploaded file.', 400);
}

$schema = customCssEditorTokenSchema();
$defaults = customCssEditorDefaultValues($theme);
$entries = customCssEditorBuildEntries($schema, [], $defaults);

$postData = [];
foreach ($entries as $entry) {
    $name = strtolower((string) ($entry['name'] ?? ''));
    $legacy = strtolower((string) ($entry['legacy'] ?? ''));

    if ($name !== '' && array_key_exists($name, $parsedVariables)) {
        $postData[(string) ($entry['name'] ?? '')] = (string) $parsedVariables[$name];
        continue;
    }

    if ($legacy !== '' && array_key_exists($legacy, $parsedVariables)) {
        $postData[(string) ($entry['name'] ?? '')] = (string) $parsedVariables[$legacy];
    }
}

$validationErrors = [];
$entries = customCssEditorApplyPostEntries($entries, $postData, $validationErrors);
if (!empty($validationErrors)) {
    customCssUploadThemeRespond(
        customCssEditorUiText('admin.css_validation_failed', 'Please fix invalid fields before saving.'),
        422,
        ['errors' => $validationErrors]
    );
}

$newCssContent = customCssEditorRenderCss($entries);
$sourceCssPath = __DIR__ . '/../../custom/custom/css/custom-' . $theme . '.css';
$publicCssPath = __DIR__ . '/../../public/custom/css/custom-' . $theme . '.css';

if (!customCssEditorWriteFiles([$sourceCssPath, $publicCssPath], $newCssContent)) {
    customCssUploadThemeRespond('Failed to save CSS file.', 500);
}

customSyncUpdateState();

customCssUploadThemeRespond(
    customCssEditorUiText('admin.css_uploaded_successfully', 'CSS uploaded successfully'),
    200,
    ['theme' => $theme]
);
