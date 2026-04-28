<!DOCTYPE html>
<?php

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (!checkAdmin()) {
  header('Location: /');
}
$page = 'statistics';
include 'components/navigation.php';

$statsId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$statsId || $statsId <= 0) {
  include __DIR__ . '/errors/404.php';
  exit;
}

$data = getLinkById($statsId);
if (!$data) {
  include __DIR__ . '/errors/404.php';
  exit;
}

function parseDbTimestamp($value)
{
  if ($value === null) {
    return null;
  }

  $raw = trim((string) $value);
  if ($raw === '') {
    return null;
  }

  $lower = strtolower($raw);
  if (
    $lower === 'current_timestamp'
    || $lower === 'now'
    || $lower === "datetime('now')"
    || $lower === "datetime('now','localtime')"
  ) {
    return null;
  }

  $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
  foreach ($formats as $format) {
    $dateTime = DateTimeImmutable::createFromFormat($format, $raw);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
    if ($dateTime !== false && !$hasErrors) {
      return $dateTime->getTimestamp();
    }
  }

  if (!preg_match('/\d/', $raw)) {
    return null;
  }

  $timestamp = strtotime($raw);
  return $timestamp === false ? null : $timestamp;
}

function detectVisitorDevice($browserValue)
{
  $raw = trim((string) $browserValue);
  if ($raw === '') {
    return ['label' => 'Unknown', 'icon' => 'devices_other'];
  }

  $normalized = strtolower($raw);

  if (strpos($normalized, 'bot') !== false || strpos($normalized, 'spider') !== false || strpos($normalized, 'crawler') !== false) {
    return ['label' => 'Bot', 'icon' => 'smart_toy'];
  }

  if (
    strpos($normalized, 'iphone') !== false ||
    strpos($normalized, 'android') !== false ||
    strpos($normalized, 'mobile') !== false
  ) {
    return ['label' => 'Mobile', 'icon' => 'smartphone'];
  }

  if (
    strpos($normalized, 'ipad') !== false ||
    strpos($normalized, 'tablet') !== false
  ) {
    return ['label' => 'Tablet', 'icon' => 'tablet_mac'];
  }

  return ['label' => 'Desktop', 'icon' => 'desktop_windows'];
}

// Sort visits by date in descending order (latest first)
usort($data['visits'], function ($a, $b) {
  $timeA = parseDbTimestamp($a['date']) ?? 0;
  $timeB = parseDbTimestamp($b['date']) ?? 0;
  return $timeB - $timeA;
});

$visitorVisitCounts = [];
foreach ($data['visits'] as $visit) {
  if (!isset($visitorVisitCounts[$visit['visitor_id']])) {
    $visitorVisitCounts[$visit['visitor_id']] = 0;
  }
  $visitorVisitCounts[$visit['visitor_id']]++;
}

// Find the visitor with the most visits
$mostVisitsVisitorId = null;
$mostVisitsVisitor = null;
$mostVisitsCount = 0;
if (!empty($visitorVisitCounts)) {
  $mostVisitsVisitorId = array_keys($visitorVisitCounts, max($visitorVisitCounts))[0];
  $mostVisitsVisitor = getVisitorById($mostVisitsVisitorId);
  $mostVisitsCount = $visitorVisitCounts[$mostVisitsVisitorId] ?? 0;
}

$linkAuthor = $data['creator'] ?? null;
$linkAuthorDisplay = 'Unknown';
if ($linkAuthor !== null && $linkAuthor !== '') {
  $linkAuthorDisplay = is_numeric($linkAuthor)
    ? (getEmailById($linkAuthor) ?: ('User #' . $linkAuthor))
    : (string) $linkAuthor;
}

$deviceCount = [
  'Desktop' => 0,
  'Mobile' => 0,
  'Tablet' => 0,
  'Bot' => 0,
  'Unknown' => 0,
];

foreach ($data['visits'] as $visit) {
  $visitor = getVisitorById($visit['visitor_id']);
  if (!$visitor) {
    continue;
  }

  $device = detectVisitorDevice($visitor['browser'] ?? '');
  if (!isset($deviceCount[$device['label']])) {
    $deviceCount[$device['label']] = 0;
  }
  $deviceCount[$device['label']]++;
}

arsort($deviceCount);
$topDeviceLabel = key($deviceCount);
$topDeviceCount = (int) current($deviceCount);
$deviceIconMap = [
  'Desktop' => 'desktop_windows',
  'Mobile' => 'smartphone',
  'Tablet' => 'tablet_mac',
  'Bot' => 'smart_toy',
  'Unknown' => 'devices_other',
];
$topDeviceIcon = $deviceIconMap[$topDeviceLabel] ?? 'devices_other';
?>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $titles['statistics']['title'] ?></title>
  <link rel="stylesheet" href="css/custom.css">
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="/css/modal.css" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="/css/modal.css">
  </noscript>
  <link rel="stylesheet" href="/css/navigation.css">
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="stylesheet" href="/css/material-icons.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .visitsCount {
      display: flex;
      flex-direction: row;
      height: 100px;
      width: 100%;
      justify-content: space-around;
      margin: 25px 0;
    }

    .visitEntry {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-width: 100px;
      height: 100px;
      background-color: var(--background-color);
      border-radius: var(--border-radius);
      padding: 10px;
    }

    .statTags {
      margin: 5px;
    }

    @media only screen and (max-width: 600px) {
      .visitsCount {
        flex-direction: column;
        height: auto;
      }

      .visitEntry {
        width: 100%;
        margin: 5px 0;
      }
    }
  </style>
