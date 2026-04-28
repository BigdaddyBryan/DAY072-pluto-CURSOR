<?php
$rawComp = isset($_GET['comp']) ? (string) $_GET['comp'] : '';
$safeId = htmlspecialchars((string) ($_GET['id'] ?? ''), ENT_QUOTES, 'UTF-8');
$safeCompValue = htmlspecialchars($rawComp, ENT_QUOTES, 'UTF-8');

$deleteCopyByComp = [
  'links' => [
    'title_key' => 'modals.delete.titles.links',
    'title_fallback' => 'Delete short link',
    'body_key' => 'modals.delete.bodies.links',
    'body_fallback' => 'Permanently delete this short link?',
  ],
  'groups' => [
    'title_key' => 'modals.delete.titles.groups',
    'title_fallback' => 'Delete group',
    'body_key' => 'modals.delete.bodies.groups',
    'body_fallback' => 'Permanently delete this group?',
  ],
  'users' => [
    'title_key' => 'modals.delete.titles.users',
    'title_fallback' => 'Delete user account',
    'body_key' => 'modals.delete.bodies.users',
    'body_fallback' => 'Permanently delete this user account?',
  ],
  'visitors' => [
    'title_key' => 'modals.delete.titles.visitors',
    'title_fallback' => 'Delete visitor entry',
    'body_key' => 'modals.delete.bodies.visitors',
    'body_fallback' => 'Permanently delete this visitor entry?',
  ],
];

$deleteCopy = isset($deleteCopyByComp[$rawComp])
  ? $deleteCopyByComp[$rawComp]
  : [
    'title_key' => 'modals.delete.titles.default',
    'title_fallback' => 'Delete item',
    'body_key' => 'modals.delete.bodies.default',
    'body_fallback' => 'Permanently delete this item?',
  ];
?>

<div class="deleteConfirmModal" role="group" aria-label="<?= htmlspecialchars(uiText('modals.delete.a11y_group', 'Delete confirmation actions'), ENT_QUOTES, 'UTF-8') ?>">
  <div class="deleteConfirmIcon" aria-hidden="true">
    <i class="material-icons">warning_amber</i>
  </div>

  <h2 class="deleteConfirmTitle">
    <?= htmlspecialchars(uiText($deleteCopy['title_key'], $deleteCopy['title_fallback']), ENT_QUOTES, 'UTF-8') ?>
  </h2>

  <p class="deleteConfirmBody">
    <?= htmlspecialchars(uiText($deleteCopy['body_key'], $deleteCopy['body_fallback']), ENT_QUOTES, 'UTF-8') ?>
  </p>

  <div class="modal-footer deleteConfirmActions">
    <input type="hidden" id="deleteLinkId" name="id" value="<?= $safeId ?>">
    <input type="hidden" id="deleteComp" name="comp" value="<?= $safeCompValue ?>">

    <button class="deleteBtn confirmSafeBtn" name="deleteFalse" id="deleteFalse" type="button">
      <?= htmlspecialchars(uiText('modals.delete.actions.keep', 'No, keep it'), ENT_QUOTES, 'UTF-8') ?>
    </button>

    <button class="deleteBtn confirmDangerBtn" name="deleteTrue" id="deleteTrue" type="button">
      <?= htmlspecialchars(uiText('modals.delete.actions.delete', 'Yes, delete'), ENT_QUOTES, 'UTF-8') ?>
    </button>
  </div>
</div>