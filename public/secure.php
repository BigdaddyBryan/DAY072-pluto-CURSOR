<?php

// === CONFIG ===
$secureFolder   = rtrim(__DIR__ . '/../custom/custom', '/\\');
$publicFolder   = rtrim(__DIR__ . '/../public/custom', '/\\');
$updateFile     = __DIR__ . '/../custom/isUpdated.php';
$localFile      = __DIR__ . '/localUpdated.php';
$logFile        = __DIR__ . '/error_log.txt';
$syncLockFile   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus_sync_' . md5(__DIR__) . '.lock';
$returnJson     = false; // Set true if you want JSON output
$syncCheckIntervalSeconds = 90;
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$forceSyncCheck = ($requestPath === '/secure')
    || (isset($_GET['forceSyncCustom']) && (string) $_GET['forceSyncCustom'] === '1');

// === HELPER: Logging ===
function logError($message)
{
    global $logFile;
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] $message\n", FILE_APPEND);
}

function ensureDirectory($directory)
{
    if (is_dir($directory)) {
        return true;
    }

    if (@mkdir($directory, 0755, true) || is_dir($directory)) {
        return true;
    }

    logError("Failed to create directory: $directory");
    return false;
}

function acquireSyncLock($lockFile)
{
    $lockHandle = @fopen($lockFile, 'c');
    if ($lockHandle === false) {
        logError("Failed to open sync lock file: $lockFile");
        return null;
    }

    if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
        @fclose($lockHandle);
        return false;
    }

    return $lockHandle;
}

function releaseSyncLock($lockHandle)
{
    if (!is_resource($lockHandle)) {
        return;
    }

    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
}

// === HELPER: Copy folder recursively (overlay mode) ===
function copyFolder($source, $destination)
{
    if (!is_dir($source)) {
        logError("Source directory not found: $source");
        return false;
    }

    if (!ensureDirectory($destination)) {
        return false;
    }

    $files = @scandir($source);
    if ($files === false) {
        logError("Failed to read source directory: $source");
        return false;
    }

    $success = true;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $src = $source . DIRECTORY_SEPARATOR . $file;
        $dst = $destination . DIRECTORY_SEPARATOR . $file;

        if (is_link($src)) {
            continue;
        }

        if (is_dir($src)) {
            if (!copyFolder($src, $dst)) {
                $success = false;
            }
            continue;
        }

        if (!is_file($src)) {
            continue;
        }

        if (!ensureDirectory(dirname($dst))) {
            $success = false;
            continue;
        }

        $needsCopy = !is_file($dst)
            || ((int) @filesize($src) !== (int) @filesize($dst))
            || ((int) @filemtime($src) > (int) @filemtime($dst));

        if (!$needsCopy) {
            continue;
        }

        if (is_file($dst) && !is_writable($dst)) {
            @chmod($dst, 0644);
        }

        if (!@copy($src, $dst)) {
            logError("Failed to copy file: $src");
            $success = false;
            continue;
        }

        $srcMtime = @filemtime($src);
        if ($srcMtime !== false) {
            @touch($dst, (int) $srcMtime);
        }
    }

    return $success;
}

// === HELPER: Get folder state recursively ===
function getFolderState($folderPath, $basePath)
{
    $lastModifiedTime = 0;
    $fileList = [];

    if (!is_dir($folderPath)) return ['lastModifiedTime' => 0, 'fileList' => []];

    $files = @scandir($folderPath);
    if ($files === false) {
        return ['lastModifiedTime' => $lastModifiedTime, 'fileList' => $fileList];
    }
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $filePath = $folderPath . '/' . $file;

        if (is_file($filePath)) {
            $lastModifiedTime = max($lastModifiedTime, filemtime($filePath));
            $fileList[] = str_replace($basePath, '', $filePath);
        } elseif (is_dir($filePath)) {
            $subState = getFolderState($filePath, $basePath);
            $lastModifiedTime = max($lastModifiedTime, $subState['lastModifiedTime']);
            $fileList = array_merge($fileList, $subState['fileList']);
        }
    }

    return ['lastModifiedTime' => $lastModifiedTime, 'fileList' => $fileList];
}

// === LOAD STORED STATES ===
$storedFileList = [];
$lastModifiedTime = 0;
$localFileList = [];
$source = null;
$lastSyncCheck = 0;

