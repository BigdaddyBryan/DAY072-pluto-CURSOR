<?php

function syncCurrentUserSessionFromDatabase()
{
  $userId = (int) ($_SESSION['user']['id'] ?? 0);
  if ($userId <= 0) {
    return;
  }

  $pdo = connectToDatabase();
  $sql = 'SELECT * FROM users WHERE id = :id LIMIT 1';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $userId]);
  $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);
  closeConnection($pdo);

  if (is_array($freshUser) && !empty($freshUser)) {
    $_SESSION['user'] = $freshUser;
  }
}

function profileIsAjaxRequest()
{
  $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
  $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  return strpos($accept, 'application/json') !== false || $requestedWith === 'xmlhttprequest';
}

function profileRespondWithNotice($message, $type = 'info', $statusCode = 200, $extra = [])
{
  if (profileIsAjaxRequest()) {
    http_response_code((int) $statusCode);
    header('Content-Type: application/json');
    $payload = [
      'success' => $type !== 'error',
      'message' => (string) $message,
      'type' => (string) $type,
    ];

    if (is_array($extra) && !empty($extra)) {
      $payload = array_merge($payload, $extra);
    }

    echo json_encode($payload);
    exit;
  }

  profileRedirectWithNotice($message, $type);
}

function profileRedirectWithNotice($message, $type = 'info')
{
  $_SESSION['ui_notice'] = (string) $message;
  $_SESSION['ui_notice_type'] = (string) $type;
  header('Location: /profile');
  exit;
}

function profileIsOneTimeSession()
{
  $userId = $_SESSION['user']['id'] ?? null;
  return $userId === 'tempUser'
    || !empty($_SESSION['user']['oneTimeToken'])
    || !empty($_SESSION['user']['accessLinkToken']);
}

function updateProfileIdentity($postData)
{
  checkUser();

  if (profileIsOneTimeSession()) {
    profileRedirectWithNotice('Profile editing is disabled during access-link sessions.', 'error');
  }

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    include __DIR__ . '/../../pages/errors/404.php';
    return;
  }

  $name = trim((string) ($postData['name'] ?? ''));
  $familyName = trim((string) ($postData['family_name'] ?? ''));

  if ($name === '' || $familyName === '') {
    profileRedirectWithNotice('First and last name are required.', 'error');
  }

  if (mb_strlen($name) > 80 || mb_strlen($familyName) > 80) {
    profileRedirectWithNotice('Name fields are too long.', 'error');
  }

  $pdo = connectToDatabase();
  $sql = 'UPDATE users SET name = :name, family_name = :family_name, modifier = :modifier, modified_at = :modified_at WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'name' => $name,
    'family_name' => $familyName,
    'modifier' => $_SESSION['user']['id'],
    'modified_at' => date('Y-m-d H:i:s'),
    'id' => $_SESSION['user']['id'],
  ]);
  closeConnection($pdo);
  syncCurrentUserSessionFromDatabase();

  profileRedirectWithNotice('Profile details updated.', 'success');
}

function updateProfileTheme($postData)
{
  checkUser();

  if (profileIsOneTimeSession()) {
    profileRedirectWithNotice('Profile editing is disabled during access-link sessions.', 'error');
  }

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    include __DIR__ . '/../../pages/errors/404.php';
    return;
  }

  $mode = strtolower(trim((string) ($postData['mode'] ?? '')));
  if (!in_array($mode, ['light', 'dark', 'system'], true)) {
    profileRedirectWithNotice('Invalid theme selection.', 'error');
  }

  $pdo = connectToDatabase();
  $sql = 'UPDATE users SET mode = :mode, modifier = :modifier, modified_at = :modified_at WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'mode' => $mode,
    'modifier' => $_SESSION['user']['id'],
    'modified_at' => date('Y-m-d H:i:s'),
    'id' => $_SESSION['user']['id'],
  ]);
  closeConnection($pdo);
  syncCurrentUserSessionFromDatabase();
  profileRedirectWithNotice('Theme updated.', 'success');
}