</head>

<body>
  <?php $shortlinkDisplayBase = isset($shortlinkBaseUrl) ? $shortlinkBaseUrl : ('https://' . $domain); ?>
  <button class="submitButton" style="margin: 10px;" onclick="javascript:history.back()">
    <?= htmlspecialchars(uiText('statistics.return', 'Return'), ENT_QUOTES, 'UTF-8') ?>
  </button>
  <div class="statsTitleContainer">
    <p class="statsHeader"><?= htmlspecialchars(uiText('statistics.statistics_for', 'Statistics for'), ENT_QUOTES, 'UTF-8') ?> <b><?= htmlspecialchars((string) $data['title'], ENT_QUOTES, 'UTF-8') ?></b></p>
  </div>
  <div class="statsContainer">
    <div class="createdDate">
      <p><?= htmlspecialchars(uiText('statistics.created_on', 'Created on:'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $data['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
      <p><?= htmlspecialchars(uiText('statistics.link_author', 'Link author:'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $linkAuthorDisplay, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <h2><?= htmlspecialchars((string) $data['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p><?= htmlspecialchars((string) $shortlinkDisplayBase, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) $data['shortlink'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><?= htmlspecialchars((string) $data['url'], ENT_QUOTES, 'UTF-8') ?></p>
    <br>
    <h3><?= htmlspecialchars(uiText('statistics.highlights', 'Highlights'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="visitsCount">
      <div class="visitEntry tooltip">
        <p><?= (int) ($data['visit_count'] ?? 0) ?></p>
        <i class="day-icons" style="font-size: 26px;">&#xf1867;</i>
        <span class="tooltiptext"><?= htmlspecialchars(uiText('statistics.total_visits', 'Total visits'), ENT_QUOTES, 'UTF-8') ?></span>
        <p class="mobileShow" style="font-size: 14px;"><?= htmlspecialchars(uiText('statistics.total_visits', 'Total visits'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="visitEntry tooltip">
        <p><?= count($data['visits'] ?? []) ?></p>
        <i class="day-icons" style="font-size: 26px;">&#xf0237;</i>
        <span class="tooltiptext"><?= htmlspecialchars(uiText('statistics.unique_visits', 'Unique visits'), ENT_QUOTES, 'UTF-8') ?></span>
        <p class="mobileShow" style="font-size: 14px;"><?= htmlspecialchars(uiText('statistics.unique_visits', 'Unique visits'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="visitEntry tooltip">
        <p><?= count($data['visitsToday'] ?? []) ?></p>
        <i class="day-icons" style="font-size: 26px;">&#xf00f6;</i>
        <span class="tooltiptext"><?= htmlspecialchars(uiText('statistics.visits_today', 'Visits today'), ENT_QUOTES, 'UTF-8') ?></span>
        <p class="mobileShow" style="font-size: 14px;"><?= htmlspecialchars(uiText('statistics.visits_today', 'Visits today'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="visitEntry tooltip">
        <p><?= htmlspecialchars((string) $topDeviceLabel, ENT_QUOTES, 'UTF-8') ?> (<?= $topDeviceCount ?>)</p>
        <i class="material-icons" style="font-size: 26px;"><?= htmlspecialchars((string) $topDeviceIcon, ENT_QUOTES, 'UTF-8') ?></i>
        <span class="tooltiptext"><?= htmlspecialchars(uiText('statistics.most_used_device_type', 'Most used device type'), ENT_QUOTES, 'UTF-8') ?></span>
        <p class="mobileShow" style="font-size: 14px;"><?= htmlspecialchars(uiText('statistics.top_device', 'Top device'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <?php if ($mostVisitsCount > 0 && $mostVisitsVisitor) { ?>
        <div class="visitEntry tooltip">
          <p style="max-width: 150px; overflow: hidden; white-space: nowrap;"><?= htmlspecialchars((string) $mostVisitsVisitor['name'], ENT_QUOTES, 'UTF-8') ?></p>
          <i class="day-icons" style="font-size: 26px;">&#xf1867;</i>
          <span class="tooltiptext"><?= htmlspecialchars(uiText('statistics.visitor_with_most_visits', 'Visitor with most visits'), ENT_QUOTES, 'UTF-8') ?></span>
          <p class="mobileShow" style="font-size: 14px;"><?= htmlspecialchars(uiText('statistics.most_visits', 'Most visits'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="visitEntry tooltip">
          <p><?= $mostVisitsCount ?></p>
          <i class="material-icons" style="font-size: 26px;">airline_stops</i>
          <span class="tooltiptext"><?= htmlspecialchars(uiText('statistics.most_visits_by_visitor', 'Most visits by a visitor'), ENT_QUOTES, 'UTF-8') ?></span>
          <p class="mobileShow" style="font-size: 14px;"><?= htmlspecialchars(uiText('statistics.most_visits', 'Most visits'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      <?php } ?>
    </div>
  </div>

  <div class="timeline">
    <?php
    // Group visits by visitor name to avoid duplicate names and show return counts
    $groupedVisitors = [];
    foreach ($data['visits'] as $visit) {
      $v = getVisitorById($visit['visitor_id']);
      if (!$v) continue;
      $name = $v['name'];

      if (!isset($groupedVisitors[$name])) {
        $device = detectVisitorDevice($v['browser'] ?? '');
        $groupedVisitors[$name] = [
          'count' => 0,
          'last_date' => $visit['date'],
          'ip' => $visit['ip'],
          'device_label' => $device['label'],
          'device_icon' => $device['icon'],
          'tags' => [],
          'groups' => []
        ];
      }

      $groupedVisitors[$name]['count']++;
      // keep the most recent visit date
      $visitTime = parseDbTimestamp($visit['date']) ?? 0;
      $lastTime = parseDbTimestamp($groupedVisitors[$name]['last_date']) ?? 0;
      if ($visitTime > $lastTime) {
        $groupedVisitors[$name]['last_date'] = $visit['date'];
        $groupedVisitors[$name]['ip'] = $visit['ip'];
        $device = detectVisitorDevice($v['browser'] ?? '');
        $groupedVisitors[$name]['device_label'] = $device['label'];
        $groupedVisitors[$name]['device_icon'] = $device['icon'];
      }

      // merge tags
      if (!empty($v['tags'])) {
        foreach ($v['tags'] as $t) {
          $groupedVisitors[$name]['tags'][$t['title']] = $t['title'];
        }
      }
      // merge groups
      if (!empty($v['groups'])) {
        foreach ($v['groups'] as $g) {
          $groupedVisitors[$name]['groups'][$g['title']] = $g['title'];
        }
      }
    }

    $i = 0;
    foreach ($groupedVisitors as $name => $info) {
      $sideClass = $i % 2 == 0 ? 'left' : 'right';
      $returns = $info['count'] - 1; // number of times they came back after the first visit
    ?>
      <div class="timeline-entry <?= $sideClass ?>">
        <div class="content" style="display: flex; flex-direction: row; justify-content: space-between;">
          <h2 class="date"><?= htmlspecialchars($name) ?><?php if ($returns > 0) { ?>&nbsp;<span
              style="font-weight: normal;">(<?= $returns ?>)</span><?php } ?></h2>
          <div>
            <?php $lastDateTime = parseDbTimestamp($info['last_date']); ?>
            <p><?= $lastDateTime !== null ? date("d M Y H:i", $lastDateTime) : '' ?></p>
            <p style="margin-top: 5px;" class="tooltip">
              <i class="material-icons"
                style="font-size: 16px; vertical-align: text-bottom; margin-right: 4px;">public</i><?= htmlspecialchars($info['ip']) ?>
              <span class="tooltiptext"><?= htmlspecialchars(uiText('statistics.ip_address_most_recent', 'IP Address (most recent)'), ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <p style="margin-top: 5px;" class="tooltip">
              <i class="material-icons"
                style="font-size: 16px; vertical-align: text-bottom; margin-right: 4px;"><?= htmlspecialchars((string) ($info['device_icon'] ?? 'devices_other'), ENT_QUOTES, 'UTF-8') ?></i><?= htmlspecialchars((string) ($info['device_label'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?>
              <span class="tooltiptext"><?= htmlspecialchars(uiText('statistics.detected_device_visitor_browser', 'Detected device (visitor browser)'), ENT_QUOTES, 'UTF-8') ?></span>
            </p>
          </div>
          <div class="tagsGroupsContainer">
            <div class="tagsContainer sameLine statTags">
              <?php foreach (array_values($info['tags']) as $tagTitle) { ?>
                <div class="tagContainer">
                  <p><?= htmlspecialchars($tagTitle) ?></p>
                </div>
              <?php } ?>
            </div>

            <div class="groupsContainer sameLine statTags">
              <?php foreach (array_values($info['groups']) as $groupTitle) { ?>
                <div class="tagContainer">
                  <p><?= htmlspecialchars($groupTitle) ?></p>
                </div>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>
    <?php
      $i++;
    }
    ?>
  </div>
  <div class="newLinkContainer">
    <button class="rocketContainer newLinkButton" id="rocketContainer" onclick="scrollUp(this)"><i
        class="day-icons rocket">&#xf0463;</i></button>
  </div>
</body>
<script src="/javascript/script.js?v=<?= $version ?>" defer></script>
<script src="/javascript/keyboard.js?v=<?= $version ?>" defer></script>

</html>