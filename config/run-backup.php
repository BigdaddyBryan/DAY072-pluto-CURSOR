<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/backupService.php';

$result = backupRunScheduledBackupIfDue('cron');
$status = (string) ($result['status'] ?? 'error');
$message = (string) ($result['message'] ?? 'Unknown backup error.');

if ($status === 'success') {
    echo "Backup created: " . ($result['snapshotId'] ?? 'unknown') . PHP_EOL;
    exit(0);
}

if ($status === 'skipped') {
    echo "Backup skipped: " . $message . PHP_EOL;
    exit(0);
}

if ($status === 'locked') {
    echo "Backup skipped (lock): " . $message . PHP_EOL;
    exit(0);
}

fwrite(STDERR, "Backup failed: " . $message . PHP_EOL);
exit(1);
