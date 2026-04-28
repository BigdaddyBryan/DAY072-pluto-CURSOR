<?php

function deleteImage($imageName)
{
  if (!checkAdmin()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  // Prevent path traversal
  $imageName = basename($imageName);

  $directory = __DIR__ . '/../../public/custom/images/slideshow';
  if (!is_dir($directory)) {
    return ['error' => 'Image directory not found'];
  }

  $scannedImages = scandir($directory);
  $images = $scannedImages === false ? [] : array_values(array_diff($scannedImages, ['.', '..']));

  if (!in_array($imageName, $images)) {
    return ['error' => 'Image not found'];
  }

  $imagePath = $directory . '/' . $imageName;

  if (!unlink($imagePath)) {
    return ['error' => 'Failed to delete image'];
  }

  echo json_encode(['success' => 'Image deleted']);
  return ['success' => 'Image deleted'];
}
