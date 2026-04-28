<!DOCTYPE html>
<?php

checkUser();

if ($_SESSION['user']['role'] === 'viewer') {
  header('Location: /');
  exit;
}

updateLastLogin($_SESSION['user']['id']);

$groups = getAllGroups('all');
$page = 'groups';
$limit = isset($_SESSION['user']['limit']) ? (int) $_SESSION['user']['limit'] : 10;

include 'components/navigation.php';
?>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $titles['groups']['title'] ?></title>
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
  <meta name='description' content='Just a random description of my website.'>
  <style>
  </style>
</head>

<body class="page-groups">
  <script>
    (function() {
      try {
        document.body.classList.add("page-groups");
        var storedMode = localStorage.getItem("groupsViewMode") || "detailed";
        var allowed = ["compact", "detailed", "thumbnail"];
        var mode = allowed.indexOf(storedMode) >= 0 ? storedMode : "detailed";
        document.body.classList.add("mode-" + mode);
      } catch (e) {}
      try {
        var modernSize = localStorage.getItem("groupsThumbSize");
        var legacySize = localStorage.getItem("groups_thumbnail_size");
        var s = parseInt(modernSize || legacySize, 10);
        if (s >= 220 && s <= 420) document.body.style.setProperty("--group-thumb-size", s + "px");

        if (!modernSize && legacySize) {
          localStorage.setItem("groupsThumbSize", String(s));
        }
      } catch (e) {}
    })();
  </script>
  <?php
  $data = $groups;
  include 'components/search.php';
  ?>
  <input type="hidden" id="page" value="groups">
  <div class="newLinkContainer">
    <button class="rocketContainer newLinkButton" id="rocketContainer" onclick="scrollUp(this)">
      <i class="day-icons rocket">&#xf0463;</i>
    </button>
    <?php if (checkAdmin()) { ?>
      <button class="newLinkButton" id="createLink" onclick="createModal('/createModal?comp=groups')"><i
          class="material-icons addLink">add</i></button>
    <?php } ?>
  </div>

  <div class="linksContainer" id="groupsContainer">
    <?php foreach ($groups as $group) {
      include 'components/groupContainer.php';
    } ?>
  </div>

  <div class="linksContainer">
    <div class="shownContainer bottomPage " <?= count($groups) > $limit ? '' : ' style="display:none"' ?>>
      <div class="pageContainer" <?= count($groups) > $limit ? '' : ' style="display:none"' ?>>
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

</body>
<script src="/javascript/script.js?v=<?= $version ?>" defer></script>
<script src="/javascript/keyboard.js?v=<?= $version ?>" defer></script>
<script src="/javascript/groups.js?v=<?= $version ?>" defer></script>

</html>