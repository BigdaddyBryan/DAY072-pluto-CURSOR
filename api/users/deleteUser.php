<?php

function deleteUser($id) {
  
  if(!checkAdmin()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  $pdo = connectToDatabase();

  $sql = "DELETE FROM users WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':id' => $id
  ]);
}