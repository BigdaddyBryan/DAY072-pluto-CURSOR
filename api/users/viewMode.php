<?php

function toggleViewMode() {
  checkUser();
  $pdo = connectToDatabase();
  $sql = "UPDATE users SET view = :view WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['view' => $_SESSION['user']['view'] === 'view' ? 'basic' : 'view', 'id' => $_SESSION['user']['id']]);
  closeConnection($pdo);
  $_SESSION['user']['view'] = $_SESSION['user']['view'] === 'view' ? 'basic' : 'view';
  return $_SESSION['user']['view'];
}