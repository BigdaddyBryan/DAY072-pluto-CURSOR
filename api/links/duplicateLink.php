<?php

function duplicateLink($id) {
  if(!checkAdmin()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }
    
    $pdo = connectToDatabase();
    
    $sql = "SELECT * FROM links WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $link = $stmt->fetch();
    
    $sql = "INSERT INTO links (title, url, shortlink, creator, created_at, modifier, modified_at, status) VALUES (:title, :url, :shortlink, :creator, :created_at, :creator, :created_at, :status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'title' => $link['title'] . ' (copy)',
        'url' => $link['url'],
        'shortlink' => generateRandomString(6),
        'creator' => $_SESSION['user']['id'],
        'created_at' => date('Y-m-d H:i:s'),
        'status' => $link['status']
    ]);
    
    $newId = $pdo->lastInsertId();
    
    $sql = "SELECT * FROM link_tags WHERE link_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $linkTags = $stmt->fetchAll();
    
    foreach ($linkTags as $linkTag) {
        $sql = "INSERT INTO link_tags (link_id, tag_id) VALUES (:link_id, :tag_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'link_id' => $newId,
            'tag_id' => $linkTag['tag_id']
        ]);
    }
    
    $sql = "SELECT * FROM link_groups WHERE link_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $linkGroups = $stmt->fetchAll();
    
    foreach ($linkGroups as $linkGroup) {
        $sql = "INSERT INTO link_groups (link_id, group_id) VALUES (:link_id, :group_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'link_id' => $newId,
            'group_id' => $linkGroup['group_id']
        ]);
    }

    closeConnection($pdo);

    header('Location: /');
}