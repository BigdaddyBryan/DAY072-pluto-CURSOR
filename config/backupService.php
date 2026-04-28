<?php

if (!function_exists('backupGetConfig')) {
    function backupGetConfig()
    {
        return [
            'database_file' => __DIR__ . '/../custom/database/database.db',
            'custom_source_dir' => __DIR__ . '/../public/custom',
            'backup_root' => __DIR__ . '/../custom/backup',
            'snapshots_dir' => __DIR__ . '/../custom/backup/snapshots',
            'tmp_dir' => __DIR__ . '/../custom/backup/.tmp',
            'state_file' => __DIR__ . '/../public/json/backup.json',
            'log_file' => __DIR__ . '/../custom/backup/backup.log',
            'lock_file' => __DIR__ . '/../custom/backup/backup.lock',
            'db_schedule_seconds' => defined('APP_BACKUP_DB_SCHEDULE_SECONDS') ? (int) APP_BACKUP_DB_SCHEDULE_SECONDS : 86400,
            'full_schedule_seconds' => defined('APP_BACKUP_FULL_SCHEDULE_SECONDS') ? (int) APP_BACKUP_FULL_SCHEDULE_SECONDS : 172800,
            'full_keep_latest' => defined('APP_BACKUP_FULL_KEEP_LATEST') ? (int) APP_BACKUP_FULL_KEEP_LATEST : 1,
            'keep_latest' => defined('APP_BACKUP_KEEP_LATEST') ? (int) APP_BACKUP_KEEP_LATEST : 5,
            'keep_daily_days' => defined('APP_BACKUP_KEEP_DAILY_DAYS') ? (int) APP_BACKUP_KEEP_DAILY_DAYS : 14,
            'keep_weekly_weeks' => defined('APP_BACKUP_KEEP_WEEKLY_WEEKS') ? (int) APP_BACKUP_KEEP_WEEKLY_WEEKS : 8,
            'keep_monthly_months' => defined('APP_BACKUP_KEEP_MONTHLY_MONTHS') ? (int) APP_BACKUP_KEEP_MONTHLY_MONTHS : 6,
        ];
    }
}

if (!function_exists('backupEnsureDirectory')) {
    function backupEnsureDirectory($path, $mode = 0750)
    {
        if (is_dir($path)) {
            return true;
        }
        return @mkdir($path, $mode, true);
    }
}

if (!function_exists('backupLogMessage')) {
    function backupLogMessage($message)
    {
        $config = backupGetConfig();
        $logDir = dirname($config['log_file']);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }

        $line = '[' . date('c') . '] ' . (string) $message . PHP_EOL;
        @file_put_contents($config['log_file'], $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('backupReadState')) {
    function backupReadState()
    {
        $config = backupGetConfig();
        $defaults = [
            'latestBackup' => 0,
            'latestDbBackup' => 0,
            'latestFullBackup' => 0,
            'latestStatus' => 'never',
            'latestSnapshotId' => null,
            'latestReason' => null,
        ];

        if (!is_file($config['state_file'])) {
            return $defaults;
        }

        $raw = @file_get_contents($config['state_file']);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }
}

if (!function_exists('backupWriteState')) {
    function backupWriteState($state)
    {
        $config = backupGetConfig();
        $stateDir = dirname($config['state_file']);

        if (!is_dir($stateDir) && !@mkdir($stateDir, 0750, true)) {
            return false;
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        // Write directly with LOCK_EX (rename() fails on Windows when target exists)
        return @file_put_contents($config['state_file'], $json, LOCK_EX) !== false;
    }
}

if (!function_exists('backupAcquireLock')) {
    function backupAcquireLock()
    {
        $config = backupGetConfig();
        if (!backupEnsureDirectory(dirname($config['lock_file']))) {
            return [null, 'Cannot create backup lock directory'];
        }

        $fp = @fopen($config['lock_file'], 'c+');
        if ($fp === false) {
            return [null, 'Cannot open backup lock file'];
        }

        if (!@flock($fp, LOCK_EX | LOCK_NB)) {
            @fclose($fp);
            return [null, 'Backup is already running'];
        }

        @ftruncate($fp, 0);
        @fwrite($fp, 'pid=' . getmypid() . ';time=' . time());

        return [$fp, null];
    }
}

if (!function_exists('backupReleaseLock')) {
    function backupReleaseLock($fp)
    {
        if (is_resource($fp)) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }
}

if (!function_exists('backupDeleteDirectory')) {
    function backupDeleteDirectory($path)
    {
        if (!is_dir($path)) {
            return true;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $ok = true;
        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            if ($item->isLink() || $item->isFile()) {
                if (!@unlink($itemPath)) {
                    $ok = false;
                }
            } elseif ($item->isDir()) {
                if (!@rmdir($itemPath)) {
                    $ok = false;
                }
            }
        }

        if (!@rmdir($path)) {
            $ok = false;
        }

        return $ok;
    }
}

if (!function_exists('backupCopyDirectory')) {
    function backupCopyDirectory($source, $destination, &$stats)
    {
        if (!is_dir($source)) {
            return true;
        }

        if (!backupEnsureDirectory($destination, 0750)) {
            return false;
        }

        $items = @scandir($source);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_link($sourcePath)) {
                continue;
            }

            if (is_dir($sourcePath)) {
                if (!backupCopyDirectory($sourcePath, $destinationPath, $stats)) {
                    return false;
                }
                continue;
            }

            if (is_file($sourcePath)) {
                if (!@copy($sourcePath, $destinationPath)) {
                    return false;
                }
                @chmod($destinationPath, 0640);
                $stats['files'] = (int) ($stats['files'] ?? 0) + 1;
                $stats['bytes'] = (int) ($stats['bytes'] ?? 0) + (int) (@filesize($destinationPath) ?: 0);
            }
        }

        return true;
    }
}

