<!DOCTYPE html>
<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
checkAdmin();
$historyUserId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$historyUserId || $historyUserId <= 0) {
  include __DIR__ . '/errors/404.php';
  exit;
}

$user = getUserById($historyUserId);
$history = getUserHistory($historyUserId);
if (!$user) {
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

include __DIR__ . '/components/navigation.php';
?>

<html lang="<?= htmlspecialchars(uiLocale(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $titles['history']['title'] ?></title>
  <link rel="stylesheet" href="css/custom.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/modal.css" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="css/modal.css">
  </noscript>
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="stylesheet" href="css/material-icons.css">
</head>

<style>
  .historyControls {
    max-width: 1200px;
    margin: 0 auto 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
  }

  .historySearch {
    min-width: 260px;
    flex: 1;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.2));
    background: var(--background-color);
    color: var(--text-color);
  }

  .historyFilterButtons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .historyFilterButton {
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.2));
    background: var(--background-color);
    color: var(--text-color);
    border-radius: 10px;
    padding: 10px 14px;
    cursor: pointer;
    font-weight: 600;
  }

  .historyFilterButton.active {
    background: #17c98c;
    color: #ffffff;
    border-color: #17c98c;
  }

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

  .historyTypeBadge {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 12px;
    border-radius: 8px;
    background: var(--background-color-light);
    color: var(--text-color-dark);
    padding: 5px 8px;
    text-transform: capitalize;
  }

  .right .historyTypeBadge {
    right: auto;
    left: 10px;
  }

  .historyMeta {
    margin-top: 8px;
    font-size: 13px;
    opacity: 0.85;
  }

  .historyEmpty {
    max-width: 1200px;
    margin: 20px auto;
    background: var(--background-color);
    border-radius: 10px;
    padding: 16px;
    color: var(--text-color);
    display: none;
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

<body>
  <?php
  $categoryLabels = [
    'all' => uiText('history.all_items', 'All history items'),
    'links' => uiText('history.links', 'Links'),
    'users' => uiText('history.users', 'Users'),
    'visitors' => uiText('history.visitors', 'Visitors'),
    'groups' => uiText('history.groups', 'Groups'),
    'access_links' => uiText('history.access_links', 'Access links'),
  ];

  $timelineItems = [];
  foreach ($history as $category => $entries) {
    if (!is_array($entries)) {
      continue;
    }

    foreach ($entries as $entry) {
      if (!is_array($entry)) {
        continue;
      }

      $rawDate = !empty($entry['modified_at']) ? $entry['modified_at'] : (!empty($entry['created_at']) ? $entry['created_at'] : null);
      $timestamp = parseDbTimestamp($rawDate);
      if ($timestamp === null) {
        continue;
      }

      $timelineItems[] = [
        'category' => (string) $category,
        'timestamp' => $timestamp,
        'entry' => $entry,
      ];
    }
  }

  usort($timelineItems, function ($a, $b) {
    return (int) $b['timestamp'] <=> (int) $a['timestamp'];
  });
  ?>

  <div class="historyControls">
    <input
      id="historySearchInput"
      class="historySearch"
      type="text"
      placeholder="<?= htmlspecialchars(uiText('history.search_placeholder', 'Search title, IP, role, scope, status...'), ENT_QUOTES, 'UTF-8') ?>"
      aria-label="<?= htmlspecialchars(uiText('history.search_aria', 'Search user history'), ENT_QUOTES, 'UTF-8') ?>">

    <div class="historyFilterButtons" id="historyFilterButtons">
      <?php foreach ($categoryLabels as $categoryKey => $categoryLabel) { ?>
        <button
          type="button"
          class="historyFilterButton<?= $categoryKey === 'all' ? ' active' : '' ?>"
          data-filter="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?>
        </button>
      <?php } ?>
    </div>
  </div>

  <div class="timeline">
    <?php foreach ($timelineItems as $itemIndex => $item) { ?>
      <?php
      $index = $item['category'];
      $value = $item['entry'];
      $timestamp = (int) $item['timestamp'];
      $formatted = date('d-m-Y H:i:s', $timestamp);

      $action = uiText('history.modified', 'modified');
      if (($index === 'links' || $index === 'users' || $index === 'groups') && ($value['modified_at'] ?? null) === ($value['created_at'] ?? null)) {
        $action = uiText('history.created', 'created');
      }

      $searchBlob = strtolower(trim(implode(' ', [
        (string) ($value['title'] ?? ''),
        (string) ($value['name'] ?? ''),
        (string) ($value['ip'] ?? ''),
        (string) ($value['status'] ?? ''),
        (string) ($value['role'] ?? ''),
        (string) ($value['scope'] ?? ''),
      ])));
      ?>
      <div
        class="timeline-entry <?= $itemIndex % 2 === 0 ? 'left' : 'right' ?>"
        data-category="<?= htmlspecialchars((string) $index, ENT_QUOTES, 'UTF-8') ?>"
        data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
        <div class="content">
          <span class="date"><?= htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="historyTypeBadge"><?= htmlspecialchars((string) ($categoryLabels[$index] ?? $index), ENT_QUOTES, 'UTF-8') ?></span>

          <?php if ($index === 'links') { ?>
            <a href="/?link_id=<?= urlencode((string) $value['id']) ?>">
              <h4 class="sameLine"><?= htmlspecialchars($user['name'] . ' ' . $action . ' ' . ($value['title'] ?? uiText('history.link', 'Link')), ENT_QUOTES, 'UTF-8') ?> <i class="material-icons">link</i></h4>
            </a>
          <?php } else if ($index === 'users') { ?>
            <h4 class="sameLine"><?= htmlspecialchars($user['name'] . ' ' . $action . ' ' . ($value['name'] ?? uiText('history.user', 'User')), ENT_QUOTES, 'UTF-8') ?></h4>
          <?php } else if ($index === 'visitors') { ?>
            <p><?= htmlspecialchars((string) ($value['ip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <a href="/visitors?visitor_id=<?= urlencode((string) $value['id']) ?>">
              <h4 class="sameLine"><?= htmlspecialchars($user['name'] . ' ' . uiText('history.modified', 'modified') . ' ' . ($value['name'] ?? uiText('history.visitor', 'Visitor')), ENT_QUOTES, 'UTF-8') ?> <i class="material-icons">link</i></h4>
            </a>
          <?php } else if ($index === 'groups') { ?>
            <h4 class="sameLine"><?= htmlspecialchars($user['name'] . ' ' . $action . ' ' . uiText('history.group', 'group') . ' ' . ($value['title'] ?? uiText('history.group_fallback', 'Group')), ENT_QUOTES, 'UTF-8') ?></h4>
          <?php } else if ($index === 'access_links') { ?>
            <h4 class="sameLine"><?= htmlspecialchars(uiText('history.access_link', 'Access link') . ' ' . ($value['status'] ?? uiText('history.updated', 'updated')) . ': ' . ($value['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="historyMeta"><?= htmlspecialchars(uiText('history.role', 'Role:') . ' ' . (string) ($value['role'] ?? uiText('history.viewer', 'viewer')) . ' | ' . uiText('history.scope', 'Scope:') . ' ' . (string) ($value['scope'] ?? uiText('history.all_groups', 'All groups')) . ' | ' . uiText('history.clicks', 'Clicks:') . ' ' . (string) ($value['click_count'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
          <?php } ?>
        </div>
      </div>
    <?php } ?>
  </div>

  <p class="historyEmpty" id="historyEmptyState"><?= htmlspecialchars(uiText('history.no_items_match_filter', 'No history items match this filter.'), ENT_QUOTES, 'UTF-8') ?></p>

  <script>
    (function() {
      const filterButtons = Array.from(document.querySelectorAll('.historyFilterButton'));
      const searchInput = document.getElementById('historySearchInput');
      const entries = Array.from(document.querySelectorAll('.timeline-entry'));
      const emptyState = document.getElementById('historyEmptyState');
      let activeFilter = 'all';

      function applyFilters() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        entries.forEach((entry) => {
          const category = entry.dataset.category || '';
          const searchValue = entry.dataset.search || '';
          const categoryMatch = activeFilter === 'all' || category === activeFilter;
          const queryMatch = query === '' || searchValue.includes(query);
          const show = categoryMatch && queryMatch;

          entry.style.display = show ? '' : 'none';
          if (show) {
            visibleCount += 1;
          }
        });

        if (emptyState) {
          emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
      }

      filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
          filterButtons.forEach((btn) => btn.classList.remove('active'));
          button.classList.add('active');
          activeFilter = button.dataset.filter || 'all';
          applyFilters();
        });
      });

      searchInput?.addEventListener('input', applyFilters);
      applyFilters();
    })();
  </script>
</body>

</html>