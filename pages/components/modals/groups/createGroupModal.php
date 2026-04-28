<?php

?>

<div class="editModalHeader">
  <h2><?= htmlspecialchars(uiText('modals.groups.create_group', 'Create Group'), ENT_QUOTES, 'UTF-8') ?></h2>
</div>
<div class="editLinkContainer">
  <form action="/createGroup" method="POST" class="editLinkForm" id="editGroupForm" enctype="multipart/form-data">
    <div class="editLinkInputContainer">
      <div class="linkInputContainer">
        <input type="text" class="linkInput" id="editLinkTitle" placeholder="" name="title">
        <label for="title" class="linkLabel"><?= htmlspecialchars(uiText('modals.groups.title', 'Title'), ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    </div>
    <hr data-content="<?= htmlspecialchars(uiText('modals.common.optional', 'Optional:'), ENT_QUOTES, 'UTF-8') ?>" class="optionalDivider">
    <div class="dataGrid">
      <div class="descContainer">
        <label for="description" class="descLabel"><?= htmlspecialchars(uiText('modals.groups.description', 'Description:'), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea class="editLinkInput descInput" placeholder="<?= htmlspecialchars(uiText('modals.groups.description_placeholder', 'Description'), ENT_QUOTES, 'UTF-8') ?>"
          name="description"></textarea>
      </div>
      <div class="imageContainer">
        <div class="groupImageContainer uploadImage">
          <img for="groupImage"
            src="/../../../public/images/groups/default.png"
            id="editImagePreview" class="image groupImage">
        </div>
        <div class="uploadButton">
          <label for="file-upload" class="custom-file-upload">
            <?= htmlspecialchars(uiText('modals.groups.upload_image', 'Upload Image'), ENT_QUOTES, 'UTF-8') ?>
          </label>
          <input onchange="checkFile(this)" id="file-upload" type="file" name="image" />
        </div>
      </div>

    </div>
    <div class="createLinkButtonContainer">
      <button class="createLinkButton submitButton" id="submitEdit"><?= htmlspecialchars(uiText('modals.groups.create_group', 'Create Group'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </form>
</div>