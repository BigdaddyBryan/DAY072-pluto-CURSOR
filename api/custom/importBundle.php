<?php

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('importBundleRespond')) {
    function importBundleRespond($success, $message, $statusCode = 200, $extra = [])
    {
        http_response_code((int) $statusCode);
        echo json_encode(array_merge([
            'success' => (bool) $success,
            'message' => (string) $message,
        ], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('importBundleResolveDbPaths')) {
    function importBundleResolveDbPaths()
    {
        $canonicalPath = __DIR__ . '/../../custom/database/database.db';
        $canonicalDir = dirname($canonicalPath);

        $canonicalWritable = false;
        if (is_dir($canonicalDir)) {
            $probeFile = $canonicalDir . DIRECTORY_SEPARATOR . '.db_write_test_' . getmypid();
            if (@file_put_contents($probeFile, '1') !== false) {
                @unlink($probeFile);
                $canonicalWritable = true;
            }
        }

        $activePath = $canonicalPath;
        if (!$canonicalWritable) {
            $projectHash = md5(realpath($canonicalPath) ?: $canonicalPath);
            $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-db-' . $projectHash;
            if (!is_dir($workDir)) {
                @mkdir($workDir, 0770, true);
            }
            $activePath = $workDir . DIRECTORY_SEPARATOR . 'database.db';
        }

        $targets = [$activePath];
        if ($canonicalWritable && $canonicalPath !== $activePath) {
            $targets[] = $canonicalPath;
        }

        return [
            'canonicalPath' => $canonicalPath,
            'activePath' => $activePath,
            'canonicalWritable' => $canonicalWritable,
            'targets' => array_values(array_unique($targets)),
        ];
    }
}

if (!function_exists('importBundleDeleteMigrationStatusFiles')) {
    function importBundleDeleteMigrationStatusFiles()
    {
        $paths = [
            __DIR__ . '/../../public/json/dbMigrationStatus.json',
            __DIR__ . '/../../custom/migration_state/dbMigrationStatus.json',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}

if (!function_exists('importBundleTrackFileBackup')) {
    function importBundleTrackFileBackup($targetPath, &$restoreOps)
    {
        foreach ($restoreOps as $op) {
            if (($op['targetPath'] ?? '') === $targetPath) {
                return;
            }
        }

        $entry = [
            'targetPath' => $targetPath,
            'existed' => is_file($targetPath),
            'backupPath' => null,
        ];

        if ($entry['existed']) {
            $entry['backupPath'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-import-file-' . bin2hex(random_bytes(8)) . '.bak';
            if (!@copy($targetPath, $entry['backupPath'])) {
                throw new Exception('Failed to backup file before import: ' . $targetPath);
            }
        }

        $restoreOps[] = $entry;
    }
}

if (!function_exists('importBundleRestoreFiles')) {
    function importBundleRestoreFiles($restoreOps)
    {
        foreach (array_reverse($restoreOps) as $op) {
            $targetPath = (string) ($op['targetPath'] ?? '');
            $existed = !empty($op['existed']);
            $backupPath = (string) ($op['backupPath'] ?? '');

            if ($targetPath === '') {
                continue;
            }

            if ($existed && $backupPath !== '' && is_file($backupPath)) {
                @copy($backupPath, $targetPath);
            }

            if (!$existed && is_file($targetPath)) {
                @unlink($targetPath);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    importBundleRespond(false, 'Method not allowed', 405);
}

// Allow import without auth when no users exist (fresh install)
$allowUnauthenticated = false;
try {
    $checkPdo = connectToDatabase();
    $checkStmt = $checkPdo->query("SELECT COUNT(*) FROM users");
    $userCount = (int) $checkStmt->fetchColumn();
    closeConnection($checkPdo);
    if ($userCount === 0) {
        $allowUnauthenticated = true;
    }
} catch (Exception $e) {
    $allowUnauthenticated = true;
}

if (!$allowUnauthenticated && (!function_exists('checkSuperAdmin') || !checkSuperAdmin())) {
    importBundleRespond(false, 'Only superadmin can import bundles', 401);
}

// Auto-backup before import overwrites (skip on fresh install)
if (!$allowUnauthenticated) {
    require_once __DIR__ . '/../../config/backupService.php';
    $backupResult = backupRunManual('setup-wizard', 'full');
    if (($backupResult['status'] ?? '') === 'error') {
        importBundleRespond(false, 'Backup failed before import. Aborting import.', 500, ['backup' => $backupResult]);
    }
}

if (empty($_FILES['bundle']) || $_FILES['bundle']['error'] !== UPLOAD_ERR_OK) {
    importBundleRespond(false, 'No file uploaded or upload error', 400);
}

$tmpFile = $_FILES['bundle']['tmp_name'];
$maxSize = 100 * 1024 * 1024; // 100MB limit
if (filesize($tmpFile) > $maxSize) {
    importBundleRespond(false, 'File too large (max 100MB)', 400);
}

$zip = new ZipArchive();
if ($zip->open($tmpFile) !== true) {
    importBundleRespond(false, 'Invalid ZIP file', 400);
}

// ── Validate manifest ──
$manifestJson = $zip->getFromName('manifest.json');
if ($manifestJson === false) {
    $zip->close();
    importBundleRespond(false, 'Missing manifest.json - not a valid deployment bundle', 400);
}

$manifest = json_decode($manifestJson, true);
if (!is_array($manifest) || ($manifest['type'] ?? '') !== 'deployment-bundle') {
    $zip->close();
    importBundleRespond(false, 'Invalid manifest - not a deployment bundle', 400);
}

// ── Security validation: no executable files ──
$blockedExtensions = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps'];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entryName = $zip->getNameIndex($i);
    if ($entryName === false) continue;

    // Block path traversal
    if (strpos($entryName, '..') !== false || strpos($entryName, '\\') !== false) {
        $zip->close();
        importBundleRespond(false, 'Path traversal detected in ZIP', 400);
    }

    // Block executable files (only check custom/ entries, database is binary)
    if (strpos($entryName, 'custom/') === 0) {
        $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
        if (in_array($ext, $blockedExtensions, true)) {
            $zip->close();
            importBundleRespond(false, 'Executable file detected in bundle: ' . $entryName, 400);
        }
    }
}

$basePath = __DIR__ . '/../../';
$results = [];
$dbRestoreOps = [];
$fileRestoreOps = [];
$tempDbPath = null;
$dbRestored = false;

$dbEntry = $zip->getFromName('database/database.db');
$ssoContent = $zip->getFromName('googleSSO.json');

$customEntries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entryName = $zip->getNameIndex($i);
    if ($entryName === false || strpos($entryName, 'custom/') !== 0) {
        continue;
    }

    if (substr($entryName, -1) === '/') {
        continue;
    }

    $relativePath = substr($entryName, strlen('custom/'));
    if ($relativePath === '' || $relativePath === false) {
        continue;
    }

    if (strpos($relativePath, 'sessions/') === 0) {
        continue;
    }

    $content = $zip->getFromIndex($i);
    if ($content === false) {
        continue;
    }

    $customEntries[$relativePath] = $content;
}

try {
    // ── Restore database ──
    if ($dbEntry !== false) {
        $tempDbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-import-' . bin2hex(random_bytes(8)) . '.db';
        if (@file_put_contents($tempDbPath, $dbEntry, LOCK_EX) === false) {
            throw new Exception('Failed to stage database file from bundle');
        }

        $verifyPdo = new PDO('sqlite:' . $tempDbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $quickCheckStmt = $verifyPdo->query('PRAGMA quick_check');
        $quickCheckResult = $quickCheckStmt ? (string) $quickCheckStmt->fetchColumn() : '';
        if (strtolower(trim($quickCheckResult)) !== 'ok') {
            throw new Exception('Imported database failed integrity check');
        }
        $verifyPdo = null;

        $dbPaths = importBundleResolveDbPaths();
        foreach (($dbPaths['targets'] ?? []) as $targetPath) {
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $entry = [
                'targetPath' => $targetPath,
                'existed' => is_file($targetPath),
                'backupPath' => null,
            ];

            if ($entry['existed']) {
                $entry['backupPath'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-import-db-' . bin2hex(random_bytes(8)) . '.bak';
                if (!@copy($targetPath, $entry['backupPath'])) {
                    throw new Exception('Failed to backup database before import: ' . $targetPath);
                }
            }

            if (!@copy($tempDbPath, $targetPath)) {
                throw new Exception('Failed to write imported database: ' . $targetPath);
            }

            @unlink($targetPath . '-journal');
            @unlink($targetPath . '-wal');
            @unlink($targetPath . '-shm');

            $dbRestoreOps[] = $entry;
        }

        importBundleDeleteMigrationStatusFiles();
        $results['database'] = 'restored';
        $dbRestored = true;
    }

    // ── Restore custom/ files (strict write with rollback map) ──
    if (!empty($customEntries)) {
        $customDir = $basePath . 'custom/custom';
        $publicCustomDir = $basePath . 'public/custom';

        foreach ($customEntries as $relativePath => $content) {
            $targetPaths = [
                $customDir . '/' . $relativePath,
                $publicCustomDir . '/' . $relativePath,
            ];

            foreach ($targetPaths as $targetPath) {
                importBundleTrackFileBackup($targetPath, $fileRestoreOps);

                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }

                if (@file_put_contents($targetPath, $content, LOCK_EX) === false) {
                    throw new Exception('Failed to write imported file: ' . $relativePath);
                }
            }
        }

        $results['custom_files'] = count($customEntries);
    }

    // ── Restore Google SSO config ──
    if ($ssoContent !== false) {
        $ssoPath = $basePath . 'custom/googleSSO.json';
        importBundleTrackFileBackup($ssoPath, $fileRestoreOps);

        $ssoDir = dirname($ssoPath);
        if (!is_dir($ssoDir)) {
            @mkdir($ssoDir, 0775, true);
        }

        if (@file_put_contents($ssoPath, $ssoContent, LOCK_EX) === false) {
            throw new Exception('Failed to restore Google SSO config');
        }

        $results['google_sso'] = 'restored';
    }

    $zip->close();

    // Update sync state after successful writes.
    require_once __DIR__ . '/cssEditorShared.php';
    customSyncUpdateState();

    if ($dbRestored) {
        if (function_exists('ensureDatabaseTables')) {
            ensureDatabaseTables();
        }
        if (function_exists('ensureDatabaseCompatibility')) {
            ensureDatabaseCompatibility(true);
        }
        $results['migrations'] = 'completed';
    }

    foreach ($dbRestoreOps as $op) {
        $backupPath = (string) ($op['backupPath'] ?? '');
        if ($backupPath !== '' && is_file($backupPath)) {
            @unlink($backupPath);
        }
    }
    foreach ($fileRestoreOps as $op) {
        $backupPath = (string) ($op['backupPath'] ?? '');
        if ($backupPath !== '' && is_file($backupPath)) {
            @unlink($backupPath);
        }
    }
    if (is_string($tempDbPath) && is_file($tempDbPath)) {
        @unlink($tempDbPath);
    }

    importBundleRespond(true, 'Bundle imported successfully', 200, [
        'manifest' => $manifest,
        'results' => $results,
    ]);
} catch (Throwable $e) {
    if ($zip instanceof ZipArchive) {
        @$zip->close();
    }

    importBundleRestoreFiles($fileRestoreOps);

    foreach (array_reverse($dbRestoreOps) as $op) {
        $targetPath = (string) ($op['targetPath'] ?? '');
        $existed = !empty($op['existed']);
        $backupPath = (string) ($op['backupPath'] ?? '');

        if ($targetPath === '') {
            continue;
        }

        if ($existed && $backupPath !== '' && is_file($backupPath)) {
            @copy($backupPath, $targetPath);
        }

        if (!$existed && is_file($targetPath)) {
            @unlink($targetPath);
        }
    }

    foreach ($dbRestoreOps as $op) {
        $backupPath = (string) ($op['backupPath'] ?? '');
        if ($backupPath !== '' && is_file($backupPath)) {
            @unlink($backupPath);
        }
    }
    foreach ($fileRestoreOps as $op) {
        $backupPath = (string) ($op['backupPath'] ?? '');
        if ($backupPath !== '' && is_file($backupPath)) {
            @unlink($backupPath);
        }
    }
    if (is_string($tempDbPath) && is_file($tempDbPath)) {
        @unlink($tempDbPath);
    }

    importBundleRespond(false, 'Bundle import failed: ' . $e->getMessage(), 500);
}
