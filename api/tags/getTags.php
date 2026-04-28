<?php

function getTags($data)
{
  if(!checkUser()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM tags WHERE title LIKE :title";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => '%' . $data['query'] . '%']);
  $tags = $stmt->fetchAll();
  closeConnection($pdo);
  return $tags;
}