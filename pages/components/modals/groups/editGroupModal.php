<?php
$id = $_GET['id'];
$group = getGroupById($id);
?>

<div class="editModalHeader">
  <h2><?= htmlspecialchars(uiText('modals.groups.edit_group', 'Edit Group'), ENT_QUOTES, 'UTF-8') ?></h2>
</div>
<div class="editLinkContainer">
  <form action="/editGroup" method="POST" class="editLinkForm" id="editGroupForm" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $group['id'] ?>">
    <div class="editLinkInputContainer">
      <div class="linkInputContainer">
        <input type="text" class="linkInput" id="editLinkTitle" placeholder="<?= htmlspecialchars(uiText('modals.groups.group_name', 'Group name'), ENT_QUOTES, 'UTF-8') ?>" name="title"
          value="<?= $group['title'] ?>">
        <label for="title" class="linkLabel"><?= htmlspecialchars(uiText('modals.groups.group_name', 'Group name'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>
    <hr data-content="<?= htmlspecialchars(uiText('modals.common.optional', 'Optional:'), ENT_QUOTES, 'UTF-8') ?>" class="optionalDivider">
    <div class="dataGrid">
      <div class="descContainer">
        <label for="description" class="descLabel"><?= htmlspecialchars(uiText('modals.groups.description', 'Description:'), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea class="editLinkInput descInput" placeholder="<?= htmlspecialchars(uiText('modals.groups.description_placeholder', 'Description'), ENT_QUOTES, 'UTF-8') ?>"
          name="description"><?= $group['description'] ?></textarea>
      </div>
      <div class="imageContainer">
        <div class="groupImageContainer uploadImage">
          <img for="groupImage"
            src="/../../../public/images/groups/<?= $group['image'] ? $group['image'] : 'default.png' ?>"
            id="editImagePreview" class="image groupImage"
            data-current-src="/../../../public/images/groups/<?= $group['image'] ? $group['image'] : 'default.png' ?>"
            data-default-src="/../../../public/images/groups/default.png">
        </div>
        <div class="uploadButton">
          <label for="file-upload" class="custom-file-upload">
            <?= htmlspecialchars(uiText('modals.groups.upload_image', 'Upload Image'), ENT_QUOTES, 'UTF-8') ?>
          </label>
          <input onchange="checkFile(this)" id="file-upload" type="file" name="image" />
          <?php if (!empty($group['image']) && $group['image'] !== 'default.png') { ?>
            <div class="removeImageToggle">
              <input type="checkbox" id="remove-group-image" name="remove_image" value="1"
                onchange="toggleGroupImageRemoval(this)">
              <label for="remove-group-image"><?= htmlspecialchars(uiText('modals.groups.remove_current_image', 'Remove current image'), ENT_QUOTES, 'UTF-8') ?></label>
            </div>
          <?php } ?>
        </div>
      </div>

    </div>
    <div class="createLinkButtonContainer">
      <button class="createLinkButton submitButton" id="submitEdit"><?= htmlspecialchars(uiText('modals.groups.update_group', 'Update Group'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </form>
</div>