<?php

function uploadGroupImage($image) {
  if (!isset($image['error']) || $image['error'] !== UPLOAD_ERR_OK || empty($image['tmp_name'])) {
    return null;
  }

  $validImageTypes = ['image/gif', 'image/jpeg', 'image/png'];
  if (!in_array($image['type'], $validImageTypes, true)) {
    return null;
  }

  if ($image['size'] > 5000000) {
    return null;
  }

  if (getimagesize($image['tmp_name']) === false) {
    return null;
  }

  $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
  $originalName = basename($image['name']);
  $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  if (!in_array($extension, $allowedExtensions, true)) {
    return null;
  }

  $targetDir = 'images/groups/';
  if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
  }

  $targetName = uniqid('group_', true) . '.' . $extension;
  $targetFile = $targetDir . $targetName;

  if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
    return null;
  }

  return $targetName;
}

function createGroup($postData, $fileData) {
  $title = isset($postData['title']) ? trim($postData['title']) : '';
  $description = isset($postData['description']) ? trim($postData['description']) : '';
  $image = isset($fileData['image']) ? $fileData['image'] : null;

  if ($title === '') {
    header('Location: /groups');
    exit;
  }

  $uploadedImageName = $image ? uploadGroupImage($image) : null;

  try {
    $pdo = connectToDatabase();

    $sql = 'INSERT INTO groups (title, description, image) VALUES (:title, :description, :image)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'title' => $title,
      'description'=> $description,
      'image'=> $uploadedImageName
    ]);

    closeConnection($pdo);
  } catch (PDOException $e) {
    echo 'There was a problem creating the group: ' . $e->getMessage();
  }

  header('Location: /groups');
  exit;
}