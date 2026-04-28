<?php
$id = $_GET['id'];
$visitor = getVisitorById($id);

?>

<!-- <div class="editModalContainer" id="editModalContainer">
  <div class="modalBackground closeEditModal"></div>
  <div class="modal-content"> -->
<div class="editLinkContainer">
  <form action="/editVisitor" method="POST" class="editLinkForm" id="editLinkForm">
    <input type="hidden" name="id" value="<?= $visitor['id'] ?>">
    <input type="hidden" name="comp" id="comp" value="visitors">
    <div class="editLinkInputContainer">
      <div class="linkInputContainer">
        <input type="text" class="editLinkInput" id="editLinkTitle" placeholder="<?= htmlspecialchars(uiText('modals.visitors.visitor_name', 'Visitor Name'), ENT_QUOTES, 'UTF-8') ?>" name="name" value="<?= $visitor['name'] ?>">
      </div>
    </div>
    <hr data-content="<?= htmlspecialchars(uiText('modals.common.organize_tags_groups', 'Organize with tags and groups:'), ENT_QUOTES, 'UTF-8') ?>" class="optionalDivider">

    <div class="createLinkInputContainer createTagsContainer" id="editTagsContainer">
      <?php foreach ($visitor['tags'] as $tag) { ?>
        <div class="editTagContainer">
          <p class="presetTag"><?= $tag['title'] ?></p>
        </div>
      <?php } ?>

      <label for="editLinkTags" class="createLinkLabel tagsLabel"><?= htmlspecialchars(uiText('modals.common.tags', 'Tags'), ENT_QUOTES, 'UTF-8') ?></label>
      <input type="text" class="createLinkInput tagsInput plus" placeholder="&#xf0415;" id="editLinkTags">

    </div>
    <div id="tagList">

    </div>
    <div class="createLinkInputContainer createGroupsContainer" id="editGroupsContainer">
      <?php foreach ($visitor['groups'] as $group) { ?>
        <div class="editGroupContainer">
          <p class="presetgroup"><?= $group['title'] ?></p>
        </div>
      <?php } ?>
      <label for="editLinkGroups" class="createLinkLabel groupsLabel"><?= htmlspecialchars(uiText('modals.common.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?></label>
      <input type="text" class="createLinkInput plus" placeholder="&#xf0415;" id="editLinkGroups">
    </div>
    <div id="groupList">

    </div>
    <div class="createLinkButtonContainer">
      <button class="createLinkButton submitButton" id="submitEdit"><?= htmlspecialchars(uiText('modals.visitors.update_visitor', 'Update Visitor'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <input type="hidden" name="tags" id="hiddenEditTags">
    <input type="hidden" name="groups" id="hiddenEditGroups">
  </form>
</div>