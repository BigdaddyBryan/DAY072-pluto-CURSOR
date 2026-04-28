<?php
function editVisitor($postData) {
  if(!checkAdmin()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  $id = $postData['id'];
  $name = $postData['name'] === '' ? generateRandomName() : $postData['name'];
  $tags = json_decode($postData['tags'], true);
  $groups = json_decode($postData['groups'], true);

  $pdo = connectToDatabase();

  // Update the visitor details
  $sql = "UPDATE visitors SET name = :name, modifier = :modifier, modified_at = :modified_at WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
      'name' => $name,
      'id' => $id,
      'modifier' => $_SESSION['user']['id'],
      'modified_at' => date('Y-m-d H:i:s')
  ]);

  // Delete existing tags for the visitor
  $sql = "DELETE FROM visitors_tags WHERE visitor_id = :visitorId";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['visitorId' => $id]);

  // Insert new tags
  foreach ($tags as $tag) {
      // Check if the tag already exists in the tags table
      $sql = "SELECT id FROM tags WHERE title = :tag";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['tag' => $tag]);
      $tagId = $stmt->fetchColumn();

      if (!$tagId) {
          // Insert the new tag into the tags table
          $sql = "INSERT INTO tags (title) VALUES (:tag)";
          $stmt = $pdo->prepare($sql);
          $stmt->execute(['tag' => ucfirst($tag)]);
          $tagId = $pdo->lastInsertId();
      }

      // Insert the tag into the visitors_tags table
      $sql = "INSERT INTO visitors_tags (visitor_id, tag_id) VALUES (:visitorId, :tagId)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['visitorId' => $id, 'tagId' => $tagId]);
  }

  // Delete existing groups for the visitor
  $sql = "DELETE FROM visitors_groups WHERE visitor_id = :visitorId";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['visitorId' => $id]);

  // Insert new groups
  foreach ($groups as $group) {
      // Check if the group already exists in the groups table
      $sql = "SELECT id FROM groups WHERE title = :group";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['group' => $group]);
      $groupId = $stmt->fetchColumn();

      if (!$groupId) {
          // Insert the new group into the groups table
          $sql = "INSERT INTO groups (title) VALUES (:group)";
          $stmt = $pdo->prepare($sql);
          $stmt->execute(['group' => ucfirst($group)]);
          $groupId = $pdo->lastInsertId();
      }

      // Insert the group into the visitors_groups table
      $sql = "INSERT INTO visitors_groups (visitor_id, group_id) VALUES (:visitorId, :groupId)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['visitorId' => $id, 'groupId' => $groupId]);
  }

  echo json_encode(['success' => 'Visitor updated successfully']);
  closeConnection($pdo);
}