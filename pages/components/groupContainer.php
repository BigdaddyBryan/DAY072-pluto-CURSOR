<?php
if (!isset($group)) {
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);
  if (isset($data['group']) && !is_null($data['group'])) {
    $group = $data['group'];
  }
}

$groupDescription = trim((string)($group['description'] ?? ''));
$groupDescriptionPreview = $groupDescription !== ''
  ? (function_exists('mb_strimwidth') ? mb_strimwidth($groupDescription, 0, 90, '...') : (strlen($groupDescription) > 90 ? substr($groupDescription, 0, 87) . '...' : $groupDescription))
  : uiText('groups.no_description', 'No description');
$groupLinkCount = isset($group['link_count']) ? (int)$group['link_count'] : 0;
$groupModifiedAt = !empty($group['modified_at']) ? date('d M Y', strtotime($group['modified_at'])) : uiText('groups.unknown_date', 'Unknown date');
$groupCreatedAt = !empty($group['created_at']) ? date('d M Y', strtotime($group['created_at'])) : uiText('groups.unknown_date', 'Unknown date');
?>

<div class="outerLinkContainer" id="group<?= $group['id'] ?>"
  data-group-id="<?= $group['id'] ?>"
  data-group-title="<?= htmlspecialchars((string)$group['title'], ENT_QUOTES, 'UTF-8') ?>"
  data-group-description="<?= htmlspecialchars($groupDescription, ENT_QUOTES, 'UTF-8') ?>"
  data-group-links="<?= $groupLinkCount ?>"
  data-group-created="<?= htmlspecialchars($groupCreatedAt, ENT_QUOTES, 'UTF-8') ?>"
  data-group-modified="<?= htmlspecialchars($groupModifiedAt, ENT_QUOTES, 'UTF-8') ?>"
  data-group-image="<?= htmlspecialchars(isset($group['image']) ? $group['image'] : '', ENT_QUOTES, 'UTF-8') ?>"
  data-is-admin="<?= checkAdmin() ? '1' : '0' ?>">
  <div class="linkContainer linkSwitch container">
    <div class="linkHeader">
      <div class="titleContainer">
        <div class="linkForm">
          <input type="checkbox" class="linkCheckbox checkbox" id="checkbox-<?= $group['id'] ?>"
            onclick="multiSelect(this.id, event)">
          <span class="checkBoxBackground"><i class="checkIcon material-icons">checkmark</i></span>
        </div>
        <div class="image groupImageContainer" id="image<?= $group['id'] ?>">
          <?php if (!isset($group['image'])) { ?>
            <img class="groupImage" src="/images/groups/default.png" alt="">
          <?php } else { ?>
            <img class="groupImage" src="/images/groups/<?= htmlspecialchars($group['image'], ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null;this.src='/images/groups/default.png'" alt="">
          <?php } ?>
        </div>
        <h3 class="linkTitle"><?= $group['title'] ?></h3>
        <div class="groupMetaInline shownBody">
          <div class="groupMetaItem tooltip">
            <i class="material-icons">link</i>
            <p><?= $groupLinkCount ?></p>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('groups.linked_items', 'Linked items'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="groupMetaItem tooltip">
            <i class="material-icons">event</i>
            <p><?= $groupCreatedAt ?></p>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('groups.created_at', 'Created at'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="groupMetaItem tooltip groupMetaDescription">
            <i class="material-icons">description</i>
            <p><?= htmlspecialchars($groupDescriptionPreview, ENT_QUOTES, 'UTF-8') ?></p>
            <span class="tooltiptext"><?= htmlspecialchars($groupDescription !== '' ? $groupDescription : uiText('groups.no_description', 'No description'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="groupMetaItem tooltip">
            <i class="material-icons">update</i>
            <p><?= $groupModifiedAt ?></p>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('groups.last_modified', 'Last modified'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
        <div class="groupQuickActions">
          <?php if (checkAdmin()) { ?>
            <a class="quickAction" onclick="createModal('/editModal?id=<?= $group['id'] ?>&comp=groups')" title="<?= htmlspecialchars(uiText('groups.edit', 'Edit'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="material-icons">edit</i>
            </a>
          <?php } ?>
          <a class="quickAction" href="/?group=<?= urlencode($group['title']) ?>" title="<?= htmlspecialchars(uiText('groups.show_links', 'Show Links'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="material-icons">open_in_new</i>
          </a>
          <?php if (checkAdmin()) { ?>
            <a class="quickAction quickActionDelete" onclick="createPopupModal('/deleteLink?id=<?= $group['id'] ?>&comp=groups', this, event)" title="<?= htmlspecialchars(uiText('groups.delete_group', 'Delete Group'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="material-icons">delete</i>
            </a>
          <?php } ?>
        </div>
      </div>
      <div class="linkBody hiddenBody">
        <h3 class="linkTitle groupOpenTitle"><?= htmlspecialchars((string) $group['title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <div class="actions sameLine">
          <?php if (checkAdmin()) { ?>
            <div class="action">
              <a class="linkAction editLink" onclick="createModal('/editModal?id=<?= $group['id'] ?>&comp=groups')">
                <i class="material-icons">edit</i><?= htmlspecialchars(uiText('groups.edit', 'Edit'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          <?php } ?>
          <div class="action">
            <a class="linkAction showLinks" href="/?group=<?= $group['title'] ?>">
              <i class="material-icons">open_in_new</i>
              <?= htmlspecialchars(uiText('groups.show_links', 'Show Links'), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
          <?php if (checkAdmin()) { ?>
            <div onclick="createPopupModal('/deleteLink?id=<?= $group['id'] ?>&comp=groups', this, event)" class="action"
              id="delete-<?= $group['id'] ?>">
              <a class="linkAction deleteLink">
                <i class="material-icons">delete</i><?= htmlspecialchars(uiText('groups.delete_group', 'Delete Group'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          <?php } ?>
        </div>
        <div class="descriptionContainer">
          <p class="groupDescription"><?= $group['description'] ?></p>
        </div>
      </div>
    </div>
  </div>
</div>