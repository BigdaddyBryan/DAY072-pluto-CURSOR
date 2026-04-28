<?php

function restoreTag(int $id): array
{
    if (!checkAdmin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }

    $pdo = connectToDatabase();

    // Fetch the archived tag record
    $stmt = $pdo->prepare(
        "SELECT * FROM archive WHERE table_name = 'tags' AND table_id = :id ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $archived = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$archived) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Archive entry not found for tag ' . $id];
    }

    $archivedTitle = trim((string) ($archived['title'] ?? ''));
    if ($archivedTitle === '') {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Archived tag has no title and cannot be restored.'];
    }

    // Reuse an existing tag with the same title when present. This avoids
    // hard failures for stale archive rows that were effectively recreated.
    $stmt = $pdo->prepare("SELECT id FROM tags WHERE LOWER(title) = LOWER(:title) LIMIT 1");
    $stmt->execute([':title' => $archivedTitle]);
    $existingTag = $stmt->fetch(PDO::FETCH_ASSOC);
    $existingTagId = $existingTag ? (int) ($existingTag['id'] ?? 0) : 0;

    $pdo->beginTransaction();

    try {
        $restoredTagId = $id;
        $usedExistingTag = false;

        if ($existingTagId > 0) {
            $restoredTagId = $existingTagId;
            $usedExistingTag = true;
        } else {
            // Re-insert the tag with its original id when possible; otherwise
            // allow a new id so restore can still complete.
            $idCheck = $pdo->prepare("SELECT id FROM tags WHERE id = :id LIMIT 1");
            $idCheck->execute([':id' => $id]);
            $idTaken = (bool) $idCheck->fetch(PDO::FETCH_ASSOC);

            if ($idTaken) {
                $stmt = $pdo->prepare("INSERT INTO tags (title) VALUES (:title)");
                $stmt->execute([':title' => $archivedTitle]);
                $restoredTagId = (int) $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO tags (id, title) VALUES (:id, :title)"
                );
                $stmt->execute([':id' => $id, ':title' => $archivedTitle]);
            }
        }

        // Restore link_tags associations that were archived
        $stmt = $pdo->prepare(
            "SELECT * FROM archive
             WHERE table_name = 'link_tags' AND second_table_id = :tag_id"
        );
        $stmt->execute([':tag_id' => $id]);
        $linkRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($linkRows as $row) {
            // Only restore if the link still exists
            $stmtCheck = $pdo->prepare("SELECT id FROM links WHERE id = :link_id");
            $stmtCheck->execute([':link_id' => $row['table_id']]);
            if (!$stmtCheck->fetch()) {
                continue;
            }
            // Avoid duplicates
            $stmtDup = $pdo->prepare(
                "SELECT link_id FROM link_tags WHERE link_id = :link_id AND tag_id = :tag_id"
            );
            $stmtDup->execute([':link_id' => $row['table_id'], ':tag_id' => $restoredTagId]);
            if ($stmtDup->fetch()) {
                continue;
            }
            $stmtIns = $pdo->prepare(
                "INSERT INTO link_tags (link_id, tag_id) VALUES (:link_id, :tag_id)"
            );
            $stmtIns->execute([':link_id' => $row['table_id'], ':tag_id' => $restoredTagId]);
        }

        // Remove from archive
        $stmt = $pdo->prepare(
            "DELETE FROM archive WHERE (table_name = 'tags' AND table_id = :id)
             OR (table_name = 'link_tags' AND second_table_id = :id2)"
        );
        $stmt->execute([':id' => $id, ':id2' => $id]);

        $pdo->commit();
        closeConnection($pdo);

        if ($usedExistingTag) {
            return [
                'success' => true,
                'message' => 'Tag already existed. Relationships were restored and archive entry was cleaned up.',
            ];
        }

        return ['success' => true, 'message' => 'Tag restored successfully.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        closeConnection($pdo);
        error_log('restoreTag failed for id ' . $id . ': ' . $e->getMessage());
        return ['success' => false, 'message' => 'Restore failed.'];
    }
}
