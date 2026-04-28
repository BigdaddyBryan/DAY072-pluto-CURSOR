<?php

checkUser();
$isAdmin = checkAdmin();

?>

<div class="tagFilter" id="modalContainer">
    <div class="modalBackground closeTagGroupModal"></div>
    <div class="tagGroupModalContent" data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>">
        <div class="modalWindowControls">
            <button type="button" class="closeTagGroupButton closeTagGroupModal closeModal modalWindowControl crossIcon material-icons" aria-label="Close modal">close</button>
        </div>
        <div class="tagGroupContainer">
            <div class="tagGroupSearchWrap">
                <i class="material-icons tagGroupSearchIcon">search</i>
                <input type="text" id="tagGroupSearchInput" class="tagGroupSearchInput" placeholder="<?= htmlspecialchars(uiText('modals.tag_group.search_placeholder', 'Search tags and groups...'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
            </div>
            <div class="tagGroupToggle" role="tablist" aria-label="<?= htmlspecialchars(uiText('modals.tag_group.filter_toggle', 'Filter type toggle'), ENT_QUOTES, 'UTF-8') ?>">
                <button type="button" class="tagGroupToggleButton is-active" id="tagToggleTags" data-target="tags" role="tab" aria-selected="true"><?= htmlspecialchars(uiText('modals.common.tags', 'Tags'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="tagGroupToggleButton" id="tagToggleGroups" data-target="groups" role="tab" aria-selected="false"><?= htmlspecialchars(uiText('modals.common.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <div class="tagGroup show" id="tagGroup">
                <div id="tagsDropdown">
                    <h3><i class="day-icons tagTitle">&#xf04fc;</i> <?= htmlspecialchars(uiText('modals.common.tags', 'Tags'), ENT_QUOTES, 'UTF-8') ?> <span class="tagGroupCountBadge" id="tagsSelectedCount">0</span></h3>
                    <i class="material-icons" id="tagsArrow">keyboard_arrow_down</i>
                </div>
                <div class="tagGroupContent" id="tagsDropdownContent">
                </div>
            </div>
            <div class="tagGroup show" id="groupGroup">
                <div id="groupsDropdown">
                    <h3><i class="groupTitle material-icons">folder</i> <?= htmlspecialchars(uiText('modals.common.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?> <span class="tagGroupCountBadge" id="groupsSelectedCount">0</span></h3>
                    <i class="material-icons" id="groupsArrow">keyboard_arrow_down</i>
                </div>
                <div class="tagGroupContent" id="groupsDropdownContent">
                </div>
            </div>
            <?php if ($isAdmin): ?>
                <div class="tagGroup show" id="deletedTagGroup">
                    <div id="deletedTagsDropdown" aria-label="Toggle recently removed tags list">
                        <h3 id="deletedTagsTitle">Recently removed tags (last 24h)</h3>
                        <i class="material-icons" id="deletedTagsArrow">keyboard_arrow_down</i>
                    </div>
                    <div class="tagGroupContent" id="deletedTagsDropdownContent">
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="tagGroupButtonContainer">
            <?php if ($isAdmin) { ?>
                <button class="tagGroupButton submitButton tagGroupDeleteButton" id="deleteTagGroup"><?= htmlspecialchars(uiText('modals.tag_group.delete_count', 'Delete (0)'), ENT_QUOTES, 'UTF-8') ?></button>
            <?php } ?>
            <button class="tagGroupButton submitButton" id="submitTagGroup"><?= htmlspecialchars(uiText('modals.tag_group.filter_count', 'Filter (0)'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
</div>