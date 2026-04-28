<?php

function saveUploadedUserPicture($file, &$errorMessage = null)
{
  $errorMessage = null;

  if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      $errorMessage = 'Profile photo upload failed. Please try again.';
    }
    return null;
  }

  if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
    $errorMessage = 'Profile photo upload failed. Please try again.';
    return null;
  }

  $maxSizeBytes = 5 * 1024 * 1024;
  $fileSize = (int) ($file['size'] ?? 0);
  if ($fileSize <= 0 || $fileSize > $maxSizeBytes) {
    $errorMessage = 'Profile photo must be 5MB or smaller.';
    return null;
  }

  $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  $allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
  ];
  $originalName = (string) ($file['name'] ?? '');
  $tmpName = (string) ($file['tmp_name'] ?? '');
  $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

  if (!in_array($extension, $allowedExtensions, true) || $tmpName === '') {
    $errorMessage = 'Only JPG, PNG, GIF or WEBP images are allowed.';
    return null;
  }

  $detectedMime = function_exists('mime_content_type') ? (string) mime_content_type($tmpName) : '';
  if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimeTypes, true)) {
    $errorMessage = 'Only JPG, PNG, GIF or WEBP images are allowed.';
    return null;
  }

  $directory = __DIR__ . '/../../../public/custom/images/users';
  if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    return null;
  }

  $baseName = pathinfo($originalName, PATHINFO_FILENAME);
  $baseName = preg_replace('/[^a-zA-Z0-9-_]+/', '-', $baseName);
  $baseName = trim((string) $baseName, '-');
  if ($baseName === '') {
    $baseName = 'user';
  }

  $fileName = date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '-' . $baseName . '.' . $extension;
  $targetPath = $directory . '/' . $fileName;

  if (!move_uploaded_file($tmpName, $targetPath)) {
    $errorMessage = 'Profile photo upload failed. Please try again.';
    return null;
  }

  return '/custom/images/users/' . $fileName;
}

function fetchGoogleProfilePicture($email)
{
  $normalizedEmail = strtolower(trim((string) $email));
  if ($normalizedEmail === '') {
    return null;
  }

  return 'https://www.google.com/s2/photos/profile/' . rawurlencode($normalizedEmail) . '?sz=256';
}

function resolveFallbackProfilePicture($email)
{
  $googleProfilePicture = fetchGoogleProfilePicture($email);
  if (is_string($googleProfilePicture) && $googleProfilePicture !== '') {
    return $googleProfilePicture;
  }

  return fetchGravatarProfilePicture($email);
}

