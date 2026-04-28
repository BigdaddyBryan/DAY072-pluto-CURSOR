<?php

function toggleDarkMode() {
  checkUser();
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  $requestedMode = strtolower(trim((string) ($data['mode'] ?? '')));

  if (!in_array($requestedMode, ['light', 'dark', 'system'], true)) {
    $current = strtolower((string) ($_SESSION['user']['mode'] ?? 'light'));
    $requestedMode = $current === 'dark' ? 'light' : 'dark';
  }

  $pdo = connectToDatabase();
  $sql = "UPDATE users SET mode = :mode WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['mode' => $requestedMode, 'id' => $_SESSION['user']['id']]);
  closeConnection($pdo);
  $_SESSION['user']['mode'] = $requestedMode;
  return $_SESSION['user']['mode'];
}