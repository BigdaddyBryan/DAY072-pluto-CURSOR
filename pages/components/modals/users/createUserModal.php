<?php

?>
<div class="createLinkContainer userModalContainer">
  <form action="/register" method="POST" enctype="multipart/form-data" id="createUserForm" class="createUserForm userModalForm" autocomplete="off">
    <input type="hidden" name="autocompleteoff" autocomplete="false">
    <div class="createLinkInputContainer">
      <div class="linkInputContainer shortLinkContainer userNameRow">
        <div class="domainContainer userNameField">
          <input type="text" class="linkInput" id="domainCreate" placeholder="" name="name">
          <label for="name" class="linkLabel labelMargin"><?= htmlspecialchars(uiText('modals.users.first_name', 'First name'), ENT_QUOTES, 'UTF-8') ?></label>
        </div>
        <div class="userNameField userNameFieldSecondary">
          <input type="text" class="linkInput" id="shortlinkCreate" placeholder="" name="family_name">
          <label for="family_name" class="linkLabel labelMargin"><?= htmlspecialchars(uiText('modals.users.last_name', 'Last name'), ENT_QUOTES, 'UTF-8') ?></label>
        </div>
      </div>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer status userRoleContainer">
        <div class="custom-select userRoleSelect">
          <select name="role">
            <option value="user"><?= htmlspecialchars(uiText('modals.users.role_user', 'User'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="admin"><?= htmlspecialchars(uiText('modals.users.role_admin', 'Admin'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="viewer"><?= htmlspecialchars(uiText('modals.users.role_viewer', 'Viewer'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="limited"><?= htmlspecialchars(uiText('modals.users.role_limited', 'Limited'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php if ($_SESSION['user']['role'] === 'superadmin') { ?>
              <option value="superadmin"><?= htmlspecialchars(uiText('modals.users.role_superadmin', 'Superadmin'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } ?>
          </select>
        </div>
      </div>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer">
        <input type="text" class="linkInput" id="linkTitle" placeholder="" name="email">
        <label for="email" class="linkLabel"><?= htmlspecialchars(uiText('modals.users.email', 'Email'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer userPasswordContainer">
        <input type="password" class="linkInput" id="password" placeholder="" name="password"
          autocomplete="one-time-code">
        <label for="password" class="linkLabel"><?= htmlspecialchars(uiText('modals.users.password', 'Password'), ENT_QUOTES, 'UTF-8') ?></label>
        <button type="button" class="showPass userPasswordToggle" onclick="switchPassword()" aria-label="Toggle password visibility"><i id="eyeIcon"
            class="material-icons">visibility_off</i></button>
      </div>
    </div>
    <hr data-content="<?= htmlspecialchars(uiText('modals.common.organize_tags_groups', 'Organize with tags and groups:'), ENT_QUOTES, 'UTF-8') ?>" class="optionalDivider">

    <div class="createLinkInputContainer createTagsContainer" id="linkTagsContainer">
      <input type="text" class="createLinkInput tagsInput plus" placeholder="&#xf0415;" id="linkTags"
        autocomplete="off">
      <label for="title" class="legend lastName"><i class="groupTitle day-icons"
          style="margin-top: 5px;">&#xf04fc;</i></label>
    </div>
    <div id="tagList">

    </div>
    <div class="createLinkInputContainer createGroupsContainer" id="linkGroupsContainer">
      <input type="text" class="createLinkInput plus" placeholder="&#xf0415;" id="linkGroups" autocomplete="off">
      <label for="title" class="legend lastName"><i class="groupTitle material-icons"
          style="margin-top: 5px;">folder</i></label>
    </div>
    <div id="groupList">

    </div>
    <div class="createLinkInputContainer">
      <div class="createLinkButtonContainer">
        <button class="createLinkButton submitButton" id="submitLink"><?= htmlspecialchars(uiText('modals.users.create_user', 'Create User'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
    <input type="hidden" name="tags" id="hiddenTags">
    <input type="hidden" name="groups" id="hiddenGroups">
  </form>
</div>