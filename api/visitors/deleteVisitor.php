<?php

function deleteVisitor($id) {
  if(!checkAdmin()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }
  
  $pdo = connectToDatabase();
  
  $sql = "DELETE FROM visitors WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);

  $sql = "SELECT * FROM visitors_tags WHERE visitor_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach($tags as $tag) {
    $sql = "SELECT * FROM visitors_tags WHERE tag_id = :tag_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['tag_id' => $tag['tag_id']]);
    $tagVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(count($tagVisitors) === 1) {
      $sql = "DELETE FROM tags WHERE id = :tag_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['tag_id' => $tag['tag_id']]);
    }
  }
  
  $sql = "DELETE FROM visitors_tags WHERE visitor_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);

  $sql = "SELECT * FROM visitors_groups WHERE visitor_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach($groups as $group) {
    $sql = "SELECT * FROM visitors_groups WHERE group_id = :group_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['group_id' => $group['group_id']]);
    $groupVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(count($groupVisitors) === 1) {
      $sql = "DELETE FROM groups WHERE id = :group_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['group_id' => $group['group_id']]);
    }
  }
  
  $sql = "DELETE FROM visitors_groups WHERE visitor_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);

  $sql = 'UPDATE visits SET visitor_id = NULL WHERE visitor_id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);

  closeConnection($pdo);
}