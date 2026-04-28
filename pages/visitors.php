<!DOCTYPE html>
<?php

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

checkuser();
$page = 'visitors';
$limit = isset($_SESSION['user']['limit']) ? (int) $_SESSION['user']['limit'] : 10;

updateLastLogin($_SESSION['user']['id']);

include 'components/navigation.php';
if ($_SESSION['user']['role'] === 'limited') {
  $visitors = [];
  foreach ($_SESSION['groups'] as $group) {
    $groupVisitors = getVisitorsByGroup($group['title']);
    $visitors = array_merge($visitors, $groupVisitors);
  }
} else if (isset($_GET['visitor_id'])) {
  $visitors = getVisitorById($_GET['visitor_id']);
  $visitors = [$visitors];
} else {
  $visitors = getVisitors();
}
$data = $visitors;
?>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $titles['visitors']['title'] ?></title>
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

<body class="page-visitors">
  <input type="hidden" id="page" value="visitors">
  <?php
  if ($_SESSION['user']['role'] === 'limited') {
  } else {
    include 'components/search.php';
  }
  ?>

  <div class="visitorsContainer" id="visitorsContainer">
    <?php foreach ($visitors as $visitor) {
      include 'components/visitorContainer.php';
    } ?>
  </div>
  <div class="visitorsContainer">
    <div class="shownContainer bottomPage" <?= count($visitors) > $limit ? '' : ' style="display:none"' ?>>
      <div class="pageContainer" <?= count($visitors) > $limit ? '' : ' style="display:none"' ?>>
        <button class="pageButton" data-action="first"><i class="material-icons">keyboard_double_arrow_left</i></button>
        <button class="pageButton" data-action="previous"><i class="material-icons">chevron_left</i></button>
        <button class="pageButton page activePage" data-page="0">1</button>
        <button class="pageButton page" data-page="1">2</button>
        <button class="pageButton page" data-page="2">3</button>
        <button class="pageButton" data-action="next"><i class="material-icons">chevron_right</i></button>
        <button class="pageButton" data-action="last"><i class="material-icons">keyboard_double_arrow_right</i></button>
      </div>
    </div>
  </div>

  <div class="newLinkContainer">
    <button class="rocketContainer newLinkButton" id="rocketContainer" onclick="scrollUp(this)"><i
        class="day-icons rocket">&#xf0463;</i></button>
  </div>

</body>
<script src="/javascript/visitors.js?v=<?= $version ?>" defer></script>
<script src="/javascript/script.js?v=<?= $version ?>" defer></script>
<script src="/javascript/keyboard.js?v=<?= $version ?>" defer></script>

</html>