if (!function_exists('backupHashFileSafe')) {
    function backupHashFileSafe($path)
    {
        if (!is_file($path)) {
            return null;
        }

        $hash = @hash_file('sha256', $path);
        return is_string($hash) ? $hash : null;
    }
}

if (!function_exists('backupHashDirectory')) {
    function backupHashDirectory($directory)
    {
        if (!is_dir($directory)) {
            return null;
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isFile()) {
                continue;
            }
            $paths[] = $item->getPathname();
        }

        sort($paths);

        $context = hash_init('sha256');
        foreach ($paths as $path) {
            $relative = str_replace('\\', '/', substr($path, strlen($directory) + 1));
            $fileHash = backupHashFileSafe($path);
            hash_update($context, $relative . "\0" . (string) $fileHash . "\0");
        }

        return hash_final($context);
    }
}

if (!function_exists('backupListSnapshots')) {
    function backupListSnapshots($snapshotsDir)
    {
        if (!is_dir($snapshotsDir)) {
            return [];
        }

        $entries = @scandir($snapshotsDir);
        if ($entries === false) {
            return [];
        }

        $snapshots = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $snapshotPath = $snapshotsDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($snapshotPath)) {
                continue;
            }

            $createdAt = (int) (@filemtime($snapshotPath) ?: 0);
            $manifestPath = $snapshotPath . DIRECTORY_SEPARATOR . 'manifest.json';
            if (is_file($manifestPath)) {
                $manifest = json_decode((string) @file_get_contents($manifestPath), true);
                if (is_array($manifest) && isset($manifest['created_at_epoch'])) {
                    $createdAt = (int) $manifest['created_at_epoch'];
                }
            }

            $snapshots[] = [
                'id' => $entry,
                'path' => $snapshotPath,
                'created_at_epoch' => $createdAt,
            ];
        }

        usort($snapshots, function ($a, $b) {
            return (int) $b['created_at_epoch'] <=> (int) $a['created_at_epoch'];
        });

        return $snapshots;
    }
}

if (!function_exists('backupDetectSnapshotType')) {
    function backupDetectSnapshotType($snapshotPath)
    {
        $manifestPath = $snapshotPath . DIRECTORY_SEPARATOR . 'manifest.json';
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) @file_get_contents($manifestPath), true);
            if (is_array($manifest) && isset($manifest['type'])) {
                return (string) $manifest['type'];
            }
        }
        return is_dir($snapshotPath . DIRECTORY_SEPARATOR . 'custom') ? 'full' : 'db';
    }
}

