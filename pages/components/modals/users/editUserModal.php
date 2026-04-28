<?php

if (!checkAdmin()) {
  ob_start();
  header('Location: /');
  ob_end_flush();
  exit;
}

$id = $_GET['id'];
$user = getUserById($id);

?>

<div class="createLinkContainer userModalContainer">
  <form action="/editUser" method="POST" enctype="multipart/form-data" class="createLinkForm userModalForm" id="editUserForm">
    <input type="hidden" name="id" value="<?= $user['id'] ?>">
    <div class="createLinkInputContainer">
      <div class="linkInputContainer shortLinkContainer userNameRow">
        <div class="domainContainer userNameField">
          <input type="text" class="linkInput" placeholder="" name="name" value="<?= $user['name'] ?>">
          <label for="name" class="linkLabel labelMargin"><?= htmlspecialchars(uiText('modals.users.first_name', 'First name'), ENT_QUOTES, 'UTF-8') ?></label>
        </div>
        <div class="userNameField userNameFieldSecondary">
          <input type="text" class="linkInput" placeholder="" name="family_name" value="<?= $user['family_name'] ?>">
          <label for="family_name" class="linkLabel labelMargin"><?= htmlspecialchars(uiText('modals.users.last_name', 'Last name'), ENT_QUOTES, 'UTF-8') ?></label>
        </div>
      </div>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer status userRoleContainer">
        <div class="custom-select userRoleSelect">
          <select name="role">
            <option <?php echo $user['role'] == 'user' ? 'selected' : '' ?> value="user"><?= htmlspecialchars(uiText('modals.users.role_user', 'User'), ENT_QUOTES, 'UTF-8') ?></option>
            <option <?php echo $user['role'] == 'admin' ? 'selected' : '' ?> value="admin"><?= htmlspecialchars(uiText('modals.users.role_admin', 'Admin'), ENT_QUOTES, 'UTF-8') ?></option>
            <option <?php echo $user['role'] == 'viewer' ? 'selected' : '' ?> value="viewer"><?= htmlspecialchars(uiText('modals.users.role_viewer', 'Viewer'), ENT_QUOTES, 'UTF-8') ?></option>
            <option <?php echo $user['role'] == 'limited' ? 'selected' : '' ?> value="limited"><?= htmlspecialchars(uiText('modals.users.role_limited', 'Limited'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php if ($_SESSION['user']['role'] === 'superadmin') { ?>
              <option <?php echo $user['role'] == 'superadmin' ? 'selected' : '' ?> value="superadmin"><?= htmlspecialchars(uiText('modals.users.role_superadmin', 'Superadmin'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } ?>
          </select>
        </div>
      </div>
    </div>
    <div class="createLinkInputContainer">
      <div class="linkInputContainer">
        <input type="text" class="linkInput" id="linkTitle" placeholder="<?= htmlspecialchars(uiText('modals.users.email', 'Email'), ENT_QUOTES, 'UTF-8') ?>" name="email"
          value="<?= $user['email'] ?>">
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
      <hr data-content="<?= htmlspecialchars(uiText('modals.common.organize_tags_groups', 'Organize with tags and groups:'), ENT_QUOTES, 'UTF-8') ?>" class="optionalDivider">

      <div class="createLinkInputContainer createTagsContainer" id="editTagsContainer">
        <?php foreach ($user['tags'] as $tag) { ?>
          <div class="editTagContainer">
            <p class="presetTag"><?= $tag['title'] ?></p>
            <span class="material-icons closeTag">close</span>
          </div>
        <?php } ?>

        <input type="text" class="createLinkInput tagsInput plus" placeholder="&#xf0415;" id="editLinkTags">
        <label for="title" class="legend lastName"><i class="day-icons tagTitle"
            style="margin-top: 5px;">&#xf04fc;</i></label>

      </div>
      <div id="tagList">

      </div>
      <div class="createLinkInputContainer createGroupsContainer" id="editGroupsContainer">
        <?php foreach ($user['groups'] as $group) { ?>
          <div class="editGroupContainer">
            <p class="presetgroup"><?= $group['title'] ?></p>
            <span class="material-icons closeTag">close</span>
          </div>
        <?php } ?>
        <!-- <label for="editLinkGroups" class="createLinkLabel groupsLabel">Groups</label> -->
        <input type="text" class="createLinkInput plus" placeholder="&#xf0415;" id="editLinkGroups">
        <label for="title" class="legend lastName"><i class="groupTitle material-icons"
            style="margin-top: 5px;">folder</i></label>
      </div>
      <div id="groupList">
      </div>

      <div class="createLinkButtonContainer userEditActions">
        <button class="createLinkButton submitButton userEditActionButton" id="submitLink"><?= htmlspecialchars(uiText('modals.users.save_user', 'Save User'), ENT_QUOTES, 'UTF-8') ?></button>
        <button
          type="button"
          class="createLinkButton deleteBtn userEditActionButton"
          id="logoutAllDevicesBtn"
          data-user-id="<?= (int) $user['id'] ?>">
          <?= htmlspecialchars(uiText('modals.users.logout_all_devices', 'Sign out everywhere'), ENT_QUOTES, 'UTF-8') ?>
        </button>
      </div>
    </div>
    <input type="hidden" name="tags" id="hiddenEditTags">
    <input type="hidden" name="groups" id="hiddenEditGroups">
  </form>
</div>