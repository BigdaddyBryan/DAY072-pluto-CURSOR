<?php
function deleteGroup($id)
{

  if (!checkAdmin()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  $pdo = connectToDatabase();

  $groupId = (int) $id;
  if ($groupId <= 0) {
    closeConnection($pdo);
    return ['success' => false, 'message' => 'Invalid group id.'];
  }

  $sql = "SELECT COUNT(*) FROM groups WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $groupId]);
  $exists = (int) $stmt->fetchColumn() > 0;

  if (!$exists) {
    closeConnection($pdo);
    return ['success' => false, 'message' => 'Group not found.'];
  }

  // Get all links associated with this group before deleting
  $sql = "SELECT link_id FROM link_groups WHERE group_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $groupId]);
  $linkedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Archive the group-link relationships
  foreach ($linkedItems as $item) {
    $sql = 'INSERT INTO archive (table_name, table_id, second_table_id, second_table_title) VALUES (:table_name, :table_id, :second_table_id, :second_table_title)';
    $stmt = $pdo->prepare($sql);
    $stmtFetch = $pdo->prepare('SELECT title FROM groups WHERE id = :id');
    $stmtFetch->execute([':id' => $groupId]);
    $groupData = $stmtFetch->fetch(PDO::FETCH_ASSOC);
    $groupTitle = $groupData ? $groupData['title'] : 'Unknown';
    $stmt->execute([
      'table_name' => 'link_groups',
      'table_id' => $item['link_id'],
      'second_table_id' => $groupId,
      'second_table_title' => $groupTitle
    ]);
  }

  // Delete all link_groups entries for this group
  $sql = "DELETE FROM link_groups WHERE group_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $groupId]);

  // Get all visitors associated with this group before deleting
  $sql = "SELECT visitor_id FROM visitors_groups WHERE group_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $groupId]);
  $visitorItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Delete all visitors_groups entries for this group
  $sql = "DELETE FROM visitors_groups WHERE group_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $groupId]);

  // Archive the group itself
  $sql = 'SELECT title FROM groups WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $groupId]);
  $groupData = $stmt->fetch(PDO::FETCH_ASSOC);

  // Delete the group
  $sql = "DELETE FROM groups WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':id' => $groupId
  ]);

  // Archive the deleted group
  if ($groupData) {
    $sql = 'INSERT INTO archive (table_name, table_id, title, created_at) VALUES (:table_name, :table_id, :title, :created_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'table_name' => 'groups',
      'table_id' => $groupId,
      'title' => $groupData['title'],
      'created_at' => date('Y-m-d H:i:s')
    ]);
  }

  closeConnection($pdo);
  return ['success' => true, 'message' => 'Group deleted successfully.'];
}

// Handle POST requests for deleting by title
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['title']) || isset($_GET['title']))) {
  if (!checkAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
  }

  $pdo = connectToDatabase();
  $title = isset($_POST['title']) ? $_POST['title'] : $_GET['title'];

  // Find the group by title
  $sql = "SELECT id FROM groups WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':title' => $title]);
  $group = $stmt->fetch(PDO::FETCH_ASSOC);

  closeConnection($pdo);

  if ($group) {
    $result = deleteGroup($group['id']);
    header('Content-Type: application/json');
    echo json_encode($result);
  } else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Group not found']);
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

    // Find the group by title
    $sql = "SELECT id FROM groups WHERE LOWER(title) = LOWER(:title)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':title' => $title]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    closeConnection($pdo);

    if ($group) {
      $result = deleteGroup($group['id']);
      header('Content-Type: application/json');
      echo json_encode($result);
    } else {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'message' => 'Group not found']);
    }
    exit;
  }
}
