<?php

function restoreArchivedLinks(array $ids)
{
    if (!checkAdmin()) {
        return [
            'success' => false,
            'restoredIds' => [],
            'failedIds' => array_values($ids),
            'restoredCount' => 0,
            'failedCount' => count($ids),
        ];
    }

    $normalizedIds = array_values(array_filter(array_map('intval', $ids), function ($id) {
        return $id > 0;
    }));

    if (empty($normalizedIds)) {
        return [
            'success' => false,
            'restoredIds' => [],
            'failedIds' => [],
            'restoredCount' => 0,
            'failedCount' => 0,
        ];
    }

    $pdo = connectToDatabase();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "UPDATE links
       SET status = 1,
           modifier = :modifier,
           modified_at = :modified_at
       WHERE id = :id AND status = 0"
        );

        $restoredIds = [];
        $failedIds = [];

        foreach ($normalizedIds as $id) {
            $stmt->execute([
                'modifier' => $_SESSION['user']['id'] ?? null,
                'modified_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]);

            if ($stmt->rowCount() > 0) {
                $restoredIds[] = $id;
            } else {
                $failedIds[] = $id;
            }
        }

        $pdo->commit();

        return [
            'success' => count($restoredIds) > 0 && count($failedIds) === 0,
            'restoredIds' => $restoredIds,
            'failedIds' => $failedIds,
            'restoredCount' => count($restoredIds),
            'failedCount' => count($failedIds),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'success' => false,
            'restoredIds' => [],
            'failedIds' => $normalizedIds,
            'restoredCount' => 0,
            'failedCount' => count($normalizedIds),
        ];
    } finally {
        closeConnection($pdo);
    }
}