if (!function_exists('backupApplyRetentionPolicy')) {
    function backupApplyRetentionPolicy()
    {
        $config = backupGetConfig();
        $allSnapshots = backupListSnapshots($config['snapshots_dir']);
        if (empty($allSnapshots)) {
            return ['deleted' => [], 'kept' => []];
        }

        $dbSnapshots = [];
        $fullSnapshots = [];
        foreach ($allSnapshots as $snapshot) {
            $type = backupDetectSnapshotType($snapshot['path']);
            if ($type === 'full') {
                $fullSnapshots[] = $snapshot;
            } else {
                $dbSnapshots[] = $snapshot;
            }
        }

        $now = time();
        $keep = [];
        $dailyKeys = [];
        $weeklyKeys = [];
        $monthlyKeys = [];

        foreach ($dbSnapshots as $index => $snapshot) {
            $id = (string) $snapshot['id'];
            $createdAt = (int) $snapshot['created_at_epoch'];
            if ($createdAt <= 0) {
                $createdAt = $now;
            }

            if ($index < max(1, (int) $config['keep_latest'])) {
                $keep[$id] = true;
            }

            $ageSeconds = max(0, $now - $createdAt);
            $dayKey = date('Y-m-d', $createdAt);
            $weekKey = date('o-W', $createdAt);
            $monthKey = date('Y-m', $createdAt);

            if ($ageSeconds <= ((int) $config['keep_daily_days'] * 86400) && !isset($dailyKeys[$dayKey])) {
                $keep[$id] = true;
                $dailyKeys[$dayKey] = true;
            }

            if ($ageSeconds <= ((int) $config['keep_weekly_weeks'] * 7 * 86400) && !isset($weeklyKeys[$weekKey])) {
                $keep[$id] = true;
                $weeklyKeys[$weekKey] = true;
            }

            if ($ageSeconds <= ((int) $config['keep_monthly_months'] * 31 * 86400) && !isset($monthlyKeys[$monthKey])) {
                $keep[$id] = true;
                $monthlyKeys[$monthKey] = true;
            }
        }

        $fullKeep = max(1, (int) $config['full_keep_latest']);
        foreach ($fullSnapshots as $index => $snapshot) {
            if ($index < $fullKeep) {
                $keep[(string) $snapshot['id']] = true;
            }
        }

        $deleted = [];
        foreach ($allSnapshots as $snapshot) {
            $id = (string) $snapshot['id'];
            if (isset($keep[$id])) {
                continue;
            }

            if (backupDeleteDirectory($snapshot['path'])) {
                $deleted[] = $id;
                backupLogMessage('Deleted old snapshot by retention policy: ' . $id);
            }
        }

        return [
            'deleted' => $deleted,
            'kept' => array_values(array_keys($keep)),
        ];
    }
}

if (!function_exists('backupBuildSnapshotId')) {
    function backupBuildSnapshotId($type = 'db')
    {
        $tag = in_array($type, ['db', 'full'], true) ? $type : 'db';
        try {
            return date('Ymd_His') . '_' . $tag . '_' . bin2hex(random_bytes(4));
        } catch (Throwable $e) {
            return date('Ymd_His') . '_' . $tag . '_' . mt_rand(1000, 9999);
        }
    }
}

