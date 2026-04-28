<?php

function uploadGroupImageForEdit($image) {
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

function editGroup($postData, $fileData) {
  $id = isset($postData['id']) ? (int)$postData['id'] : 0;
  $title = isset($postData['title']) ? trim($postData['title']) : '';
  $description = isset($postData['description']) ? trim($postData['description']) : '';
  $image = isset($fileData['image']) ? $fileData['image'] : null;
  $removeImage = isset($postData['remove_image']) && $postData['remove_image'] === '1';

  if ($id <= 0 || $title === '') {
    header('Location: /groups');
    exit;
  }

  $pdo = connectToDatabase();

  $currentImageStmt = $pdo->prepare('SELECT image FROM groups WHERE id = :id');
  $currentImageStmt->execute(['id' => $id]);
  $currentImage = $currentImageStmt->fetchColumn();

  if ($currentImage === false) {
    closeConnection($pdo);
    header('Location: /groups');
    exit;
  }

  $nextImage = $currentImage;
  $uploadedImageName = $image ? uploadGroupImageForEdit($image) : null;

  if ($uploadedImageName !== null) {
    $nextImage = $uploadedImageName;
  } else if ($removeImage) {
    $nextImage = null;
  }

  $sql = 'UPDATE groups SET title = :title, description = :description, image = :image, modified_at = :modified_at, modifier = :modifier WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'id' => $id,
    'title' => $title,
    'description'=> $description,
    'image'=> $nextImage,
    'modified_at' => date('Y-m-d H:i:s'),
    'modifier' => $_SESSION['user']['email']
  ]);

  closeConnection($pdo);

  $imageHasChanged = $currentImage !== $nextImage;
  if ($imageHasChanged && !empty($currentImage) && $currentImage !== 'default.png') {
    $currentImagePath = 'images/groups/' . $currentImage;
    if (file_exists($currentImagePath)) {
      unlink($currentImagePath);
    }
  }

  header('Location: /groups');
  exit;
}