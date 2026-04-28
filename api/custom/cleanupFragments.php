<?php

/**
 * Remove unused tag fragments from the database.
 * A tag is considered unused when it is not linked to any link/user/visitor.
 */
function cleanupFragments()
{
    if (!checkAdmin()) {
        return ['error' => 'Unauthorized'];
    }

    $pdo = connectToDatabase();

    try {
        $pdo->beginTransaction();

        $deleteSql = "DELETE FROM tags
            WHERE id NOT IN (
                SELECT tag_id FROM link_tags WHERE tag_id IS NOT NULL
                UNION
                SELECT tag_id FROM users_tags WHERE tag_id IS NOT NULL
                UNION
                SELECT tag_id FROM visitors_tags WHERE tag_id IS NOT NULL
            )";

        $stmt = $pdo->prepare($deleteSql);
        $stmt->execute();
        $deleted = (int) $stmt->rowCount();

        $pdo->commit();
        closeConnection($pdo);

        return [
            'success' => true,
            'deleted' => $deleted,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        closeConnection($pdo);

        return [
            'error' => 'Cleanup failed: ' . $e->getMessage(),
        ];
    }
}
