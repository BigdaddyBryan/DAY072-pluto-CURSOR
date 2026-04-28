<?php

function restoreGroup(int $id): array
{
    if (!checkAdmin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }

    $pdo = connectToDatabase();

    // Fetch the archived group record
    $stmt = $pdo->prepare(
        "SELECT * FROM archive WHERE table_name = 'groups' AND table_id = :id ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $archived = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$archived) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Archive entry not found for group ' . $id];
    }

    $archivedTitle = trim((string) ($archived['title'] ?? ''));
    if ($archivedTitle === '') {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Archived group has no title and cannot be restored.'];
    }

    // Reuse an existing group with the same title when present. This avoids
    // hard failures for stale archive rows that were effectively recreated.
    $stmt = $pdo->prepare("SELECT id FROM groups WHERE LOWER(title) = LOWER(:title) LIMIT 1");
    $stmt->execute([':title' => $archivedTitle]);
    $existingGroup = $stmt->fetch(PDO::FETCH_ASSOC);
    $existingGroupId = $existingGroup ? (int) ($existingGroup['id'] ?? 0) : 0;

    $pdo->beginTransaction();

    try {
        $restoredGroupId = $id;
        $usedExistingGroup = false;

        if ($existingGroupId > 0) {
            $restoredGroupId = $existingGroupId;
            $usedExistingGroup = true;
        } else {
            // Try to restore the original id. If that id is already occupied,
            // restore with a new id so the action succeeds instead of failing.
            $idCheck = $pdo->prepare("SELECT id FROM groups WHERE id = :id LIMIT 1");
            $idCheck->execute([':id' => $id]);
            $idTaken = (bool) $idCheck->fetch(PDO::FETCH_ASSOC);

            if ($idTaken) {
                $stmt = $pdo->prepare("INSERT INTO groups (title) VALUES (:title)");
                $stmt->execute([':title' => $archivedTitle]);
                $restoredGroupId = (int) $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO groups (id, title) VALUES (:id, :title)"
                );
                $stmt->execute([':id' => $id, ':title' => $archivedTitle]);
            }
        }

        // Restore link_groups associations that were archived
        $stmt = $pdo->prepare(
            "SELECT * FROM archive
             WHERE table_name = 'link_groups' AND second_table_id = :group_id"
        );
        $stmt->execute([':group_id' => $id]);
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
                "SELECT id FROM link_groups WHERE link_id = :link_id AND group_id = :group_id"
            );
            $stmtDup->execute([':link_id' => $row['table_id'], ':group_id' => $restoredGroupId]);
            if ($stmtDup->fetch()) {
                continue;
            }
            $stmtIns = $pdo->prepare(
                "INSERT INTO link_groups (link_id, group_id) VALUES (:link_id, :group_id)"
            );
            $stmtIns->execute([':link_id' => $row['table_id'], ':group_id' => $restoredGroupId]);
        }

        // Restore visitors_groups associations when archive rows exist.
        if (function_exists('tableExists') && tableExists($pdo, 'visitors_groups')) {
            $stmt = $pdo->prepare(
                "SELECT * FROM archive
                 WHERE table_name = 'visitors_groups' AND second_table_id = :group_id"
            );
            $stmt->execute([':group_id' => $id]);
            $visitorRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($visitorRows as $row) {
                $visitorId = (int) ($row['table_id'] ?? 0);
                if ($visitorId <= 0) {
                    continue;
                }

                $stmtCheck = $pdo->prepare("SELECT id FROM visitors WHERE id = :visitor_id");
                $stmtCheck->execute([':visitor_id' => $visitorId]);
                if (!$stmtCheck->fetch()) {
                    continue;
                }

                $stmtDup = $pdo->prepare(
                    "SELECT visitor_id FROM visitors_groups WHERE visitor_id = :visitor_id AND group_id = :group_id"
                );
                $stmtDup->execute([':visitor_id' => $visitorId, ':group_id' => $restoredGroupId]);
                if ($stmtDup->fetch()) {
                    continue;
                }

                $stmtIns = $pdo->prepare(
                    "INSERT INTO visitors_groups (visitor_id, group_id) VALUES (:visitor_id, :group_id)"
                );
                $stmtIns->execute([':visitor_id' => $visitorId, ':group_id' => $restoredGroupId]);
            }
        }

        // Remove from archive
        $stmt = $pdo->prepare(
            "DELETE FROM archive WHERE (table_name = 'groups' AND table_id = :id)
             OR (table_name = 'link_groups' AND second_table_id = :id2)
             OR (table_name = 'visitors_groups' AND second_table_id = :id3)"
        );
        $stmt->execute([':id' => $id, ':id2' => $id, ':id3' => $id]);

        $pdo->commit();
        closeConnection($pdo);

        if ($usedExistingGroup) {
            return [
                'success' => true,
                'message' => 'Group already existed. Relationships were restored and archive entry was cleaned up.',
            ];
        }

        return ['success' => true, 'message' => 'Group restored successfully.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        closeConnection($pdo);
        error_log('restoreGroup failed for id ' . $id . ': ' . $e->getMessage());
        return ['success' => false, 'message' => 'Restore failed.'];
    }
}
