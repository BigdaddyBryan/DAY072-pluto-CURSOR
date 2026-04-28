<!DOCTYPE html>
<?php
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

checkUser();

if (!checkAdmin()) {
  header('Location: /');
  exit;
}

updateLastLogin($_SESSION['user']['id']);

$page = 'users';
$users = getAllUsers();

$activeDeviceSessionCounts = [];
$latestDeviceSeenTimestamps = [];
$deviceStore = loadDeviceSessionsStore();
if (isset($deviceStore['sessions']) && is_array($deviceStore['sessions'])) {
  foreach ($deviceStore['sessions'] as $sessionData) {
    if (!is_array($sessionData) || !empty($sessionData['revoked'])) {
      continue;
    }

    $sessionUserId = (string) ($sessionData['user_id'] ?? '');
    if ($sessionUserId === '') {
      continue;
    }

    if (!isset($activeDeviceSessionCounts[$sessionUserId])) {
      $activeDeviceSessionCounts[$sessionUserId] = 0;
    }
    $activeDeviceSessionCounts[$sessionUserId]++;

    $lastSeenAt = (int) ($sessionData['last_seen_at'] ?? 0);
    if ($lastSeenAt > 0 && (!isset($latestDeviceSeenTimestamps[$sessionUserId]) || $lastSeenAt > $latestDeviceSeenTimestamps[$sessionUserId])) {
      $latestDeviceSeenTimestamps[$sessionUserId] = $lastSeenAt;
    }
  }
}

include 'components/navigation.php';

?>
<html lang="<?= htmlspecialchars(uiLocale(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <title><?= $titles['users']['title'] ?></title>
  <link rel="stylesheet" href="css/custom.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/modal.css" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="css/modal.css">
  </noscript>
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="stylesheet" href="css/material-icons.css">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="page-users">
  <?php $data = $users;
  include 'components/search.php'; ?>
  <input type="hidden" id="page" value="users">
  <div class="newUserContainer">
    <button class="newUserButton" id="createUser" onclick="createModal('/createModal?comp=users')"><i
        class="material-icons addLink">add</i></button>
  </div>

  <div class="usersContainer" id="usersContainer">
    <?php foreach ($users as $user) {
      include 'components/userContainer.php';
    } ?>
  </div>
</body>
<script src="/javascript/script.js?v=<?= $version ?>" defer></script>
<script src="/javascript/users.js?v=<?= $version ?>" defer></script>
<script src="/javascript/keyboard.js?v=<?= $version ?>" defer></script>

</html>