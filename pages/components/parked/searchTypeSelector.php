<?php
/*
 * PARKED FEATURE (not active in UI): search type selector.
 *
 * Restored from: pages/components/search.php
 * Parked on request to remove the All/Title/URL dropdown from the visible search bar.
 *
 * To restore later: include this snippet back into pages/components/search.php
 * and reconnect onchange="adjustSearchType(this.value)".
 */
?>
<select id="searchSelector" class="searchSelector shownSelect" aria-label="<?= htmlspecialchars(uiText('search.search_type', 'Search type'), ENT_QUOTES, 'UTF-8') ?>" onchange="adjustSearchType(this.value)">
    <option value="all" <?= $searchType === 'all' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.all', 'All'), ENT_QUOTES, 'UTF-8') ?></option>
    <?php if ($page === 'links') { ?>
        <option value="title" <?= $searchType === 'title' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.title', 'Title'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="url" <?= $searchType === 'url' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.url', 'URL'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="tags" <?= $searchType === 'tags' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.tags', 'Tags'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="groups" <?= $searchType === 'groups' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="shortlink" <?= $searchType === 'shortlink' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.shortlink', 'Shortlink'), ENT_QUOTES, 'UTF-8') ?></option>
    <?php } else if ($page === 'visitors') { ?>
        <option value="name" <?= $searchType === 'name' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.name', 'Name'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="title" <?= $searchType === 'title' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.title', 'Title'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="ip" <?= $searchType === 'ip' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.ip', 'IP'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="tags" <?= $searchType === 'tags' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.tags', 'Tags'), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="groups" <?= $searchType === 'groups' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?></option>
    <?php } else if ($page === 'groups') { ?>
        <option value="title" <?= $searchType === 'title' ? 'selected' : '' ?>><?= htmlspecialchars(uiText('search.title', 'Title'), ENT_QUOTES, 'UTF-8') ?></option>
    <?php } ?>
</select>