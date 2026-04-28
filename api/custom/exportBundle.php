<?php

if (!function_exists('checkSuperAdmin') || !checkSuperAdmin()) {
    http_response_code(401);
    echo 'Only superadmin can export bundles';
    exit;
}

$dbPath = __DIR__ . '/../../custom/database/database.db';
$customDir = __DIR__ . '/../../custom/custom';
$versionPath = __DIR__ . '/../../public/json/version.json';

$tmpDir = sys_get_temp_dir();
$zipName = 'deployment-bundle-' . date('Y-m-d-His') . '.zip';
$zipPath = $tmpDir . '/' . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not create ZIP archive']);
    exit;
}

// ── Add manifest.json ──
$versionData = is_file($versionPath) ? json_decode((string) file_get_contents($versionPath), true) : [];
$manifest = [
    'type' => 'deployment-bundle',
    'version' => $versionData['version'] ?? 'unknown',
    'exported_at' => date('c'),
    'source_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'schema_version' => function_exists('migrationSchemaVersion') ? migrationSchemaVersion() : 0,
];
$zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// ── Add database ──
if (is_file($dbPath)) {
    $zip->addFile($dbPath, 'database/database.db');
}

// ── Add custom/ folder recursively ──
if (is_dir($customDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($customDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $filePath = str_replace('\\', '/', $file->getRealPath());
        $basePath = str_replace('\\', '/', $customDir);
        $relativePath = 'custom/' . substr($filePath, strlen($basePath) + 1);

        // Skip session files
        if (strpos($relativePath, 'custom/sessions/') === 0) {
            continue;
        }

        $zip->addFile($file->getRealPath(), $relativePath);
    }
}

// ── Add Google SSO config ──
$ssoPath = __DIR__ . '/../../custom/googleSSO.json';
if (is_file($ssoPath)) {
    $zip->addFile($ssoPath, 'googleSSO.json');
}

$zip->close();

// ── Stream download ──
if (!is_file($zipPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ZIP file was not created']);
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache, no-store, must-revalidate');

readfile($zipPath);
@unlink($zipPath);
exit;
