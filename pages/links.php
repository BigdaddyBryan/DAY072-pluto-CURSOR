<!DOCTYPE html>
<?php

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

checkUser();

$page = 'links';
$role = $_SESSION['user']['role'] ?? '';
if (isset($_GET['group']) || $role === 'limited') {
  if ($role === 'limited') {
    $links = [];
    $sessionGroups = $_SESSION['groups'] ?? [];
    foreach ($sessionGroups as $group) {
      if (!isset($group['title'])) {
        continue;
      }
      $groupLinks = getLinksByGroup($group['title']);
      $links = array_merge($links, $groupLinks);
    }
  } else {
    $links = getLinksByGroup($_GET['group']);
  }
} else if (isset($_GET['link_id'])) {
  $linkId = filter_input(INPUT_GET, 'link_id', FILTER_VALIDATE_INT);
  if ($linkId && $linkId > 0) {
    $linkData = getLinkById($linkId);
    $links = $linkData ? [$linkData] : [];
  } else {
    $links = [];
  }
} else {
  $links = getLinks();
}

$open = false;
$openId = null;
if (isset($_GET['open'])) {
  $open = true;
  $openCandidate = filter_input(INPUT_GET, 'open', FILTER_VALIDATE_INT);
  $openId = ($openCandidate && $openCandidate > 0) ? $openCandidate : null;
}

$requestUri = $_SERVER['REQUEST_URI'];
$_SESSION['last_visited_page'] = $requestUri;

updateLastLogin($_SESSION['user']['id']);

checkBackup($domain);
// echo "<script>console.log('Debug Objects: ', " . json_encode($links, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ");</script>";
include 'components/navigation.php';

?>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="color-scheme" content="light dark">
  <style>
    html,
    body {
      background-color: #f4f7fb;
    }
  </style>
  <script>
    (function() {
      var preference = "light";
      try {
        preference = localStorage.getItem("themePreference") || "light";
      } catch (error) {}

      var resolvedTheme = preference;
      if (resolvedTheme === "system") {
        try {
          resolvedTheme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        } catch (error) {
          resolvedTheme = "light";
        }
      }

      if (resolvedTheme === "dark") {
        document.documentElement.style.backgroundColor = "#0b1220";
      } else {
        document.documentElement.style.backgroundColor = "#f4f7fb";
      }
      document.documentElement.style.colorScheme = resolvedTheme === "dark" ? "dark" : "light";
    })();
  </script>
  <title><?= $titles['links']['title'] ?></title>
  <link rel="stylesheet" href="css/custom.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="stylesheet" href="css/material-icons.css">
  <link rel="stylesheet" href="css/modal.css" media="print" onload="this.media='all'">
  <link rel="stylesheet" href="css/keyboard.css" media="print" onload="this.media='all'">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name='description' content='Just a random description of my website.'>
</head>

<body class="page-links" data-user-role="<?= $role ?>">
  <?php
  if (isset($_GET['group'])) {
    $groupTitle = $_GET['group'];
    $data = $links;
    include 'components/search.php';
  ?>
    <div class="groupHeader groupHeaderUnified">
      <h2><?= htmlspecialchars($groupTitle) ?></h2>
      <a href="/links" class="returnButton returnButtonUnified">
        <i class="material-icons returnButtonIcon">arrow_back</i>
        <span><?= htmlspecialchars(uiText('links.return_to_links', 'Return to Links'), ENT_QUOTES, 'UTF-8') ?></span>
      </a>
    </div>
  <?php
  } elseif ($role === 'limited') {
    // $group = getGroupByTitle($_GET['group']);
    // include 'components/groupHeader.php';
  } else {
    $data = $links;
    include 'components/search.php';
  }
  ?>
  <input type="hidden" id="page" value="links">
  <div class="newLinkContainer">
    <button class="rocketContainer newLinkButton" id="rocketContainer" onclick="scrollUp(this)"><i
        class="day-icons rocket">&#xf0463;</i></button>
    <?php if (checkAdmin()) { ?>
      <button class="newLinkButton" id="createLink" onclick="createModal('/createModal?comp=links')"><i
          class="material-icons addLink">add</i></button>
    <?php } ?>
  </div>

  <div class="linksContainer" id="linksContainer">
    <?php foreach ($links as $link) {
      include 'components/linkContainer.php';
    } ?>

  </div>
  <div class="infiniteScrollSentinel" id="infiniteScrollSentinel">
    <div class="infiniteScrollSpinner" id="infiniteScrollSpinner"></div>
  </div>
  <div class="linksContainer">
    <?php if (!isset($_GET['group']) && $role !== 'limited') { ?>
      <div class="shownContainer bottomPage">
        <div class="pageContainer">
          <button class="pageButton" data-action="first"><i class="material-icons">keyboard_double_arrow_left</i></button>
          <button class="pageButton" data-action="previous"><i class="material-icons">chevron_left</i></button>
          <button class="pageButton page activePage" data-page="0">1</button>
          <button class="pageButton page" data-page="1">2</button>
          <button class="pageButton page" data-page="2">3</button>
          <button class="pageButton" data-action="next"><i class="material-icons">chevron_right</i></button>
          <button class="pageButton" data-action="last"><i class="material-icons">keyboard_double_arrow_right</i></button>
        </div>
      </div>
    <?php } ?>
  </div>

  <script src="/javascript/links.js?v=<?= $version ?>" defer></script>
  <script src="/javascript/script.js?v=<?= $version ?>" defer></script>
  <script src="/javascript/keyboard.js?v=<?= $version ?>" defer></script>
</body>

</html>