<?php

require_once __DIR__ . '/cssEditorShared.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$action = trim((string) ($input['action'] ?? 'generate'));

// ── Return presets ──
if ($action === 'presets') {
    echo json_encode(['success' => true, 'presets' => colorEnginePresets()]);
    exit;
}

// ── Generate or apply theme ──
$lightColors = $input['light'] ?? null;
$darkColors = $input['dark'] ?? null;

if (!is_array($lightColors) || !is_array($darkColors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Both light and dark color objects are required']);
    exit;
}

$requiredKeys = ['accent', 'canvas', 'text', 'border'];
foreach (['light' => $lightColors, 'dark' => $darkColors] as $theme => $colors) {
    foreach ($requiredKeys as $key) {
        $val = trim((string) ($colors[$key] ?? ''));
        if ($val === '' || !customCssEditorIsHexColor($val)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Invalid or missing {$theme}.{$key} color"]);
            exit;
        }
    }
}

$lightTokens = generateThemeFromBaseColors(
    $lightColors['accent'],
    $lightColors['canvas'],
    $lightColors['text'],
    $lightColors['border'],
    false
);

$darkTokens = generateThemeFromBaseColors(
    $darkColors['accent'],
    $darkColors['canvas'],
    $darkColors['text'],
    $darkColors['border'],
    true
);

$autoFix = !empty($input['autoFix']);
if ($autoFix) {
    $lightTokens = colorEngineAutoFixAll($lightTokens);
    $darkTokens = colorEngineAutoFixAll($darkTokens);
}

$lightContrast = colorEngineCheckContrast($lightTokens);
$darkContrast = colorEngineCheckContrast($darkTokens);

$response = [
    'success' => true,
    'light' => ['tokens' => $lightTokens, 'contrast' => $lightContrast],
    'dark' => ['tokens' => $darkTokens, 'contrast' => $darkContrast],
];

// ── If action is "apply", write CSS files ──
if ($action === 'apply') {
    // Allow unauthenticated if no users exist (fresh install)
    $allowUnauth = false;
    try {
        $chkPdo = connectToDatabase();
        $chkStmt = $chkPdo->query("SELECT COUNT(*) FROM users");
        $allowUnauth = ((int) $chkStmt->fetchColumn()) === 0;
        closeConnection($chkPdo);
    } catch (Exception $e) {
        $allowUnauth = true;
    }
    if (!$allowUnauth && (!function_exists('checkSuperAdmin') || !checkSuperAdmin())) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Only superadmin can apply themes']);
        exit;
    }

    // Auto-backup before applying theme changes (skip on fresh install)
    if (!$allowUnauth) {
        require_once __DIR__ . '/../../config/backupService.php';
        backupRunManual('setup-wizard', 'full');
    }

    $schema = customCssEditorTokenSchema();

    foreach (['light' => $lightTokens, 'dark' => $darkTokens] as $theme => $tokenValues) {
        $entries = [];
        foreach ($schema as $token) {
            $name = $token['name'] ?? '';
            if ($name === '') continue;
            $entries[] = [
                'name' => $name,
                'legacy' => $token['legacy'] ?? '',
                'category' => customCssEditorResolveCategory($name),
                'label' => $token['label'] ?? $name,
                'type' => $token['type'] ?? 'text',
                'editable' => $token['editable'] ?? true,
                'derivedFrom' => $token['derivedFrom'] ?? '',
                'value' => $tokenValues[$name] ?? '',
            ];
        }

        $entries = customCssEditorApplyDerivedValues($entries);
        $css = customCssEditorRenderCss($entries);

        $sourcePath = __DIR__ . '/../../custom/custom/css/custom-' . $theme . '.css';
        $publicPath = __DIR__ . '/../../public/custom/css/custom-' . $theme . '.css';

        if (!customCssEditorWriteFiles([$sourcePath, $publicPath], $css)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "Failed to write {$theme} CSS"]);
            exit;
        }
    }

    // Update sync state so secure.php won't trigger expensive re-sync
    customSyncUpdateState();

    $response['applied'] = true;
}

echo json_encode($response, JSON_UNESCAPED_SLASHES);
