<?php
$linkId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$link = $linkId > 0 ? getLinkById($linkId) : null;
$aliases = $linkId > 0 ? getLinkAliasHistory($linkId) : [];
$shortlinkDisplayBase = isset($shortlinkBaseUrl) ? $shortlinkBaseUrl : ('https://' . $domain);
?>

<div class="aliasHistoryModal">
    <div class="aliasHistoryHeader">
        <h2><?= htmlspecialchars(uiText('modals.alias_history.title', 'Alias History'), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if ($link) { ?>
            <p class="aliasHistorySubline">
                <?= htmlspecialchars(uiText('modals.alias_history.current_shortlink', 'Current shortlink:'), ENT_QUOTES, 'UTF-8') ?>
                <strong><?= htmlspecialchars((string) ($link['shortlink'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        <?php } ?>
    </div>

    <?php if (!$link) { ?>
        <div class="aliasHistoryEmpty">
            <?= htmlspecialchars(uiText('modals.alias_history.link_not_found', 'Link not found.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php } else if (empty($aliases)) { ?>
        <div class="aliasHistoryEmpty">
            <?= htmlspecialchars(uiText('modals.alias_history.no_aliases_yet', 'No previous aliases yet.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php } else { ?>
        <div class="aliasHistoryList" id="aliasHistoryList" data-link-id="<?= (int) $linkId ?>">
            <?php foreach ($aliases as $aliasRow) {
                $aliasValue = trim((string) ($aliasRow['alias'] ?? ''));
                if ($aliasValue === '') {
                    continue;
                }
                $aliasCreatedAt = (string) ($aliasRow['created_at'] ?? '');
                $aliasCreatedBy = (string) ($aliasRow['created_by_email'] ?? '');
                $fullAliasUrl = rtrim($shortlinkDisplayBase, '/') . '/' . ltrim($aliasValue, '/');
            ?>
                <div class="aliasHistoryItem">
                    <div class="aliasHistoryMain">
                        <p class="aliasHistoryAlias"><?= htmlspecialchars($aliasValue, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="aliasHistoryMeta">
                            <?php if ($aliasCreatedAt !== '') { ?>
                                <span><?= htmlspecialchars($aliasCreatedAt, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php } ?>
                            <?php if ($aliasCreatedBy !== '') { ?>
                                <span>·</span>
                                <span><?= htmlspecialchars($aliasCreatedBy, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php } ?>
                        </p>
                    </div>
                    <div class="aliasHistoryActions">
                        <button
                            type="button"
                            class="aliasActionButton"
                            data-copy-alias-url="<?= htmlspecialchars($fullAliasUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="material-icons">content_copy</i>
                            <?= htmlspecialchars(uiText('modals.alias_history.copy', 'Copy'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            class="aliasActionButton aliasActionPrimary"
                            data-restore-alias="<?= htmlspecialchars($aliasValue, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="material-icons">restore</i>
                            <?= htmlspecialchars(uiText('modals.alias_history.restore', 'Make Active'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            class="aliasActionButton aliasActionDanger"
                            data-delete-alias="<?= htmlspecialchars($aliasValue, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="material-icons">delete</i>
                            <?= htmlspecialchars(uiText('modals.alias_history.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>