if (file_exists($updateFile)) {
    include $updateFile;
}

if (file_exists($localFile)) {
    include $localFile;
}

// Throttle expensive sync checks when this file is included on every request.
// Use a temp file as throttle fallback if the normal state file isn't writable.
$syncThrottleFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus_sync_' . md5(__DIR__) . '.ts';
if (!$forceSyncCheck) {
    $effectiveLastSync = (int) $lastSyncCheck;
    if ($effectiveLastSync === 0 && is_file($syncThrottleFile)) {
        $effectiveLastSync = (int) @file_get_contents($syncThrottleFile);
    }
    if ((time() - $effectiveLastSync) < $syncCheckIntervalSeconds) {
        return;
    }
}
// Update throttle file immediately so concurrent requests don't all scan
@file_put_contents($syncThrottleFile, (string) time());

$syncLockHandle = acquireSyncLock($syncLockFile);
if ($syncLockHandle === false) {
    // Another request is already synchronizing custom files.
    return;
}
if ($syncLockHandle === null) {
    // Unable to lock, fail-soft and continue request.
    return;
}

try {

    // === CURRENT FOLDER STATE ===
    $currentState = getFolderState($secureFolder, $secureFolder);
    $currentModifiedTime = $currentState['lastModifiedTime'];
    $currentFileList = $currentState['fileList'];

    // === DETECT CHANGES ===
    $filesAdded = array_diff($currentFileList, $storedFileList);
    $filesDeleted = array_diff($storedFileList, $currentFileList);

    $filesChanged = ($currentModifiedTime > $lastModifiedTime)
        || !empty($filesAdded)
        || !empty($filesDeleted);

    // === SYNC IF CHANGED ===
    if ($filesChanged || $localFileList !== $storedFileList) {
        $syncSucceeded = copyFolder($secureFolder, $publicFolder);
        if (!$syncSucceeded) {
            logError("Custom folder sync completed with warnings; keeping existing public assets");
        }

        if ($syncSucceeded) {
            $newStateContent = "<?php\n";
            $newStateContent .= "\$lastModifiedTime = $currentModifiedTime;\n";
            $newStateContent .= "\$storedFileList = " . var_export($currentFileList, true) . ";\n";
            @file_put_contents($updateFile, $newStateContent);

            $localContent = "<?php\n";
            $localContent .= "\$lastModifiedTime = $currentModifiedTime;\n";
            $localContent .= "\$source = '" . (isset($domain) ? $domain : '') . "';\n";
            $localContent .= "\$lastSyncCheck = " . time() . ";\n";
            $localContent .= "\$localFileList = " . var_export($currentFileList, true) . ";\n";
            @file_put_contents($localFile, $localContent);

            $message = "";
            if ($returnJson) {
                echo json_encode(['status' => 'updated', 'message' => $message]);
            } else {
                echo $message;
            }
        } else {
            // Sync failed softly: keep previous state but still update last sync check.
            $localContent = "<?php\n";
            $localContent .= "\$lastModifiedTime = $lastModifiedTime;\n";
            $localContent .= "\$source = '" . (isset($domain) ? $domain : '') . "';\n";
            $localContent .= "\$lastSyncCheck = " . time() . ";\n";
            $localContent .= "\$localFileList = " . var_export($localFileList, true) . ";\n";
            @file_put_contents($localFile, $localContent);

            if ($returnJson) {
                echo json_encode(['status' => 'warning', 'message' => 'Custom sync skipped due filesystem warnings']);
            }
        }
    } else {
        // Keep bookkeeping current so subsequent requests can skip costly scans.
        $localContent = "<?php\n";
        $localContent .= "\$lastModifiedTime = $currentModifiedTime;\n";
        $localContent .= "\$source = '" . (isset($domain) ? $domain : '') . "';\n";
        $localContent .= "\$lastSyncCheck = " . time() . ";\n";
        $localContent .= "\$localFileList = " . var_export($currentFileList, true) . ";\n";
        @file_put_contents($localFile, $localContent);

        $message = "";
        if ($returnJson) {
            echo json_encode(['status' => 'unchanged', 'message' => $message]);
        } else {
            echo $message;
        }
    }
} finally {
    releaseSyncLock($syncLockHandle);
}
