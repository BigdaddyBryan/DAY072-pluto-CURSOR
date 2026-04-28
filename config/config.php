<?php

if (!defined('APP_SESSION_LIFETIME_SECONDS')) {
    // 30 days — keep users logged in for a long time
    define('APP_SESSION_LIFETIME_SECONDS', 30 * 24 * 60 * 60);
}

if (!defined('APP_SESSION_SAVE_PATH')) {
    // Use an isolated directory so other PHP apps' GC cannot delete our sessions
    define('APP_SESSION_SAVE_PATH', __DIR__ . '/../custom/sessions');
}

if (!defined('APP_BACKUP_DB_SCHEDULE_SECONDS')) {
    // Database backup cadence (24 hours)
    define('APP_BACKUP_DB_SCHEDULE_SECONDS', 24 * 60 * 60);
}

if (!defined('APP_BACKUP_FULL_SCHEDULE_SECONDS')) {
    // Full backup cadence (48 hours)
    define('APP_BACKUP_FULL_SCHEDULE_SECONDS', 48 * 60 * 60);
}

if (!defined('APP_BACKUP_FULL_KEEP_LATEST')) {
    // Only keep the latest full backup to save storage
    define('APP_BACKUP_FULL_KEEP_LATEST', 1);
}

if (!defined('APP_BACKUP_KEEP_LATEST')) {
    define('APP_BACKUP_KEEP_LATEST', 5);
}

if (!defined('APP_BACKUP_KEEP_DAILY_DAYS')) {
    define('APP_BACKUP_KEEP_DAILY_DAYS', 14);
}

if (!defined('APP_BACKUP_KEEP_WEEKLY_WEEKS')) {
    define('APP_BACKUP_KEEP_WEEKLY_WEEKS', 8);
}

if (!defined('APP_BACKUP_KEEP_MONTHLY_MONTHS')) {
    define('APP_BACKUP_KEEP_MONTHLY_MONTHS', 6);
}

if (!function_exists('connectToDatabase')) {
    /**
     * Return a writable PDO connection (singleton per request).
     *
     * When the canonical DB lives on a cloud-synced folder (OneDrive /
     * iCloud) PHP may be unable to write to it directly.  In that case a
     * working copy is kept in a stable temp location so that all writes
     * succeed and data persists across server restarts.
     *
     * A single cached connection is returned for the entire request
     * lifetime to avoid SQLite lock contention between multiple PDOs.
     */
    function connectToDatabase()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $canonicalPath = __DIR__ . '/../custom/database/database.db';

        if (!file_exists($canonicalPath)) {
            throw new Exception("Database file not found: $canonicalPath");
        }

        // Check if the directory is writable at the OS level (cloud-synced
        // folders like OneDrive may report is_writable() = true but reject
        // actual writes with errno=9).
        $dbDir = dirname($canonicalPath);
        $canWrite = false;
        $probe = $dbDir . DIRECTORY_SEPARATOR . '.db_write_test_' . getmypid();
        if (@file_put_contents($probe, '1') !== false) {
            @unlink($probe);
            $canWrite = true;
        }

        $dbPath = $canonicalPath;

        if (!$canWrite) {
            // Resolve a stable working-copy path that survives server restarts.
            $projectHash = md5(realpath($canonicalPath));
            $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-db-' . $projectHash;
            if (!is_dir($workDir)) {
                @mkdir($workDir, 0770, true);
            }
            $dbPath = $workDir . DIRECTORY_SEPARATOR . 'database.db';

            // Seed the working copy from the canonical DB if it doesn't exist
            // yet, or if the canonical copy is newer (external sync / pull).
            $needsCopy = !file_exists($dbPath);
            if (!$needsCopy && filemtime($canonicalPath) > filemtime($dbPath)) {
                $needsCopy = true;
            }
            if ($needsCopy) {
                if (!@copy($canonicalPath, $dbPath)) {
                    throw new Exception("Cannot create writable database copy at: $dbPath");
                }
                @unlink($dbPath . '-journal');
                @unlink($dbPath . '-wal');
                @unlink($dbPath . '-shm');
            }
        }

        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $cached = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Unable to open database: " . $e->getMessage());
        }
    }
}

if (!function_exists('closeConnection')) {
    function closeConnection($pdo)
    {
        // No-op: singleton connection is reused for the entire request.
    }
}
