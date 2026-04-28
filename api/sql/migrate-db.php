<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/sql.php';

$checkTables = ['link_aliases', 'user_sessions'];

function printDatabaseStatus($dbPath, $checkTables)
{
    if (!file_exists($dbPath)) {
        echo 'Missing database: ' . $dbPath . "\n";
        return;
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    echo 'Database: ' . $dbPath . "\n";

    foreach ($checkTables as $table) {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :table LIMIT 1");
        $stmt->execute(['table' => $table]);
        echo ' - ' . $table . ': ' . ($stmt->fetchColumn() ? 'OK' : 'MISSING') . "\n";
    }

    $linkColumnsStmt = $pdo->query("PRAGMA table_info('links')");
    $linkColumns = $linkColumnsStmt ? $linkColumnsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $hasExpiresAt = false;
    foreach ($linkColumns as $column) {
        if (($column['name'] ?? '') === 'expires_at') {
            $hasExpiresAt = true;
            break;
        }
    }
    echo ' - links.expires_at: ' . ($hasExpiresAt ? 'OK' : 'MISSING') . "\n";

    echo "\n";
    $pdo = null;
}

$customDbPath = realpath(__DIR__ . '/../../custom/database/database.db');
$rootDbPath = realpath(__DIR__ . '/../../database.db');

echo "Running schema-only migrations...\n\n";
ensureDatabaseCompatibility(true);

$statusFile = __DIR__ . '/../../public/json/dbMigrationStatus.json';
echo 'Status file: ' . $statusFile . "\n\n";

if ($customDbPath !== false) {
    printDatabaseStatus($customDbPath, $checkTables);
}

if ($rootDbPath !== false && $rootDbPath !== $customDbPath) {
    printDatabaseStatus($rootDbPath, $checkTables);
}

echo "Migration check complete.\n";
