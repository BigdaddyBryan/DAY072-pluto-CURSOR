<?php

// === AUTHENTICATION ===
if (!checkAdmin()) {
    http_response_code(403);
    die('Forbidden');
}

// === BASIC CHECKS ===
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('ZipArchive extension is not enabled.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

if (!isset($_FILES['customZip']) || $_FILES['customZip']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No file uploaded or upload error.');
}

// === FILE VALIDATION ===
$uploadedFile = $_FILES['customZip']['tmp_name'];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile);
finfo_close($finfo);

if (!in_array($mimeType, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
    http_response_code(400);
    die('Uploaded file is not a zip archive.');
}

// === FOLDER SETUP ===
$sourceFolder = __DIR__ . '/../../custom/custom';
$publicFolder = __DIR__ . '/../../public/custom';

function ensureFolderExists($folder)
{
    if (is_dir($folder)) {
        return true;
    }

    return @mkdir($folder, 0755, true) || is_dir($folder);
}

// Safe folder cleanup (keep root folder)
function safeClearFolderContents($folder)
{
    if (!is_dir($folder)) {
        return ensureFolderExists($folder);
    }

    $items = scandir($folder);
    if ($items === false) {
        return false;
    }

    $success = true;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $folder . DIRECTORY_SEPARATOR . $item;

        // Skip symlinks for safety
        if (is_link($path)) continue;

        if (is_dir($path)) {
            if (!safeClearFolderContents($path)) {
                $success = false;
            }
            if (!@rmdir($path)) {
                $success = false;
            }
        } elseif (is_file($path)) {
            if (!@unlink($path)) {
                $success = false;
            }
        }
    }

    return $success;
}

// Safe folder copy
function safeCopyFolder($source, $destination)
{
    if (!is_dir($source)) {
        return false;
    }

    if (!ensureFolderExists($destination)) {
        return false;
    }

    $items = scandir($source);
    if ($items === false) {
        return false;
    }

    $success = true;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $src = $source . DIRECTORY_SEPARATOR . $item;
        $dst = $destination . DIRECTORY_SEPARATOR . $item;
        if (is_dir($src)) {
            if (!safeCopyFolder($src, $dst)) {
                $success = false;
            }
        } elseif (is_file($src)) {
            if (!@copy($src, $dst)) {
                $success = false;
            }
        }
    }

    return $success;
}

// Clean or create both folders
foreach ([$sourceFolder, $publicFolder] as $folder) {
    if (!safeClearFolderContents($folder) || !ensureFolderExists($folder)) {
        http_response_code(500);
        die('Failed to prepare target folder.');
    }
}

// === VALIDATE ZIP CONTENTS (Zip Slip protection) ===
$zip = new ZipArchive();
if ($zip->open($uploadedFile) !== TRUE) {
    http_response_code(500);
    die('Failed to open zip file.');
}

$realTarget = realpath($sourceFolder) ?: $sourceFolder;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entryName = $zip->getNameIndex($i);
    if (strpos($entryName, '..') !== false) {
        $zip->close();
        http_response_code(400);
        die('Zip contains invalid path: directory traversal detected.');
    }
    // Block PHP files to prevent code execution
    $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
    if ($ext === 'php' || $ext === 'phtml' || $ext === 'phar') {
        $zip->close();
        http_response_code(400);
        die('Zip contains disallowed file type: ' . $ext);
    }
}

// === UNZIP to source (custom/custom/) ===
if (!$zip->extractTo($sourceFolder)) {
    $zip->close();
    http_response_code(500);
    die('Failed to extract zip file.');
}
$zip->close();

// Copy to public/custom/ so both dirs are in sync
if (!safeCopyFolder($sourceFolder, $publicFolder)) {
    http_response_code(500);
    die('Failed to sync public custom folder.');
}

// Update sync state
require_once __DIR__ . '/cssEditorShared.php';
customSyncUpdateState();

echo 'Custom folder uploaded and overwritten successfully.';
exit;
