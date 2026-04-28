<?php

?>
<?php $shortlinkDisplayBase = isset($shortlinkBaseUrl) ? $shortlinkBaseUrl : ('https://' . $domain); ?>
<div class="createLinkContainer">
  <form action="/createLink" method="POST" class="createLinkForm" id="createLinkForm" autocomplete="off">
    <div class="warning" id="warningTitle">
      <p><?= htmlspecialchars(uiText('modals.links.link_title_exists', 'Link Title already exists'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer">
        <input type="text" class="linkInput" id="linkTitle" placeholder="" name="title" required>
        <label for="title" class="linkLabel"><?= htmlspecialchars(uiText('modals.links.link_title', 'Link Title'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer">
        <input type="url" class="linkInput" id="linkURL" placeholder="" name="url" required pattern="https?://.+">
        <label for="title" class="linkLabel"><?= htmlspecialchars(uiText('modals.links.url', 'URL'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>
    <div class="warning" id="warning">
      <p id="warningMessage" data-default-message="<?= htmlspecialchars(uiText('modals.links.shortlink_exists', 'Shortlink already exists'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('modals.links.shortlink_exists', 'Shortlink already exists'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer">
        <div class="domainContainer">
          <label for="shortlinkCreate" class="domainTitle"><?= $shortlinkDisplayBase ?>/</label>
        </div>
        <div style="position: relative; width: 100%; height: 100%;">
          <input type="text" class="linkInput" name="shortlink" id="shortlinkCreate" placeholder="">
          <label for="shortlink" class="linkLabel labelMargin"><?= htmlspecialchars(uiText('modals.links.shortlink', 'Shortlink'), ENT_QUOTES, 'UTF-8') ?></label>
        </div>
      </div>
    </div>
    <div class="aliasOverwriteOption" id="overwriteAliasOption">
      <label class="aliasOverwriteToggle" for="overwriteAliasCheck">
        <input type="checkbox" id="overwriteAliasCheck">
        <span><?= htmlspecialchars(uiText('modals.links.overwrite_alias_label', 'Overwrite existing alias'), ENT_QUOTES, 'UTF-8') ?></span>
      </label>
      <p class="aliasOverwriteHint"><?= htmlspecialchars(uiText('modals.links.overwrite_alias_hint', 'Use this when the shortlink is only an alias in history, not an active shortlink.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <!-- Expiration date/time (optional) -->
    <div class="createLinkInputContainer">
      <div class="linkInputContainer">
        <input type="datetime-local" class="linkInput" id="expiresAtCreate" name="expires_at" value="">
        <label for="expires_at" class="linkLabel"><?= htmlspecialchars(uiText('modals.links.expires_optional', 'Expires at (optional)'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>

    <hr data-content="<?= htmlspecialchars(uiText('modals.common.organize_tags_groups', 'Organize with tags and groups:'), ENT_QUOTES, 'UTF-8') ?>" class="optionalDivider">

    <div class="createLinkInputContainer createTagsContainer" id="linkTagsContainer">
      <input type="text" class="createLinkInput tagsInput plus" placeholder="&#xf0415;" id="linkTags" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
      <label for="title" class="legend lastName"><i class="groupTitle day-icons"
          style="margin-top: 5px;">&#xf04fc;</i></label>
    </div>
    <div id="tagList">

    </div>
    <div class="linkGroupListAnchor">
      <div class="createLinkInputContainer createGroupsContainer" id="linkGroupsContainer">
        <input type="text" class="createLinkInput plus" placeholder="&#xf0415;" id="linkGroups" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
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
          <input type="checkbox" id="statusCheck" name="status" checked>
          <span class="slider round"></span>
        </label>
      </div>
    </div>
    <div class="createLinkButtonContainer">
      <button class="createLinkButton submitButton" id="submitLink"><?= htmlspecialchars(uiText('modals.links.create_link', 'Create Link'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <input type="hidden" name="tags" id="hiddenTags">
    <input type="hidden" name="groups" id="hiddenGroups">
    <input type="hidden" name="overwrite_alias" id="overwriteAlias" value="0">
  </form>
</div>