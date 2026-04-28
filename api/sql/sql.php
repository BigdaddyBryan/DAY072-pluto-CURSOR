<?php
function ensureDatabaseTables()
{
    $pdo = connectToDatabase();
    $tableCheckQuery = "SELECT name FROM sqlite_master WHERE type='table' AND name='users_groups';";
    $stmt = $pdo->query($tableCheckQuery);
    $tableExists = $stmt->fetch();

    if (!$tableExists) {
        $sqlFilePath = __DIR__ . '/empty-sql.sql';

        if (!file_exists($sqlFilePath)) {
            throw new Exception("SQL file not found: $sqlFilePath");
        }

        $sql = file_get_contents($sqlFilePath);
        if ($sql === false) {
            throw new Exception("Unable to read SQL file: $sqlFilePath");
        }

        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            throw new Exception("Unable to execute SQL statements: " . $e->getMessage());
        }
    }

    closeConnection($pdo);
}

function tableExists(PDO $pdo, $tableName)
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :table LIMIT 1");
    $stmt->execute(['table' => $tableName]);
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, $tableName, $columnName)
{
    $stmt = $pdo->query("PRAGMA table_info('" . str_replace("'", "''", $tableName) . "')");
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($columns as $column) {
        if ((string) ($column['name'] ?? '') === (string) $columnName) {
            return true;
        }
    }

    return false;
}

function logMigrationError($message)
{
    $logPath = __DIR__ . '/../../public/error_log.txt';
    @file_put_contents($logPath, '[' . date('Y-m-d H:i:s') . '] DB migration: ' . $message . "\n", FILE_APPEND);
}

function migrationStatusPath()
{
    $primary = __DIR__ . '/../../public/json/dbMigrationStatus.json';
    $primaryDir = dirname($primary);

    if (is_dir($primaryDir)) {
        $testFile = $primaryDir . DIRECTORY_SEPARATOR . '.migration_write_test_' . getmypid();
        if (@file_put_contents($testFile, '1') !== false) {
            @unlink($testFile);
            return $primary;
        }
    }

    $fallbackDir = __DIR__ . '/../../custom/migration_state';
    if (!is_dir($fallbackDir)) {
        @mkdir($fallbackDir, 0775, true);
    }

    return $fallbackDir . '/dbMigrationStatus.json';
}

function migrationSchemaVersion()
{
    return 3;
}

function migrationRetryIntervalSeconds()
{
    return 3600;
}

function getCustomSwapSignature()
{
    $paths = [
        __DIR__ . '/../../custom/isUpdated.php',
        __DIR__ . '/../../custom/googleSSO.json',
        __DIR__ . '/../../custom/database/database.db',
    ];

    $parts = [];
    foreach ($paths as $path) {
        $exists = file_exists($path);
        $mtime = $exists ? (@filemtime($path) ?: 0) : 0;
        $size = $exists ? (@filesize($path) ?: 0) : 0;
        $parts[] = $path . '|' . ($exists ? '1' : '0') . '|' . $mtime . '|' . $size;
    }

    return hash('sha256', implode(';', $parts));
}

function readMigrationStatus()
{
    $path = migrationStatusPath();
    if (!file_exists($path)) {
        return ['updated_at' => null, 'databases' => []];
    }

    $decoded = json_decode((string) @file_get_contents($path), true);
    if (!is_array($decoded) || !isset($decoded['databases']) || !is_array($decoded['databases'])) {
        return ['updated_at' => null, 'databases' => []];
    }

    return $decoded;
}

