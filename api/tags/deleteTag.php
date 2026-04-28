<?php

function deleteTag($id)
{
    if (!checkAdmin()) {
        ob_start();
        header('Location: /');
        ob_end_flush();
        exit;
    }

    $pdo = connectToDatabase();

    $tagId = (int) $id;
    if ($tagId <= 0) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Invalid tag id.'];
    }

    $sql = "SELECT COUNT(*) FROM tags WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tagId]);
    $exists = (int) $stmt->fetchColumn() > 0;

    if (!$exists) {
        closeConnection($pdo);
        return ['success' => false, 'message' => 'Tag not found.'];
    }

    // Get all links associated with this tag before deleting
    $sql = "SELECT link_id FROM link_tags WHERE tag_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tagId]);
    $linkedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Archive the tag-link relationships
    foreach ($linkedItems as $item) {
        $sql = 'INSERT INTO archive (table_name, table_id, second_table_id, second_table_title) VALUES (:table_name, :table_id, :second_table_id, :second_table_title)';
        $stmt = $pdo->prepare($sql);
        $stmtFetch = $pdo->prepare('SELECT title FROM tags WHERE id = :id');
        $stmtFetch->execute([':id' => $tagId]);
        $tagData = $stmtFetch->fetch(PDO::FETCH_ASSOC);
        $tagTitle = $tagData ? $tagData['title'] : 'Unknown';
        $stmt->execute([
            'table_name' => 'link_tags',
            'table_id' => $item['link_id'],
            'second_table_id' => $tagId,
            'second_table_title' => $tagTitle
        ]);
    }

    // Delete all link_tags entries for this tag
    $sql = "DELETE FROM link_tags WHERE tag_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tagId]);

    // Get all visitors associated with this tag before deleting
    $sql = "SELECT visitor_id FROM visitors_tags WHERE tag_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tagId]);
    $visitorItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete all visitors_tags entries for this tag
    $sql = "DELETE FROM visitors_tags WHERE tag_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tagId]);

    // Archive the tag itself
    $sql = 'SELECT title FROM tags WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tagId]);
    $tagData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete the tag
    $sql = "DELETE FROM tags WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tagId]);

    // Archive the deleted tag
    if ($tagData) {
        $sql = 'INSERT INTO archive (table_name, table_id, title, created_at) VALUES (:table_name, :table_id, :title, :created_at)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'table_name' => 'tags',
            'table_id' => $tagId,
            'title' => $tagData['title'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    closeConnection($pdo);
    return ['success' => true, 'message' => 'Tag deleted successfully.'];
}

// Handle POST requests for deleting by title
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['title']) || isset($_GET['title']))) {
    if (!checkAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $pdo = connectToDatabase();
    $title = isset($_POST['title']) ? $_POST['title'] : $_GET['title'];

    // Find the tag by title
    $sql = "SELECT id FROM tags WHERE LOWER(title) = LOWER(:title)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':title' => $title]);
    $tag = $stmt->fetch(PDO::FETCH_ASSOC);

    closeConnection($pdo);

    if ($tag) {
        $result = deleteTag($tag['id']);
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tag not found']);
    }
    exit;
}

// Handle JSON POST requests for AJAX calls
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['title'])) {
        if (!checkAdmin()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $pdo = connectToDatabase();
        $title = $input['title'];

        // Find the tag by title
        $sql = "SELECT id FROM tags WHERE LOWER(title) = LOWER(:title)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':title' => $title]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);

        closeConnection($pdo);

        if ($tag) {
            $result = deleteTag($tag['id']);
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tag not found']);
        }
        exit;
    }
}
