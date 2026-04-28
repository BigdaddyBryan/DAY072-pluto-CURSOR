<?php

function renameGroup($id, $newTitle)
{
    if (!checkAdmin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }

    $pdo = connectToDatabase();

    $groupId = (int) $id;
    if ($groupId <= 0) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Invalid group id.'];
    }

    $newTitle = trim((string) $newTitle);
    if ($newTitle === '') {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Group name cannot be empty.'];
    }

    if (mb_strlen($newTitle, 'UTF-8') > 255) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Group name is too long.'];
    }

    // Verify group exists
    $stmt = $pdo->prepare('SELECT id, title FROM groups WHERE id = :id');
    $stmt->execute([':id' => $groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Group not found.'];
    }

    $oldTitle = $group['title'];

    if ($oldTitle === $newTitle) {
        closeConnection($pdo);
        return ['success' => true, 'message' => 'No changes made.', 'id' => $groupId, 'title' => $newTitle];
    }

    // Check if a group with the new name already exists
    $stmt = $pdo->prepare('SELECT id FROM groups WHERE LOWER(title) = LOWER(:title) AND id != :id');
    $stmt->execute([':title' => $newTitle, ':id' => $groupId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'A group with this name already exists.'];
    }

    // Rename the group
    $stmt = $pdo->prepare('UPDATE groups SET title = :title WHERE id = :id');
    $stmt->execute([':title' => $newTitle, ':id' => $groupId]);

    closeConnection($pdo);

    return [
        'success' => true,
        'message' => 'Group renamed successfully.',
        'id' => $groupId,
        'title' => $newTitle,
        'oldTitle' => $oldTitle,
    ];
}
