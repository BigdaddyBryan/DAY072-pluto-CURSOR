<?php

function favourite($linkId) {
  if (session_status() == PHP_SESSION_NONE) {
    session_start();
  }

  checkUser();

  $pdo = connectToDatabase();
  $sql = 'SELECT * FROM user_links WHERE user_id = :user_id AND link_id = :link_id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['user_id' => $_SESSION['user']['id'], 'link_id' => $linkId]);
  $favourite = $stmt->fetch();

  if ($favourite) {
    $sql = 'DELETE FROM user_links WHERE user_id = :user_id AND link_id = :link_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user']['id'], 'link_id' => $linkId]);
    closeConnection($pdo);
    return ['favourite' => false];
  }

  $sql = 'INSERT INTO user_links (user_id, link_id) VALUES (:user_id, :link_id)';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['user_id' => $_SESSION['user']['id'], 'link_id' => $linkId]);
  closeConnection($pdo);
  return ['favourite' => true];
}