function register($postData, $files = [])
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  $isXhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

  // Allow registration without auth when no users exist (fresh install)
  $allowUnauthenticated = false;
  try {
    $checkPdo = connectToDatabase();
    $checkStmt = $checkPdo->query("SELECT COUNT(*) FROM users");
    $userCount = (int) $checkStmt->fetchColumn();
    closeConnection($checkPdo);
    if ($userCount === 0) {
      $allowUnauthenticated = true;
    }
  } catch (Exception $e) {
    $allowUnauthenticated = true;
  }

  if (!$allowUnauthenticated && !checkAdmin()) {
    if ($isXhr) {
      http_response_code(401);
      echo json_encode(['success' => false, 'message' => 'Unauthorized']);
      exit;
    }
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  $pdo = connectToDatabase();

  if (empty($postData['email']) || empty($postData['password']) || empty($postData['name']) || empty($postData['role'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
  }

  $email = strtolower(trim((string) $postData['email']));
  $password = $postData['password'];
  $name = $postData['name'];
  $family_name = $postData['family_name'] ?? '';
  $role = $postData['role'];
  $tags = !empty($postData['tags']) ? json_decode($postData['tags'], true) : [];
  $groups = !empty($postData['groups']) ? json_decode($postData['groups'], true) : [];
  $uploadError = null;
  $uploadedPicture = saveUploadedUserPicture($files['picture_file'] ?? null, $uploadError);
  $profilePictureUrl = $uploadedPicture ?: resolveFallbackProfilePicture($email);

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
  }

  $sql = 'SELECT * FROM Users WHERE LOWER(email) = LOWER(:email)';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['email' => $email]);
  $user = $stmt->fetch();
  if ($user) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User with this email already exists']);
    exit();
  }

  // Hash the password
  $saltRounds = 10;
  $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $saltRounds]);

  // Creator/modifier — may not exist during fresh install
  $creatorId = $_SESSION['user']['id'] ?? null;

  // Prepare SQL query
  $columns = ['name', 'family_name', 'role', 'email', 'password', 'picture', 'creator', 'created_at', 'modifier', 'modified_at', 'mode', 'view', '`limit`'];
  $placeholders = implode(', ', array_fill(0, count($columns), '?'));
  $sql = "INSERT INTO Users (" . implode(', ', $columns) . ") VALUES ($placeholders)";

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $family_name, $role, $email, $hash, $profilePictureUrl, $creatorId, date('Y-m-d H:i:s'), $creatorId, date('Y-m-d H:i:s'), 'dark', 'list', 10]);
    $userId = $pdo->lastInsertId();

    // Handle tags
    if (!empty($tags)) {
      foreach ($tags as $tag) {
        $sql = "SELECT * FROM tags WHERE LOWER(title) = LOWER(:tag)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tag' => $tag]);
        $tagData = $stmt->fetch();

        $tagId = null;

        if ($tagData) {
          $tagId = $tagData['id'];
        } else {
          $sql = "INSERT INTO tags (title) VALUES (:tag)";
          $stmt = $pdo->prepare($sql);
          $stmt->execute(['tag' => ucfirst($tag)]);
          $tagId = $pdo->lastInsertId();
        }

        $sql = 'SELECT * FROM users_tags WHERE user_id = :userId AND tag_id = :tagId';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          'userId' => $userId,
          'tagId' => $tagId
        ]);
        $userTag = $stmt->fetch();

        if (!$userTag) {
          $sql = "INSERT INTO users_tags (user_id, tag_id) VALUES (:userId, :tagId)";
          $stmt = $pdo->prepare($sql);
          $stmt->execute(['userId' => $userId, 'tagId' => $tagId]);
        }
      }
    }

    // Handle groups
    if (!empty($groups)) {
      foreach ($groups as $group) {
        $sql = "SELECT * FROM groups WHERE LOWER(title) = LOWER(:group)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['group' => $group]);
        $groupData = $stmt->fetch();
        $groupId = null;

        if ($groupData) {
          $groupId = $groupData['id'];
        } else {
          $sql = "INSERT INTO groups (title) VALUES (:group)";
          $stmt = $pdo->prepare($sql);
          $stmt->execute(['group' => ucfirst($group)]);
          $groupId = $pdo->lastInsertId();
        }

        $sql = 'SELECT * FROM users_groups WHERE user_id = :userId AND group_id = :groupId';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          'userId' => $userId,
          'groupId' => $groupId
        ]);
        $userGroup = $stmt->fetch();

        if (!$userGroup) {
          $sql = "INSERT INTO users_groups (user_id, group_id) VALUES (:userId, :groupId)";
          $stmt = $pdo->prepare($sql);
          $stmt->execute(['userId' => $userId, 'groupId' => $groupId]);
        }
      }
    }

    $redirectPath = '/users';

    // Fresh install: auto-login as the first created admin so setup can continue to /links.
    if ($allowUnauthenticated) {
      $createdUserStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
      $createdUserStmt->execute(['id' => (int) $userId]);
      $createdUser = $createdUserStmt->fetch(PDO::FETCH_ASSOC);

      if ($createdUser) {
        $_SESSION['user'] = $createdUser;

        $groupsStmt = $pdo->prepare('SELECT groups.id, groups.title FROM users_groups
          INNER JOIN groups ON users_groups.group_id = groups.id
          WHERE users_groups.user_id = :id');
        $groupsStmt->execute(['id' => (int) $userId]);
        $sessionGroups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($sessionGroups) {
          $_SESSION['groups'] = $sessionGroups;
        } else {
          unset($_SESSION['groups']);
        }

        if (function_exists('issueUserDeviceSession')) {
          issueUserDeviceSession($createdUser);
        }

        $redirectPath = '/links';
      }
    }

    if (is_string($uploadError) && trim($uploadError) !== '') {
      $_SESSION['ui_notice'] = $uploadError . ' Fallback profile image was used.';
      $_SESSION['ui_notice_type'] = 'warning';
    }

    if ($isXhr) {
      echo json_encode([
        'success' => true,
        'redirect' => $redirectPath,
      ]);
    } else {
      header('Location: ' . $redirectPath);
    }
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to insert user: ' . $e->getMessage()]);
  }

  closeConnection($pdo);
}

function fetchGravatarProfilePicture($email)
{
  $emailHash = md5(strtolower(trim((string) $email)));
  return "https://www.gravatar.com/avatar/$emailHash?d=mp&s=256";
}
