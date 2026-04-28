<?php

require_once __DIR__ . '/cssEditorShared.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('resetProjectRespond')) {
    function resetProjectRespond($success, $message, $statusCode = 200, $extra = [])
    {
        http_response_code((int) $statusCode);
        echo json_encode(array_merge([
            'success' => (bool) $success,
            'message' => (string) $message,
        ], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('resetProjectResolveDbPaths')) {
    function resetProjectResolveDbPaths()
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

if (!function_exists('resetProjectCreateFreshDatabaseFile')) {
    function resetProjectCreateFreshDatabaseFile($sqlPath)
    {
        $tempDbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-reset-' . bin2hex(random_bytes(8)) . '.db';

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            throw new Exception('Unable to read schema file');
        }

        $tempPdo = new PDO('sqlite:' . $tempDbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $tempPdo->exec($sql);

        $quickCheck = $tempPdo->query('PRAGMA quick_check');
        $quickCheckResult = $quickCheck ? (string) $quickCheck->fetchColumn() : '';
        if (strtolower(trim($quickCheckResult)) !== 'ok') {
            throw new Exception('Fresh database integrity check failed');
        }

        $tempPdo = null;
        return $tempDbPath;
    }
}

if (!function_exists('resetProjectDeleteMigrationStatusFiles')) {
    function resetProjectDeleteMigrationStatusFiles()
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

if (!function_exists('resetProjectTrackFileBackup')) {
    function resetProjectTrackFileBackup($targetPath, &$restoreOps)
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
            $entry['backupPath'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-reset-file-' . bin2hex(random_bytes(8)) . '.bak';
            if (!@copy($targetPath, $entry['backupPath'])) {
                throw new Exception('Failed to backup file before reset: ' . $targetPath);
            }
        }

        $restoreOps[] = $entry;
    }
}

if (!function_exists('resetProjectRestoreFiles')) {
    function resetProjectRestoreFiles($restoreOps)
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
    resetProjectRespond(false, 'Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    resetProjectRespond(false, 'Invalid JSON input', 400);
}

$resetDatabase = !empty($input['resetDatabase']);
$resetColors = !empty($input['resetColors']);

if (!$resetDatabase && !$resetColors) {
    resetProjectRespond(false, 'Nothing to reset. Specify resetDatabase and/or resetColors.', 400);
}

if (!function_exists('checkSuperAdmin') || !checkSuperAdmin()) {
    resetProjectRespond(false, 'Only superadmin can reset the project', 403);
}

// Auto-backup before destructive reset.
require_once __DIR__ . '/../../config/backupService.php';
$backupResult = backupRunManual('setup-wizard', 'full');
if (($backupResult['status'] ?? '') === 'error') {
    resetProjectRespond(false, 'Backup failed before reset. Aborting reset.', 500, ['backup' => $backupResult]);
}

$results = [];
$dbRestoreOps = [];
$fileRestoreOps = [];
$tempDbPath = null;

try {
    // ── Reset Database ──
    if ($resetDatabase) {
        $sqlPath = __DIR__ . '/../sql/empty-sql.sql';
        if (!is_file($sqlPath)) {
            throw new Exception('Schema file not found');
        }

        $dbPaths = resetProjectResolveDbPaths();
        $tempDbPath = resetProjectCreateFreshDatabaseFile($sqlPath);

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
                $entry['backupPath'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-reset-db-' . bin2hex(random_bytes(8)) . '.bak';
                if (!@copy($targetPath, $entry['backupPath'])) {
                    throw new Exception('Failed to backup database before reset: ' . $targetPath);
                }
            }

            if (!@copy($tempDbPath, $targetPath)) {
                throw new Exception('Failed to write reset database: ' . $targetPath);
            }

            @unlink($targetPath . '-journal');
            @unlink($targetPath . '-wal');
            @unlink($targetPath . '-shm');

            $dbRestoreOps[] = $entry;
        }

        resetProjectDeleteMigrationStatusFiles();
        $results['database'] = 'reset';
    }

    // ── Reset Colors ──
    if ($resetColors) {
        $schema = customCssEditorTokenSchema();

        foreach (['light', 'dark'] as $theme) {
            $defaults = customCssEditorDefaultValues($theme);
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
                    'value' => $defaults[$name] ?? '',
                ];
            }

            $entries = customCssEditorApplyDerivedValues($entries);
            $css = customCssEditorRenderCss($entries);

            $sourcePath = __DIR__ . '/../../custom/custom/css/custom-' . $theme . '.css';
            $publicPath = __DIR__ . '/../../public/custom/css/custom-' . $theme . '.css';

            $paths = [$sourcePath, $publicPath];
            foreach ($paths as $path) {
                resetProjectTrackFileBackup($path, $fileRestoreOps);

                $dir = dirname($path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }

                if (@file_put_contents($path, $css, LOCK_EX) === false) {
                    throw new Exception("Failed to write {$theme} CSS defaults");
                }
            }
        }

        $results['colors'] = 'reset';
    }

    // Update sync state after successful writes.
    customSyncUpdateState();

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

    resetProjectRespond(true, 'Reset completed successfully', 200, ['results' => $results]);
} catch (Throwable $e) {
    // Rollback file writes.
    resetProjectRestoreFiles($fileRestoreOps);

    // Rollback DB replacements.
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

    resetProjectRespond(false, 'Reset failed: ' . $e->getMessage(), 500);
}