if (!function_exists('backupRun')) {
    function backupRun($reason = 'manual', $requestedBy = 'system', $force = false, $type = 'db')
    {
        $config = backupGetConfig();
        backupEnsureDirectory($config['backup_root'], 0750);
        backupEnsureDirectory($config['snapshots_dir'], 0750);
        backupEnsureDirectory($config['tmp_dir'], 0750);

        list($lockHandle, $lockError) = backupAcquireLock();
        if ($lockError !== null) {
            return [
                'status' => 'locked',
                'message' => $lockError,
            ];
        }

        $tmpSnapshotDir = null;

        try {
            $state = backupReadState();
            $now = time();
            $scheduleKey = $type === 'full' ? 'full_schedule_seconds' : 'db_schedule_seconds';
            $stateKey = $type === 'full' ? 'latestFullBackup' : 'latestDbBackup';
            $latestBackupMs = (int) ($state[$stateKey] ?? 0);
            $latestBackupSec = $latestBackupMs > 0 ? (int) floor($latestBackupMs / 1000) : 0;
            $isDue = $latestBackupSec <= 0 || ($now - $latestBackupSec) >= (int) $config[$scheduleKey];

            if (!$force && !$isDue) {
                return [
                    'status' => 'skipped',
                    'message' => ucfirst($type) . ' backup is not due yet.',
                    'latestBackup' => $latestBackupMs,
                    'nextDueAt' => ($latestBackupSec + (int) $config[$scheduleKey]) * 1000,
                ];
            }

            if (!is_file($config['database_file']) || !is_readable($config['database_file'])) {
                throw new RuntimeException('Database file is missing or not readable.');
            }

            $snapshotId = backupBuildSnapshotId($type);
            $tmpSnapshotDir = $config['tmp_dir'] . DIRECTORY_SEPARATOR . 'snapshot_' . $snapshotId;
            $finalSnapshotDir = $config['snapshots_dir'] . DIRECTORY_SEPARATOR . $snapshotId;

            if (is_dir($tmpSnapshotDir)) {
                backupDeleteDirectory($tmpSnapshotDir);
            }

            if (!@mkdir($tmpSnapshotDir, 0750, true)) {
                throw new RuntimeException('Cannot create temporary snapshot directory.');
            }

            $databaseTarget = $tmpSnapshotDir . DIRECTORY_SEPARATOR . 'database.db';
            if (!@copy($config['database_file'], $databaseTarget)) {
                throw new RuntimeException('Cannot copy database file to snapshot.');
            }
            @chmod($databaseTarget, 0640);

            $customStats = ['files' => 0, 'bytes' => 0];
            if ($type === 'full') {
                $customTarget = $tmpSnapshotDir . DIRECTORY_SEPARATOR . 'custom';
                if (!backupCopyDirectory($config['custom_source_dir'], $customTarget, $customStats)) {
                    throw new RuntimeException('Cannot copy custom directory to snapshot.');
                }
            }

            $manifest = [
                'snapshot_id' => $snapshotId,
                'type' => $type,
                'created_at_iso' => date('c', $now),
                'created_at_epoch' => $now,
                'reason' => (string) $reason,
                'requested_by' => (string) $requestedBy,
                'database' => [
                    'file' => 'database.db',
                    'size' => (int) (@filesize($databaseTarget) ?: 0),
                    'sha256' => backupHashFileSafe($databaseTarget),
                ],
            ];

            if ($type === 'full') {
                $manifest['custom'] = [
                    'directory' => 'custom',
                    'files' => (int) ($customStats['files'] ?? 0),
                    'bytes' => (int) ($customStats['bytes'] ?? 0),
                    'sha256' => backupHashDirectory($tmpSnapshotDir . DIRECTORY_SEPARATOR . 'custom'),
                ];
            }

            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($manifestJson === false || @file_put_contents($tmpSnapshotDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifestJson, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write backup manifest.');
            }

            if (!@rename($tmpSnapshotDir, $finalSnapshotDir)) {
                throw new RuntimeException('Cannot finalize snapshot directory.');
            }
            $tmpSnapshotDir = null;

            $retentionResult = backupApplyRetentionPolicy();
            $state = backupReadState();
            $state['latestBackup'] = $now * 1000;
            if ($type === 'db') {
                $state['latestDbBackup'] = $now * 1000;
            } else {
                $state['latestFullBackup'] = $now * 1000;
            }
            $state['latestStatus'] = 'success';
            $state['latestSnapshotId'] = $snapshotId;
            $state['latestReason'] = (string) $reason;

            backupWriteState($state);
            backupLogMessage('Backup completed [' . $type . ']: ' . $snapshotId . ' (reason=' . $reason . ', deleted=' . count($retentionResult['deleted']) . ')');

            return [
                'status' => 'success',
                'message' => 'Backup completed successfully.',
                'snapshotId' => $snapshotId,
                'createdAt' => $now * 1000,
                'deletedSnapshots' => $retentionResult['deleted'],
            ];
        } catch (Throwable $e) {
            backupLogMessage('Backup failed [' . $type . ']: ' . $e->getMessage());

            if (is_string($tmpSnapshotDir) && is_dir($tmpSnapshotDir)) {
                backupDeleteDirectory($tmpSnapshotDir);
            }

            $state = backupReadState();
            $state['latestStatus'] = 'error';
            $state['latestErrorAt'] = time() * 1000;
            backupWriteState($state);

            return [
                'status' => 'error',
                'message' => 'Backup failed. Check backup log for details.',
            ];
        } finally {
            backupReleaseLock($lockHandle);
        }
    }
}

if (!function_exists('backupRunManual')) {
    function backupRunManual($requestedBy = 'admin', $type = 'db')
    {
        return backupRun('manual', $requestedBy, true, $type);
    }
}

if (!function_exists('backupRunScheduledBackupIfDue')) {
    function backupRunScheduledBackupIfDue($requestedBy = 'scheduler')
    {
        $dbResult = backupRun('scheduled', $requestedBy, false, 'db');
        $fullResult = backupRun('scheduled', $requestedBy, false, 'full');

        $anySuccess = ($dbResult['status'] ?? '') === 'success' || ($fullResult['status'] ?? '') === 'success';
        $anyError = ($dbResult['status'] ?? '') === 'error' || ($fullResult['status'] ?? '') === 'error';

        if ($anyError) {
            $msgs = [];
            if (($dbResult['status'] ?? '') === 'error') $msgs[] = 'DB: ' . ($dbResult['message'] ?? 'failed');
            if (($fullResult['status'] ?? '') === 'error') $msgs[] = 'Full: ' . ($fullResult['message'] ?? 'failed');
            return ['status' => 'error', 'message' => implode('; ', $msgs)];
        }

        if ($anySuccess) {
            $id = ($dbResult['status'] ?? '') === 'success' ? ($dbResult['snapshotId'] ?? '') : ($fullResult['snapshotId'] ?? '');
            return ['status' => 'success', 'message' => 'Backup completed.', 'snapshotId' => $id];
        }

        return ['status' => 'skipped', 'message' => 'No backups due.'];
    }
}

if (!function_exists('backupReadLogTail')) {
    function backupReadLogTail($maxLines = 40)
    {
        $config = backupGetConfig();
        $lineLimit = max(1, (int) $maxLines);

        if (!is_file($config['log_file'])) {
            return [];
        }

        $lines = @file($config['log_file'], FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        return array_slice($lines, -$lineLimit);
    }
}

if (!function_exists('backupGetSnapshotSummaries')) {
    function backupGetSnapshotSummaries($maxSnapshots = 10)
    {
        $config = backupGetConfig();
        $limit = max(1, (int) $maxSnapshots);
        $snapshots = backupListSnapshots($config['snapshots_dir']);
        $snapshots = array_slice($snapshots, 0, $limit);

        $summary = [];
        foreach ($snapshots as $snapshot) {
            $snapshotId = (string) ($snapshot['id'] ?? '');
            $createdAt = (int) ($snapshot['created_at_epoch'] ?? 0);
            $snapshotPath = (string) ($snapshot['path'] ?? '');
            $manifestPath = $snapshotPath . DIRECTORY_SEPARATOR . 'manifest.json';

            $item = [
                'snapshotId' => $snapshotId,
                'type' => backupDetectSnapshotType($snapshotPath),
                'createdAt' => $createdAt > 0 ? ($createdAt * 1000) : 0,
                'reason' => null,
                'requestedBy' => null,
                'databaseSize' => null,
                'customFiles' => null,
                'customBytes' => null,
            ];

            if (is_file($manifestPath)) {
                $manifest = json_decode((string) @file_get_contents($manifestPath), true);
                if (is_array($manifest)) {
                    $manifestCreated = isset($manifest['created_at_epoch']) ? (int) $manifest['created_at_epoch'] : 0;
                    if ($manifestCreated > 0) {
                        $item['createdAt'] = $manifestCreated * 1000;
                    }

                    $item['reason'] = isset($manifest['reason']) ? (string) $manifest['reason'] : null;
                    $item['requestedBy'] = isset($manifest['requested_by']) ? (string) $manifest['requested_by'] : null;

                    if (isset($manifest['database']) && is_array($manifest['database'])) {
                        $item['databaseSize'] = isset($manifest['database']['size'])
                            ? (int) $manifest['database']['size']
                            : null;
                    }

                    if (isset($manifest['custom']) && is_array($manifest['custom'])) {
                        $item['customFiles'] = isset($manifest['custom']['files'])
                            ? (int) $manifest['custom']['files']
                            : null;
                        $item['customBytes'] = isset($manifest['custom']['bytes'])
                            ? (int) $manifest['custom']['bytes']
                            : null;
                    }
                }
            }

            $summary[] = $item;
        }

        return $summary;
    }
}

if (!function_exists('backupGetAdminStatus')) {
    function backupGetAdminStatus($logLineLimit = 30, $snapshotLimit = 20)
    {
        return [
            'state' => backupReadState(),
            'snapshots' => backupGetSnapshotSummaries($snapshotLimit),
            'logLines' => backupReadLogTail($logLineLimit),
        ];
    }
}

if (!function_exists('backupDeleteSnapshot')) {
    function backupDeleteSnapshot($snapshotId)
    {
        $config = backupGetConfig();
        $safe = basename((string) $snapshotId);

        if ($safe === '' || $safe === '.' || $safe === '..') {
            return ['status' => 'error', 'message' => 'Invalid snapshot ID.'];
        }

        $snapshotPath = $config['snapshots_dir'] . DIRECTORY_SEPARATOR . $safe;

        if (!is_dir($snapshotPath)) {
            return ['status' => 'error', 'message' => 'Snapshot not found.'];
        }

        if (!backupDeleteDirectory($snapshotPath)) {
            return ['status' => 'error', 'message' => 'Could not delete snapshot.'];
        }

        backupLogMessage('Snapshot deleted manually: ' . $safe);

        return ['status' => 'success', 'message' => 'Snapshot deleted.'];
    }
}

if (!function_exists('backupDownloadSnapshot')) {
    function backupDownloadSnapshot($snapshotId)
    {
        $config = backupGetConfig();
        $safe = basename((string) $snapshotId);

        if ($safe === '' || $safe === '.' || $safe === '..') {
            http_response_code(400);
            echo 'Invalid snapshot ID.';
            exit;
        }

        $snapshotPath = $config['snapshots_dir'] . DIRECTORY_SEPARATOR . $safe;

        if (!is_dir($snapshotPath)) {
            http_response_code(404);
            echo 'Snapshot not found.';
            exit;
        }

        $type = backupDetectSnapshotType($snapshotPath);
        $dbFile = $snapshotPath . DIRECTORY_SEPARATOR . 'database.db';

        // For db-only snapshots, serve the database file directly
        if ($type === 'db') {
            if (!is_file($dbFile)) {
                http_response_code(404);
                echo 'Database file not found in snapshot.';
                exit;
            }

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="backup-' . $safe . '.db"');
            header('Content-Length: ' . filesize($dbFile));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            readfile($dbFile);
            exit;
        }

        // Full snapshots: try ZipArchive, then PharData, then fallback to db file
        if (class_exists('ZipArchive')) {
            $zipFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_' . $safe . '_' . time() . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                $baseLen = strlen($snapshotPath) + 1;
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($snapshotPath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $file) {
                    $realPath = $file->getRealPath();
                    $relative = substr($realPath, $baseLen);
                    if ($file->isDir()) {
                        $zip->addEmptyDir($relative);
                    } elseif ($file->isFile()) {
                        $zip->addFile($realPath, $relative);
                    }
                }
                $zip->close();
                if (file_exists($zipFile) && filesize($zipFile) > 0) {
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="backup-' . $safe . '.zip"');
                    header('Content-Length: ' . filesize($zipFile));
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    readfile($zipFile);
                    @unlink($zipFile);
                    exit;
                }
                @unlink($zipFile);
            }
        }

        // Fallback: try PharData (tar.gz)
        if (class_exists('PharData')) {
            $tarFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_' . $safe . '_' . time() . '.tar';
            $gzFile = $tarFile . '.gz';
            try {
                $tar = new PharData($tarFile);
                $tar->buildFromDirectory($snapshotPath);
                $tar->compress(Phar::GZ);
                unset($tar);
                @unlink($tarFile);
                if (is_file($gzFile) && filesize($gzFile) > 0) {
                    header('Content-Type: application/gzip');
                    header('Content-Disposition: attachment; filename="backup-' . $safe . '.tar.gz"');
                    header('Content-Length: ' . filesize($gzFile));
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    readfile($gzFile);
                    @unlink($gzFile);
                    exit;
                }
            } catch (Throwable $e) {
                @unlink($tarFile);
                @unlink($gzFile);
            }
        }

        // Final fallback: serve just the database file
        if (is_file($dbFile)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="backup-' . $safe . '.db"');
            header('Content-Length: ' . filesize($dbFile));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            readfile($dbFile);
            exit;
        }

        http_response_code(500);
        echo 'Could not create download.';
        exit;
    }
}
