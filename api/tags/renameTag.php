<?php

function renameTag($id, $newTitle)
{
    if (!checkAdmin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }

    $pdo = connectToDatabase();

    $tagId = (int) $id;
    if ($tagId <= 0) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Invalid tag id.'];
    }

    $newTitle = trim((string) $newTitle);
    if ($newTitle === '') {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Tag name cannot be empty.'];
    }

    if (mb_strlen($newTitle, 'UTF-8') > 255) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Tag name is too long.'];
    }

    // Verify tag exists
    $stmt = $pdo->prepare('SELECT id, title FROM tags WHERE id = :id');
    $stmt->execute([':id' => $tagId]);
    $tag = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tag) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Tag not found.'];
    }

    $oldTitle = $tag['title'];

    if ($oldTitle === $newTitle) {
        closeConnection($pdo);
        return ['success' => true, 'message' => 'No changes made.', 'id' => $tagId, 'title' => $newTitle];
    }

    // Check if a tag with the new name already exists
    $stmt = $pdo->prepare('SELECT id FROM tags WHERE LOWER(title) = LOWER(:title) AND id != :id');
    $stmt->execute([':title' => $newTitle, ':id' => $tagId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'A tag with this name already exists.'];
    }

    // Rename the tag
    $stmt = $pdo->prepare('UPDATE tags SET title = :title WHERE id = :id');
    $stmt->execute([':title' => $newTitle, ':id' => $tagId]);

    closeConnection($pdo);

    return [
        'success' => true,
        'message' => 'Tag renamed successfully.',
        'id' => $tagId,
        'title' => $newTitle,
        'oldTitle' => $oldTitle,
    ];
}
