<?php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

if (!checkAdmin()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

require_once __DIR__ . '/backupService.php';

$snapshotId = trim((string) ($_GET['id'] ?? ''));

if ($snapshotId === '') {
    http_response_code(400);
    echo 'Missing snapshot ID.';
    exit;
}

backupDownloadSnapshot($snapshotId);
