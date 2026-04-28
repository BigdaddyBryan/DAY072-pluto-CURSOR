<?php
function getlink() {
  $data = json_decode(file_get_contents("php://input"), true);

  if(!checkAdmin()) {
    echo json_encode(array('error' => 'You do not have permission to do this'));
    return;
  }

  $pdo = connectToDatabase();

  $sql = 'SELECT title FROM links WHERE title = :title';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array(':title' => $data['title']));
  $link = $stmt->fetch();


  closeConnection($pdo);
  return $link;
}