function writeMigrationStatus($status)
{
    $path = migrationStatusPath();
    $dir = dirname($path);

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $result = @file_put_contents($path, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($result === false) {
        logMigrationError('Unable to write migration status at ' . $path);
    }

    return $result !== false;
}

function buildSchemaCheck(PDO $pdo)
{
    return [
        'link_aliases_table' => tableExists($pdo, 'link_aliases'),
        'user_sessions_table' => tableExists($pdo, 'user_sessions'),
        'links_expires_at_column' => columnExists($pdo, 'links', 'expires_at'),
        'archive_link_status_column' => columnExists($pdo, 'archive', 'link_status'),
        'archive_link_visit_count_column' => columnExists($pdo, 'archive', 'link_visit_count'),
        'archive_link_expires_at_column' => columnExists($pdo, 'archive', 'link_expires_at'),
        'archive_link_last_visited_at_column' => columnExists($pdo, 'archive', 'link_last_visited_at'),
        'archive_link_modifier_column' => columnExists($pdo, 'archive', 'link_modifier'),
        'archive_link_modified_at_original_column' => columnExists($pdo, 'archive', 'link_modified_at_original'),
        'users_sort_preference_column' => columnExists($pdo, 'users', 'sort_preference'),
    ];
}

function applyCompatibilitySchema(PDO $pdo)
{
    $pdo->beginTransaction();

    $pdo->exec('CREATE TABLE IF NOT EXISTS link_aliases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        link_id INTEGER NOT NULL,
        alias TEXT NOT NULL UNIQUE,
        created_at TEXT,
        created_by INTEGER
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_aliases_alias ON link_aliases(alias)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_aliases_link_id ON link_aliases(link_id)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS user_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id TEXT NOT NULL UNIQUE,
        token_hash TEXT,
        ip_address TEXT,
        user_agent TEXT,
        device_label TEXT,
        created_at TEXT,
        last_seen_at TEXT,
        revoked_at TEXT
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_sessions_user_id ON user_sessions(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_sessions_session_id ON user_sessions(session_id)');

    if (tableExists($pdo, 'links') && !columnExists($pdo, 'links', 'expires_at')) {
        $pdo->exec('ALTER TABLE links ADD COLUMN expires_at TEXT NULL');
    }

    if (tableExists($pdo, 'users') && !columnExists($pdo, 'users', 'sort_preference')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN sort_preference TEXT DEFAULT 'latest_modified'");
        $pdo->exec("UPDATE users SET sort_preference = 'latest_modified' WHERE sort_preference IS NULL OR trim(sort_preference) = ''");
    }

    if (tableExists($pdo, 'archive')) {
        $archiveColumns = [
            'link_status' => 'INTEGER',
            'link_visit_count' => 'INTEGER',
            'link_expires_at' => 'TEXT',
            'link_last_visited_at' => 'TEXT',
            'link_modifier' => 'INTEGER',
            'link_modified_at_original' => 'TEXT',
        ];

        foreach ($archiveColumns as $columnName => $columnType) {
            if (!columnExists($pdo, 'archive', $columnName)) {
                $pdo->exec('ALTER TABLE archive ADD COLUMN ' . $columnName . ' ' . $columnType);
            }
        }
    }

    $pdo->commit();

    // Fix groups.title column type if it's INTEGER (should be TEXT).
    // SQLite doesn't support ALTER COLUMN, so we check and warn only for new databases.
    // For existing databases, the column works with mixed types due to SQLite's type affinity.

    // Add indexes on junction table foreign keys for query performance
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_tags_link_id ON link_tags(link_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_tags_tag_id ON link_tags(tag_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_groups_link_id ON link_groups(link_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_groups_group_id ON link_groups(group_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_visitors_tags_visitor_id ON visitors_tags(visitor_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_visitors_tags_tag_id ON visitors_tags(tag_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_visitors_groups_visitor_id ON visitors_groups(visitor_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_visitors_groups_group_id ON visitors_groups(group_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_groups_user_id ON users_groups(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_groups_group_id ON users_groups(group_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_tags_user_id ON users_tags(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_tags_tag_id ON users_tags(tag_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_links_user_id ON user_links(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_links_link_id ON user_links(link_id)');
}

function migrateDatabasePath($dbPath)
{
    $status = [
        'path' => $dbPath,
        'exists' => false,
        'writable' => false,
        'migrated' => false,
        'error' => null,
        'schema' => null,
        'checked_at' => date('c'),
    ];

    if (!is_string($dbPath) || $dbPath === '' || !file_exists($dbPath)) {
        return $status;
    }

    $status['exists'] = true;
    $status['writable'] = is_writable($dbPath);
    $status['db_mtime'] = @filemtime($dbPath) ?: null;
    $status['db_size'] = @filesize($dbPath) ?: null;

    $pdo = null;
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        applyCompatibilitySchema($pdo);
        $status['migrated'] = true;
        $status['schema'] = buildSchemaCheck($pdo);
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $status['error'] = $e->getMessage();
        logMigrationError($e->getMessage() . ' | ' . $dbPath);
    }

    if ($pdo instanceof PDO) {
        closeConnection($pdo);
    }

    return $status;
}

function shouldRunCompatibilityMigration($status, $targets, $force = false)
{
    if ($force) {
        return true;
    }

    $schemaVersion = (int) ($status['schema_version'] ?? 0);
    if ($schemaVersion !== migrationSchemaVersion()) {
        return true;
    }

    $currentCustomSignature = getCustomSwapSignature();
    $previousCustomSignature = (string) ($status['custom_signature'] ?? '');
    if ($currentCustomSignature !== $previousCustomSignature) {
        return true;
    }

    $updatedAt = isset($status['updated_at']) ? strtotime((string) $status['updated_at']) : false;
    if ($updatedAt === false) {
        return true;
    }

    if ((time() - $updatedAt) >= migrationRetryIntervalSeconds()) {
        return true;
    }

    foreach ($targets as $key => $target) {
        $currentPath = $target['path'];
        $exists = !empty($target['exists']);
        $currentMtime = $exists ? (@filemtime($currentPath) ?: null) : null;
        $currentSize = $exists ? (@filesize($currentPath) ?: null) : null;

        $previous = $status['databases'][$key] ?? null;
        if (!is_array($previous)) {
            return true;
        }

        if ((string) ($previous['path'] ?? '') !== (string) $currentPath) {
            return true;
        }

        if ((bool) ($previous['exists'] ?? false) !== $exists) {
            return true;
        }

        if (($previous['db_mtime'] ?? null) !== $currentMtime) {
            return true;
        }

        if (($previous['db_size'] ?? null) !== $currentSize) {
            return true;
        }
    }

    return false;
}

function ensureDatabaseCompatibility($force = false)
{
    $status = readMigrationStatus();
    $currentCustomSignature = getCustomSwapSignature();
    $customDbPath = realpath(__DIR__ . '/../../custom/database/database.db');
    $customTargetPath = $customDbPath !== false ? $customDbPath : (__DIR__ . '/../../custom/database/database.db');

    $rootDbPath = realpath(__DIR__ . '/../../database.db');
    $rootTargetPath = $rootDbPath !== false ? $rootDbPath : (__DIR__ . '/../../database.db');

    $targets = [
        'custom' => [
            'path' => $customTargetPath,
            'exists' => file_exists($customTargetPath),
        ],
    ];

    if ($rootTargetPath !== $customTargetPath) {
        $targets['root'] = [
            'path' => $rootTargetPath,
            'exists' => file_exists($rootTargetPath),
        ];
    }

    if (!shouldRunCompatibilityMigration($status, $targets, (bool) $force)) {
        return;
    }

    $status['schema_version'] = migrationSchemaVersion();
    $status['custom_signature'] = $currentCustomSignature;
    $status['updated_at'] = date('c');

    if ($targets['custom']['exists']) {
        $status['databases']['custom'] = migrateDatabasePath($customTargetPath);
    } else {
        $status['databases']['custom'] = [
            'path' => $customTargetPath,
            'exists' => false,
            'writable' => false,
            'migrated' => false,
            'error' => 'Database path not found.',
            'schema' => null,
            'db_mtime' => null,
            'db_size' => null,
            'checked_at' => date('c'),
        ];
    }

    if (isset($targets['root'])) {
        if ($targets['root']['exists']) {
            $status['databases']['root'] = migrateDatabasePath($rootTargetPath);
        } else {
            $status['databases']['root'] = [
                'path' => $rootTargetPath,
                'exists' => false,
                'writable' => false,
                'migrated' => false,
                'error' => 'Database path not found.',
                'schema' => null,
                'db_mtime' => null,
                'db_size' => null,
                'checked_at' => date('c'),
            ];
        }
    }

    writeMigrationStatus($status);
}

ensureDatabaseTables();
ensureDatabaseCompatibility();
