<!DOCTYPE html>
<?php

if (!checkAdmin()) {
  ob_start();
  header('Location: /');
  ob_end_flush();
  exit;
}

$page = 'archive';


include 'components/navigation.php';

$data = getArchive();
$shortlinkDisplayBase = isset($shortlinkBaseUrl) ? $shortlinkBaseUrl : ('https://' . $domain);

?>

<html lang="<?= htmlspecialchars(uiLocale(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <title><?= $titles['links']['title'] ?></title>
  <link rel="stylesheet" href="css/custom.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/modal.css" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="css/modal.css">
  </noscript>
  <link rel="stylesheet" href="css/keyboard.css" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="css/keyboard.css">
  </noscript>
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="stylesheet" href="css/material-icons.css">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name='description' content='Just a random description of my website.'>
</head>

<body>
  <div class="container" style="margin-top: 80px;">
    <?php if (count($data) > 0) { ?>
      <?php foreach ($data as $entry) { ?>
        <?php if ($entry['table_name'] === 'links') { ?>
          <div class="outerLinkContainer">
            <div class="linkContainer">
              <h3><?= $entry['title'] ?></h3>
              <p><?= $entry['url'] ?></p>
              <p><?= $shortlinkDisplayBase . '/' . $entry['shortlink'] ?></p>
              <p><?= htmlspecialchars(uiText('archive.deleted_by', 'Deleted by:'), ENT_QUOTES, 'UTF-8') ?> <?= getUserById($entry['modifier'])['email'] ?></p>
              <p><?= htmlspecialchars(uiText('archive.deleted_at', 'Deleted at:'), ENT_QUOTES, 'UTF-8') ?> <?= $entry['modified_at'] ?></p>
              <a href="/restore?comp=links&id=<?= $entry['table_id'] ?>"><button class="submitButton"><?= htmlspecialchars(uiText('archive.recover', 'Recover'), ENT_QUOTES, 'UTF-8') ?></button></a>
            </div>
          </div>
        <?php } else if ($entry['table_name'] === 'visitors') { ?>

        <?php } else if ($entry['table_name'] === 'users') { ?>

        <?php } ?>
      <?php } ?>
    <?php } else { ?>
      <h3><?= htmlspecialchars(uiText('archive.empty', 'Such empty...'), ENT_QUOTES, 'UTF-8') ?></h3>
    <?php } ?>
  </div>
</body>

</html>