<?php
$payload = json_decode(file_get_contents('php://input'), true);
$comp = isset($payload['comp']) ? $payload['comp'] : '';

$options = [
  ['id' => 'alphabet_asc', 'iconClass' => 'day-icons', 'iconId' => 'f05bd', 'icon' => '&#xf05bd;', 'label_key' => 'search.sort_option_alphabet_asc', 'fallback' => 'Alphabetical (A-Z)'],
  ['id' => 'alphabet_desc', 'iconClass' => 'day-icons', 'iconId' => 'f05bf', 'icon' => '&#xf05bf;', 'label_key' => 'search.sort_option_alphabet_desc', 'fallback' => 'Alphabetical (Z-A)'],
  ['id' => 'latest_visit', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'person_pin_circle', 'label_key' => 'search.sort_option_latest_visit', 'fallback' => 'Latest Visited'],
  ['id' => 'favorite', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'star', 'label_key' => 'search.sort_option_favorite', 'fallback' => 'Favorited'],
  ['id' => 'latest', 'iconClass' => 'day-icons', 'iconId' => 'f1547', 'icon' => '&#xf1547;', 'label_key' => 'search.sort_option_latest', 'fallback' => 'Latest Added'],
  ['id' => 'oldest', 'iconClass' => 'day-icons', 'iconId' => 'f1548', 'icon' => '&#xf1548;', 'label_key' => 'search.sort_option_oldest', 'fallback' => 'Oldest Added'],
  ['id' => 'most_visit', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'trending_up', 'label_key' => 'search.sort_option_most_visit', 'fallback' => 'Most Visited'],
  ['id' => 'least_visit', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'trending_down', 'label_key' => 'search.sort_option_least_visit', 'fallback' => 'Least Visited'],
  ['id' => 'latest_modified', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'update', 'label_key' => 'search.sort_option_latest_modified', 'fallback' => 'Latest Modified'],
  ['id' => 'most_visits_today', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'person_pin_circle', 'label_key' => 'search.sort_option_most_visits_today', 'fallback' => 'Most Visits Today'],
  ['id' => 'archived', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'archive', 'label_key' => 'search.sort_option_archived', 'fallback' => 'Archived'],
  ['id' => 'most_links', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'trending_up', 'label_key' => 'search.sort_option_most_links', 'fallback' => 'Most Linked'],
  ['id' => 'least_links', 'iconClass' => 'material-icons', 'iconId' => '', 'icon' => 'trending_down', 'label_key' => 'search.sort_option_least_links', 'fallback' => 'Least Linked']
];

if ($comp === 'groups') {
  $allowed = ['alphabet_asc', 'alphabet_desc', 'latest', 'oldest', 'latest_modified', 'most_links', 'least_links'];
  $options = array_values(array_filter($options, function ($option) use ($allowed) {
    return in_array($option['id'], $allowed, true);
  }));
} elseif ($comp === 'visitors') {
  $allowed = ['alphabet_asc', 'alphabet_desc', 'latest_visit', 'latest', 'oldest', 'most_visit', 'least_visit', 'latest_modified', 'most_visits_today'];
  $options = array_values(array_filter($options, function ($option) use ($allowed) {
    return in_array($option['id'], $allowed, true);
  }));
} elseif ($comp === 'users') {
  $allowed = ['alphabet_asc', 'alphabet_desc', 'latest_visit', 'latest', 'oldest', 'latest_modified'];
  $options = array_values(array_filter($options, function ($option) use ($allowed) {
    return in_array($option['id'], $allowed, true);
  }));
}
?>

<div class="sortFilter" id="sortModal">
  <div class="modalBackground closeSortModal"></div>
  <div>
    <div class="sortModalContent">
      <div class="modalWindowControls">
        <button type="button" class="closeTagGroupButton closeSortModal closeModal modalWindowControl crossIcon material-icons" aria-label="Close modal">close</button>
      </div>
      <div class="sortFilterCon">
        <?php foreach ($options as $option) { ?>
          <?php $optionLabel = uiText($option['label_key'], $option['fallback']); ?>
          <div class="sortOption" id="<?= htmlspecialchars($option['id']) ?>" data-sort-label="<?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>">
            <i class="<?= htmlspecialchars($option['iconClass']) ?>" <?= $option['iconId'] !== '' ? 'id="' . htmlspecialchars($option['iconId']) . '"' : '' ?>><?= $option['icon'] ?></i>
            <p><?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <i class="material-icons sortOptionCheck" aria-hidden="true">check</i>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>