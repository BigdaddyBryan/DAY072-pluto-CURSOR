<?php

function getGroups($data)
{
  if(!checkUser()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM groups WHERE title LIKE :title";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => '%' . $data['query'] . '%']);
  $groups = $stmt->fetchAll();
  closeConnection($pdo);
  return $groups;
}