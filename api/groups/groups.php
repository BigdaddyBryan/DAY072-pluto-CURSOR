<?php

function groupCount() {
  $pdo = connectToDatabase();
  $sql = 'SELECT COUNT(*) FROM groups';
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $count = $stmt->fetchColumn();
  closeConnection($pdo);
  return $count;
}
