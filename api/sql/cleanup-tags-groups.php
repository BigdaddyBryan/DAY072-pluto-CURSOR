<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../../custom/database/database.db';
if (!file_exists($databasePath)) {
    fwrite(STDERR, "Database not found at {$databasePath}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $databasePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$report = [
    'tag_titles_trimmed' => 0,
    'group_titles_trimmed' => 0,
    'tag_duplicates_merged' => 0,
    'group_duplicates_merged' => 0,
    'orphan_link_tags_deleted' => 0,
    'orphan_users_tags_deleted' => 0,
    'orphan_visitors_tags_deleted' => 0,
    'orphan_link_groups_deleted' => 0,
    'orphan_users_groups_deleted' => 0,
    'orphan_visitors_groups_deleted' => 0,
    'duplicate_link_tags_deleted' => 0,
    'duplicate_users_tags_deleted' => 0,
    'duplicate_visitors_tags_deleted' => 0,
    'duplicate_link_groups_deleted' => 0,
    'duplicate_users_groups_deleted' => 0,
    'duplicate_visitors_groups_deleted' => 0,
    'empty_tags_deleted' => 0,
    'unused_tags_deleted' => 0,
    'empty_groups_deleted' => 0,
];

function trimTitles(PDO $pdo, string $table, string $reportKey, array &$report): void
{
    $rows = $pdo->query("SELECT id, title FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE {$table} SET title = :title WHERE id = :id");
    $count = 0;

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $title = (string) ($row['title'] ?? '');
        $trimmed = trim($title);
        if ($id <= 0 || $trimmed === $title) {
            continue;
        }

        $stmt->execute([
            'title' => $trimmed,
            'id' => $id,
        ]);
        $count++;
    }

    $report[$reportKey] = $count;
}

function mergeDuplicates(PDO $pdo, string $table, string $pivotTable, string $pivotColumn, array &$report, string $reportKey): void
{
    $dupeRows = $pdo->query("SELECT LOWER(title) AS normalized_title, GROUP_CONCAT(id) AS ids FROM {$table} WHERE TRIM(title) <> '' GROUP BY LOWER(title) HAVING COUNT(*) > 1")
        ->fetchAll(PDO::FETCH_ASSOC);

    $updatePivot = $pdo->prepare("UPDATE {$pivotTable} SET {$pivotColumn} = :target_id WHERE {$pivotColumn} = :source_id");
    $deleteEntity = $pdo->prepare("DELETE FROM {$table} WHERE id = :id");

    $merged = 0;
    foreach ($dupeRows as $dupeRow) {
        $idList = array_values(array_filter(array_map('intval', explode(',', (string) ($dupeRow['ids'] ?? '')))));
        sort($idList);
        if (count($idList) < 2) {
            continue;
        }

        $targetId = (int) $idList[0];
        $sourceIds = array_slice($idList, 1);

        foreach ($sourceIds as $sourceId) {
            $updatePivot->execute([
                'target_id' => $targetId,
                'source_id' => $sourceId,
            ]);

            $deleteEntity->execute(['id' => $sourceId]);
            $merged++;
        }
    }

    $report[$reportKey] = $merged;
}

function deleteEmptyTitles(PDO $pdo, string $table, array &$report, string $reportKey): void
{
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE TRIM(title) = ''");
    $stmt->execute();
    $report[$reportKey] = $stmt->rowCount();
}

function deleteOrphans(PDO $pdo, string $pivotTable, string $leftTable, string $leftColumn, string $rightTable, string $rightColumn, array &$report, string $reportKey): void
{
    $sql = "DELETE FROM {$pivotTable}
          WHERE {$leftColumn} NOT IN (SELECT id FROM {$leftTable})
             OR {$rightColumn} NOT IN (SELECT id FROM {$rightTable})";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $report[$reportKey] = $stmt->rowCount();
}

function deleteDuplicatePairs(PDO $pdo, string $pivotTable, string $leftColumn, string $rightColumn, array &$report, string $reportKey): void
{
    $sql = "DELETE FROM {$pivotTable}
          WHERE rowid NOT IN (
            SELECT MIN(rowid)
            FROM {$pivotTable}
            GROUP BY {$leftColumn}, {$rightColumn}
          )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $report[$reportKey] = $stmt->rowCount();
}

function deleteUnusedTags(PDO $pdo, array &$report, string $reportKey): void
{
    $sql = "DELETE FROM tags
          WHERE id NOT IN (
            SELECT tag_id FROM link_tags WHERE tag_id IS NOT NULL
            UNION
            SELECT tag_id FROM users_tags WHERE tag_id IS NOT NULL
            UNION
            SELECT tag_id FROM visitors_tags WHERE tag_id IS NOT NULL
          )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $report[$reportKey] = $stmt->rowCount();
}

try {
    $pdo->beginTransaction();

    trimTitles($pdo, 'tags', 'tag_titles_trimmed', $report);
    trimTitles($pdo, 'groups', 'group_titles_trimmed', $report);

    mergeDuplicates($pdo, 'tags', 'link_tags', 'tag_id', $report, 'tag_duplicates_merged');
    mergeDuplicates($pdo, 'groups', 'link_groups', 'group_id', $report, 'group_duplicates_merged');

    deleteEmptyTitles($pdo, 'tags', $report, 'empty_tags_deleted');
    deleteEmptyTitles($pdo, 'groups', $report, 'empty_groups_deleted');

    deleteOrphans($pdo, 'link_tags', 'links', 'link_id', 'tags', 'tag_id', $report, 'orphan_link_tags_deleted');
    deleteOrphans($pdo, 'users_tags', 'users', 'user_id', 'tags', 'tag_id', $report, 'orphan_users_tags_deleted');
    deleteOrphans($pdo, 'visitors_tags', 'visitors', 'visitor_id', 'tags', 'tag_id', $report, 'orphan_visitors_tags_deleted');
    deleteOrphans($pdo, 'link_groups', 'links', 'link_id', 'groups', 'group_id', $report, 'orphan_link_groups_deleted');
    deleteOrphans($pdo, 'users_groups', 'users', 'user_id', 'groups', 'group_id', $report, 'orphan_users_groups_deleted');
    deleteOrphans($pdo, 'visitors_groups', 'visitors', 'visitor_id', 'groups', 'group_id', $report, 'orphan_visitors_groups_deleted');

    deleteDuplicatePairs($pdo, 'link_tags', 'link_id', 'tag_id', $report, 'duplicate_link_tags_deleted');
    deleteDuplicatePairs($pdo, 'users_tags', 'user_id', 'tag_id', $report, 'duplicate_users_tags_deleted');
    deleteDuplicatePairs($pdo, 'visitors_tags', 'visitor_id', 'tag_id', $report, 'duplicate_visitors_tags_deleted');
    deleteDuplicatePairs($pdo, 'link_groups', 'link_id', 'group_id', $report, 'duplicate_link_groups_deleted');
    deleteDuplicatePairs($pdo, 'users_groups', 'user_id', 'group_id', $report, 'duplicate_users_groups_deleted');
    deleteDuplicatePairs($pdo, 'visitors_groups', 'visitor_id', 'group_id', $report, 'duplicate_visitors_groups_deleted');
    deleteUnusedTags($pdo, $report, 'unused_tags_deleted');

    $pdo->commit();

    echo "Cleanup completed.\n";
    foreach ($report as $key => $value) {
        echo $key . '=' . (int) $value . "\n";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Cleanup failed: " . $e->getMessage() . "\n");
    exit(1);
}
