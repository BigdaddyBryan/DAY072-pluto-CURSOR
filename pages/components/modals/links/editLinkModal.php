<?php
$id = $_GET['id'];
$link = getLinkById($id);
$shortlinkDisplayBase = isset($shortlinkBaseUrl) ? $shortlinkBaseUrl : ('https://' . $domain);

?>

<!-- <div class="editModalContainer" id="editModalContainer">
  <div class="modalBackground closeEditModal"></div>
  <div class="modal-content"> -->
<div class="editLinkContainer">
  <form action="/editLink" method="POST" class="editLinkForm" id="editLinkForm" autocomplete="off">
    <input type="hidden" name="id" value="<?= $link['id'] ?>">
    <div class="warning" id="warningTitle">
      <p><?= htmlspecialchars(uiText('modals.links.link_title_exists', 'Link Title already exists'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="editLinkInputContainer">
      <div class="linkInputContainer">
        <input type="text" class="linkInput" id="editLinkTitle" placeholder="" name="title"
          value="<?= $link['title'] ?>">
        <label for="title" class="linkLabel"><?= htmlspecialchars(uiText('modals.links.link_title', 'Link Title'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>
    <div class="editLinkInputContainer">
      <div class="linkInputContainer">
        <input type="url" class="linkInput" id="editLinkURL" placeholder="<?= htmlspecialchars(uiText('modals.links.url', 'URL'), ENT_QUOTES, 'UTF-8') ?>" name="url" pattern="https?://.+"
          value="<?= $link['url'] ?>">
        <label for="title" class="linkLabel"><?= htmlspecialchars(uiText('modals.links.url', 'URL'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>
    <div class="warning" id="warning">
      <p id="warningMessage" data-default-message="<?= htmlspecialchars(uiText('modals.links.shortlink_exists', 'Shortlink already exists'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('modals.links.shortlink_exists', 'Shortlink already exists'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="editLinkInputContainer">
      <div class="linkInputContainer shortLinkContainer">
        <div class="domainContainer">
          <label for="shortlinkEdit" class="domainTitle"><?= $shortlinkDisplayBase ?>/</label>
        </div>
        <div style="position: relative; width: 100%; height: 100%;">
          <input type="text" class="linkInput" id="shortlinkEdit" placeholder="" name="shortlink"
            value="<?= $link['shortlink'] ?>">
          <label for="title" class="linkLabel labelMargin"><?= htmlspecialchars(uiText('modals.links.shortlink', 'Shortlink'), ENT_QUOTES, 'UTF-8') ?></label>
        </div>
      </div>
    </div>

    <!-- Expiration date/time (optional) -->
    <div class="editLinkInputContainer">
      <div class="linkInputContainer">
        <input type="datetime-local" class="linkInput" id="expiresAtEdit" name="expires_at"
          value="<?= isset($link['expires_at']) && !empty($link['expires_at']) ? date('Y-m-d\\TH:i', strtotime($link['expires_at'])) : '' ?>">
        <label for="expires_at" class="linkLabel"><?= htmlspecialchars(uiText('modals.links.expires_optional', 'Expires at (optional)'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>

    <hr data-content="<?= htmlspecialchars(uiText('modals.common.organize_tags_groups', 'Organize with tags and groups:'), ENT_QUOTES, 'UTF-8') ?>" class="optionalDivider">

    <div class="createLinkInputContainer createTagsContainer" id="editTagsContainer">
      <?php foreach ($link['tags'] as $tag) { ?>
        <div class="editTagContainer">
          <p class="presetTag"><?= $tag['title'] ?></p>
          <span class="material-icons closeTag">close</span>
        </div>
      <?php } ?>

      <input type="text" class="createLinkInput tagsInput plus" placeholder="&#xf0415;" id="editLinkTags" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
      <label for="title" class="legend lastName"><i class="day-icons tagTitle"
          style="margin-top: 5px;">&#xf04fc;</i></label>

    </div>
    <div id="tagList">

    </div>
    <div class="linkGroupListAnchor">
      <div class="createLinkInputContainer createGroupsContainer" id="editGroupsContainer">
        <?php foreach ($link['groups'] as $group) { ?>
          <div class="editGroupContainer">
            <p class="presetGroup"><?= $group['title'] ?></p>
            <span class="material-icons closeTag">close</span>
          </div>
        <?php } ?>
        <!-- <label for="editLinkGroups" class="createLinkLabel groupsLabel">Groups</label> -->
        <input type="text" class="createLinkInput plus" placeholder="&#xf0415;" id="editLinkGroups" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
        <label for="title" class="legend lastName"><i class="groupTitle material-icons"
            style="margin-top: 5px;">folder</i></label>
      </div>
      <div id="groupList">

      </div>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer status">
        <label for="statusCheck" class="linkLabel statusLabel"><?= htmlspecialchars(uiText('modals.links.active_status', 'Active Status:'), ENT_QUOTES, 'UTF-8') ?></label>
        <label class="switch">
          <input type="checkbox" id="statusCheck" name="status" <?= intval($link['status']) === 1 ? 'checked' : '' ?>>
          <span class="slider round"></span>
        </label>
      </div>
    </div>
    <div class="createLinkButtonContainer">
      <button class="createLinkButton submitButton" id="submitEdit"><?= htmlspecialchars(uiText('modals.links.update_link', 'Update Link'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <input type="hidden" name="tags" id="hiddenEditTags">
    <input type="hidden" name="groups" id="hiddenEditGroups">
  </form>
</div>