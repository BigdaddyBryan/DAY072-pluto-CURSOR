<!DOCTYPE html>
<?php

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (!checkAdmin()) {
  ob_start();
  header('Location: /');
  ob_end_flush();
  exit;
}

if (!$_SERVER['REQUEST_METHOD'] === 'GET') {
  header('Location: /visitors');
}
$page = 'visits';

include 'components/navigation.php';

$visitorId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$visitorId || $visitorId <= 0) {
  include __DIR__ . '/errors/404.php';
  exit;
}

$visits = getVisitsByVisitor($visitorId);
$data = $visits;
?>
<html lang="<?= htmlspecialchars(uiLocale(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <title><?= $titles['visits']['title'] ?></title>
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
  <style>
    /* Timeline CSS */
    .timeline {
      position: relative;
      max-width: 1200px;
      margin: 0 auto;
    }

    .timeline::after {
      content: '';
      position: absolute;
      width: 6px;
      background-color: var(--background-color);
      top: 0;
      bottom: 0;
      left: 50%;
      margin-left: -3px;
    }

    .timeline-entry {
      padding: 10px 40px;
      position: relative;
      background-color: inherit;
      width: 50%;
    }

    .timeline-entry::after {
      content: '';
      position: absolute;
      width: 25px;
      height: 25px;
      right: -17px;
      background-color: var(--background-color-light);
      border: 4px solid var(--background-color);
      top: 15px;
      border-radius: 50%;
      z-index: 1;
    }

    .left {
      left: 0;
    }

    .right {
      left: 50%;
    }

    .left::before {
      content: " ";
      height: 0;
      position: absolute;
      top: 22px;
      width: 0;
      z-index: 1;
      right: 30px;
      border: medium solid var(--background-color);
      border-width: 10px 0 10px 10px;
      border-color: transparent transparent transparent var(--background-color);
    }

    .right::before {
      content: " ";
      height: 0;
      position: absolute;
      top: 22px;
      width: 0;
      z-index: 1;
      left: 30px;
      border: medium solid var(--background-color);
      border-width: 10px 10px 10px 0;
      border-color: transparent var(--background-color) transparent transparent;
    }

    .right::after {
      left: -16px;
    }

    .content {
      padding: 20px 30px;
      background-color: var(--background-color);
      position: relative;
      border-radius: 6px;
      padding-top: 50px;
    }

    .left .content .date {
      position: absolute;
      right: 10px;
      top: 10px;
      font-size: 16px;
      font-weight: 700;
      color: var(--text-color);
    }

    .right .content .date {
      position: absolute;
      left: 10px;
      top: 10px;
      font-size: 16px;
      font-weight: 700;
      color: var(--text-color);
    }

    @media (max-width: 768px) {
      .timeline-entry {
        width: 100%;
        text-align: right;
        position: relative;
      }

      .left::before,
      .right::before {
        display: none;
      }

      .right,
      .left {
        left: 10px;
      }

      .timeline::after {
        left: 19px;
        width: 6px;
      }

      .timeline-entry-content {
        margin-right: 0;
        margin-left: 30px;
        /* Adjust this value to provide space for the dot */
        text-align: right;
      }

      .timeline-entry::after {
        width: 15px;
        height: 15px;
        left: 0;
        /* Align the dot to the left border */
        top: 15px;
        border-width: 2px;
        right: auto;
      }

      .timeline .date {
        left: 10px;
      }

      .timeline .location {
        text-align: right;
      }

      .timeline {
        padding-bottom: 100px;
      }
    }
  </style>
</head>

<body>
  <div class="timeline">
    <?php foreach ($data as $index => $entry) { ?>
      <div class="timeline-entry <?php echo $index % 2 == 0 ? 'left' : 'right'; ?>">
        <div class="content">
          <h2 class="date"><?= htmlspecialchars((string) $entry['date'], ENT_QUOTES, 'UTF-8') ?></h2>
          <?php $link = getLinkById($entry['link_id']); ?>
          <span class="location sameLine">
            <?php if ($link): ?>
              <i class="material-icons">link</i>
              <a href="/?link_id=<?= urlencode((string) $entry['link_id']) ?>"><?= htmlspecialchars((string) $link['title'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
              <span><?= htmlspecialchars(uiText('visits.unknown_deleted', 'Unknown - Deleted'), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          </span>
        </div>
      </div>
    <?php } ?>
  </div>
</body>
<script src="/javascript/visitors.js?v=<?= $version ?>" defer></script>
<script src="/javascript/script.js?v=<?= $version ?>" defer></script>

</html>