<?php

function uploadImage($image)
{
  // Check if the user is an admin
  if (!checkAdmin()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  // Set the directory where images are stored
  $directory = __DIR__ . '/../../public/custom/images/slideshow';

  if (!is_dir($directory)) {
    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
      return ['error' => 'Upload directory is not available'];
    }
  }

  $validFileTypes = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'webp'];

  $normalizeFilename = static function ($name) {
    $base = pathinfo((string) $name, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9-_]+/', '-', $base);
    $base = trim((string) $base, '-');
    return $base !== '' ? $base : 'image';
  };

  $uploadOne = static function ($fileName, $tmpName) use ($directory, $validFileTypes, $normalizeFilename) {
    $imageFileType = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    if (!in_array($imageFileType, $validFileTypes, true)) {
      return ['error' => 'Invalid file type'];
    }

    $originalImageName = $normalizeFilename($fileName);

    for ($attempt = 0; $attempt < 5; $attempt++) {
      $prefix = date('YmdHis') . '-' . bin2hex(random_bytes(3));
      $newImageName = $prefix . '-' . $originalImageName . '.' . $imageFileType;
      $imagePath = $directory . '/' . $newImageName;

      if (file_exists($imagePath)) {
        continue;
      }

      if (move_uploaded_file($tmpName, $imagePath)) {
        return ['success' => 'Image uploaded', 'filename' => $newImageName];
      }
    }

    return ['error' => 'Failed to upload image'];
  };

  if (is_array($image['name'] ?? null)) {
    $uploaded = [];
    $errors = [];

    foreach ($image['name'] as $index => $fileName) {
      $tmpName = $image['tmp_name'][$index] ?? null;
      $uploadError = $image['error'][$index] ?? UPLOAD_ERR_NO_FILE;

      if ($uploadError !== UPLOAD_ERR_OK || !$tmpName) {
        $errors[] = 'Failed to upload image';
        continue;
      }

      $result = $uploadOne($fileName, $tmpName);
      if (!empty($result['error'])) {
        $errors[] = $result['error'];
        continue;
      }

      $uploaded[] = $result['filename'];
    }

    if (!empty($errors) && empty($uploaded)) {
      return ['error' => $errors[0]];
    }

    return ['success' => 'Image uploaded', 'filenames' => $uploaded];
  }

  if (($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($image['tmp_name'])) {
    return ['error' => 'Failed to upload image'];
  }

  return $uploadOne($image['name'], $image['tmp_name']);
}
