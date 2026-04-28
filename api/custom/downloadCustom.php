<?php

if (!class_exists('ZipArchive')) {
    die("ZipArchive extension is not enabled.");
}

if (!checkAdmin()) {
    http_response_code(401);
    exit;
}

$folder = realpath(__DIR__ . '/../../public/custom');
if (!$folder || !is_dir($folder)) {
    die("Source folder does not exist: $folder");
}

$zipFile = sys_get_temp_dir() . '/custom_' . time() . '.zip';
if (!is_writable(sys_get_temp_dir())) {
    die("Temp directory is not writable: " . sys_get_temp_dir());
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
    die("Could not create zip file.");
}

$folderLen = strlen($folder) + 1;

// Add files and empty directories
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, $folderLen);

    if ($file->isDir()) {
        // Add empty directory (ZipArchive only adds if explicitly told)
        $zip->addEmptyDir($relativePath);
    } elseif ($file->isFile()) {
        $zip->addFile($filePath, $relativePath);
    }
}
$zip->close();

if (!file_exists($zipFile) || filesize($zipFile) === 0) {
    http_response_code(500);
    die('Failed to create zip file.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename=custom.zip');
header('Content-Length: ' . filesize($zipFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!readfile($zipFile)) {
    http_response_code(500);
    die('Failed to download file.');
}

unlink($zipFile);
exit;