function updateProfilePassword($postData)
{
  checkUser();

  if (profileIsOneTimeSession()) {
    profileRespondWithNotice('Profile editing is disabled during access-link sessions.', 'error', 403, ['code' => 'ONE_TIME_READ_ONLY']);
  }

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    include __DIR__ . '/../../pages/errors/404.php';
    return;
  }

  $currentPassword = (string) ($postData['current_password'] ?? '');
  $newPassword = (string) ($postData['new_password'] ?? '');
  $confirmPassword = (string) ($postData['confirm_password'] ?? '');

  if ($newPassword === '' || $confirmPassword === '') {
    profileRespondWithNotice('New password and confirmation are required.', 'error', 422, ['code' => 'PASSWORD_REQUIRED']);
  }

  if ($newPassword !== $confirmPassword) {
    profileRespondWithNotice('New passwords do not match.', 'error', 422, ['code' => 'PASSWORD_MISMATCH']);
  }

  if (strlen($newPassword) < 8) {
    profileRespondWithNotice('Password must be at least 8 characters long.', 'error', 422, ['code' => 'PASSWORD_TOO_SHORT']);
  }

  $pdo = connectToDatabase();
  $sql = 'SELECT password FROM users WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $_SESSION['user']['id']]);
  $existingHash = (string) ($stmt->fetchColumn() ?? '');

  if ($existingHash !== '') {
    if ($currentPassword === '') {
      closeConnection($pdo);
      profileRespondWithNotice('Current password is required.', 'error', 422, ['code' => 'CURRENT_PASSWORD_REQUIRED']);
    }

    if (!password_verify($currentPassword, $existingHash)) {
      closeConnection($pdo);
      profileRespondWithNotice('Current password is incorrect.', 'error', 422, ['code' => 'CURRENT_PASSWORD_INCORRECT']);
    }
  }

  $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
  $sql = 'UPDATE users SET password = :password, modifier = :modifier, modified_at = :modified_at WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'password' => $newHash,
    'modifier' => $_SESSION['user']['id'],
    'modified_at' => date('Y-m-d H:i:s'),
    'id' => $_SESSION['user']['id'],
  ]);

  closeConnection($pdo);
  syncCurrentUserSessionFromDatabase();
  profileRespondWithNotice('Password updated successfully.', 'success');
}

function updateProfilePhoto($files)
{
  checkUser();

  if (profileIsOneTimeSession()) {
    profileRedirectWithNotice('Profile editing is disabled during access-link sessions.', 'error');
  }

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    include __DIR__ . '/../../pages/errors/404.php';
    return;
  }

  if (!isset($files['profile_photo']) || !is_array($files['profile_photo'])) {
    profileRedirectWithNotice('No profile photo selected.', 'error');
  }

  $photo = $files['profile_photo'];

  if (($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($photo['tmp_name'])) {
    profileRedirectWithNotice('Failed to upload profile photo.', 'error');
  }

  if (($photo['size'] ?? 0) > 5 * 1024 * 1024) {
    profileRedirectWithNotice('Profile photo must be smaller than 5MB.', 'error');
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = $finfo ? finfo_file($finfo, $photo['tmp_name']) : '';
  if ($finfo) {
    finfo_close($finfo);
  }

  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
  ];

  if (!isset($allowed[$mimeType])) {
    profileRedirectWithNotice('Unsupported image type. Use JPG, PNG, GIF, or WEBP.', 'error');
  }

  $directory = __DIR__ . '/../../public/custom/images/profiles';
  if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    profileRedirectWithNotice('Profile image folder is not available.', 'error');
  }

  $extension = $allowed[$mimeType];
  $newFileName = 'profile-' . $_SESSION['user']['id'] . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
  $targetPath = $directory . '/' . $newFileName;

  if (!move_uploaded_file($photo['tmp_name'], $targetPath)) {
    profileRedirectWithNotice('Could not save profile photo.', 'error');
  }

  $newPictureUrl = '/custom/images/profiles/' . $newFileName;

  $pdo = connectToDatabase();
  $sql = 'SELECT picture FROM users WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $_SESSION['user']['id']]);
  $previousPicture = (string) ($stmt->fetchColumn() ?? '');

  $sql = 'UPDATE users SET picture = :picture, modifier = :modifier, modified_at = :modified_at WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'picture' => $newPictureUrl,
    'modifier' => $_SESSION['user']['id'],
    'modified_at' => date('Y-m-d H:i:s'),
    'id' => $_SESSION['user']['id'],
  ]);
  closeConnection($pdo);
  syncCurrentUserSessionFromDatabase();

  if (strpos($previousPicture, '/custom/images/profiles/') === 0) {
    $previousPath = __DIR__ . '/../../public' . $previousPicture;
    if (is_file($previousPath)) {
      @unlink($previousPath);
    }
  }

  profileRedirectWithNotice('Profile photo updated.', 'success');
}
