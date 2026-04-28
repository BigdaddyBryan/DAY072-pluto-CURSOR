<?php
if (!checkAdmin()) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/cssEditorShared.php';

$theme = strtolower(trim((string) ($_GET['theme'] ?? 'light')));
if ($theme !== 'light' && $theme !== 'dark') {
    http_response_code(400);
    echo 'Invalid theme.';
    exit;
}

$sourceCssPath = __DIR__ . '/../../custom/custom/css/custom-' . $theme . '.css';
$publicCssPath = __DIR__ . '/../../public/custom/css/custom-' . $theme . '.css';
$cssContent = customCssEditorReadContent($sourceCssPath, $publicCssPath);

if (trim($cssContent) === '') {
    $schema = customCssEditorTokenSchema();
    $defaults = customCssEditorDefaultValues($theme);
    $cssContent = customCssEditorRenderCss(customCssEditorBuildEntries($schema, [], $defaults));
}

$fileName = 'custom-' . $theme . '.css';

header('Content-Type: text/css; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $fileName);
header('Content-Length: ' . strlen($cssContent));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $cssContent;
exit;
