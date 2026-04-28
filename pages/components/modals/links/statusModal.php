<?php
$statusId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$statusId || $statusId <= 0) {
  $statusId = 0;
}

$statusRaw = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_INT);
$statusValue = $statusRaw === 1 ? 1 : 0;
?>

<form action="/switchLinkStatus" method="POST" id="statusForm-<?= $statusId ?>">
  <h2 class="statusText" id="statusText"><?= $statusValue === 1 ? htmlspecialchars(uiText('links.active', 'Active'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(uiText('links.archived', 'Archived'), ENT_QUOTES, 'UTF-8') ?></h2>
  <input type="hidden" name="id" id="statusCheckId" value="<?= $statusId ?>">
  <label class="switch">
    <input type="checkbox" id="statusCheck" <?= $statusValue === 1 ? 'checked' : '' ?>>
    <span class="slider round"></span>
  </label>
</form>