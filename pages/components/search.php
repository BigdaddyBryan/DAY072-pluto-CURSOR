<?php
// echo "<script>console.log('Debug Objects: ', " . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ");</script>";
$limit = isset($_SESSION['user']['limit']) ? (int) $_SESSION['user']['limit'] : 10;
$sortPreference = isset($_SESSION['user']['sort_preference']) ? (string) $_SESSION['user']['sort_preference'] : 'latest_modified';
$searchType = isset($_GET['searchType']) ? $_GET['searchType'] : 'all';
$hasMultiplePages = count($data) > (int) $limit;
?>

<input
  type="hidden"
  id="pageLocation"
  value="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>"
  data-initial-limit="<?= (int) $limit ?>"
  data-initial-sort="<?= htmlspecialchars($sortPreference, ENT_QUOTES, 'UTF-8') ?>">
<div class="navigationMax">
  <div class="navigation">
    <div class="searchContainer">
      <div class="searchBarContainer">
        <div id="searchBar" class="searchBar">
          <i class="material-icons searchIcon">search</i>
          <input type="text" class="searchInput" id="searchbar" autofocus />
          <label for="searchbar" id="searchLabel" class="searchLabel"><?= htmlspecialchars(uiText('search.placeholder', 'Search'), ENT_QUOTES, 'UTF-8') ?></label>
          <h4 class="version searchVersion" aria-hidden="true"><?= $version ?></h4>
          <?php
          // PARKED FEATURE: search type selector is stored in pages/components/parked/searchTypeSelector.php
          // It is intentionally removed from the visible UI and can be restored from that file later.
          ?>
          <div class="activeFilters sameLine" id="activeFilters">
          </div>
          <button type="button" class="searchBarClearBtn" id="searchBarClearBtn" style="display:none" onclick="if(document.getElementById('clearFilter'))document.getElementById('clearFilter').click()">
            <i class="material-icons">close</i>
          </button>
        </div>
        <div class="filtersContainer">
          <?php if ($page !== 'groups') { ?>
            <?php if ($page === 'users') { ?>
              <div class="filterContainer tooltip bottomtool">
                <button id="openFilterPanel" class="filterButton inactive"><i
                    class="material-icons">tune</i></button>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('search.open_filter_panel', 'Filters'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <button id="filterTagsGroups" style="display:none" aria-hidden="true" tabindex="-1"></button>
            <?php } else { ?>
              <div class="filterContainer tooltip bottomtool">
                <button id="filterTagsGroups" class="filterButton inactive"><i
                    class="material-icons">filter_alt</i></button>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('search.filter_tags_groups', 'Filter by tags and groups'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            <?php } ?>
          <?php } ?>
          <div class="resetContainer tooltip bottomtool desktopHide">
            <button id="resetFilter" class="resetButton inactive mobileHide"><i
                class="material-icons">restart_alt</i></button>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('search.reset_filters', 'Reset all filters and sorting'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="sortContainer tooltip bottomtool sortContainerIcon">
            <button id="sortLinks" class="sortButton inactive"><i class="material-icons day-icons"
                id="sortIcon">sort</i></button>
            <span class="tooltiptext" id="sortOptionTooltip"><?= htmlspecialchars(uiText('search.sort_by_title', 'Sort by title'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="clearContainer tooltip bottomtool desktopHide">
            <button id="clearFilter" class="clearButton inactive mobileHide"><i
                class="material-icons">clear</i></button>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('search.clear_search', 'Clear search and filters'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <?php if (checkAdmin()) { ?>
            <div class="selectModeContainer tooltip bottomtool desktopHide">
              <button id="enterSelectMode" class="selectModeButton inactive" onclick="if(typeof enterSelectionMode==='function')enterSelectionMode()"><i
                  class="material-icons">checklist</i></button>
              <span class="tooltiptext">Multi-select</span>
            </div>
          <?php } ?>
        </div>
        <button type="button" id="sortDropdownBtn" class="sortDropdownBtn">
          <span id="sortDropdownLabel"><?= htmlspecialchars(uiText('search.sort_by_title', 'Sort by title'), ENT_QUOTES, 'UTF-8') ?></span>
          <i class="material-icons sortDropdownArrow">arrow_drop_down</i>
        </button>
        <div class="topPaginationContainer">
          <div class="pageContainer topPageContainer" <?= $hasMultiplePages ? '' : ' style="display:none"' ?>>
            <button class="pageButton" data-action="first"><i class="material-icons">keyboard_double_arrow_left</i></button>
            <button class="pageButton" data-action="previous"><i class="material-icons">chevron_left</i></button>
            <button class="pageButton page activePage" data-page="0">1</button>
            <button class="pageButton page" data-page="1">2</button>
            <button class="pageButton page" data-page="2">3</button>
            <button class="pageButton" data-action="next"><i class="material-icons">chevron_right</i></button>
            <button class="pageButton" data-action="last"><i class="material-icons">keyboard_double_arrow_right</i></button>
          </div>
        </div>
        <button type="button" id="pageSizeDropdownBtn" class="pageSizeDropdownBtn" aria-label="<?= htmlspecialchars(uiText('search.items_per_page', 'Items per page'), ENT_QUOTES, 'UTF-8') ?>" data-value="<?= (int)$limit ?>">
          <span id="pageSizeDropdownLabel"><?= (int)$limit ?></span>
          <i class="material-icons sortDropdownArrow">arrow_drop_down</i>
        </button>
        <?php if ($page === 'groups') { ?>
          <div class="groupSizeControl" id="groupSizeControl">
            <input type="range" id="groupThumbSize" min="220" max="420" step="10" value="300" aria-label="<?= htmlspecialchars(uiText('search.thumbnail_size', 'Thumbnail size'), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="groupViewModes" id="groupViewModes">
            <button type="button" class="groupViewToggle" id="groupViewCompact" data-view="compact" aria-label="<?= htmlspecialchars(uiText('search.compact_grid_view', 'Compact grid view'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="material-icons">grid_view</i>
            </button>
            <button type="button" class="groupViewToggle" id="groupViewDetailed" data-view="detailed" aria-label="<?= htmlspecialchars(uiText('search.detailed_list_view', 'Detailed list view'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="material-icons">view_list</i>
            </button>
            <button type="button" class="groupViewToggle" id="groupViewThumbnail" data-view="thumbnail" aria-label="<?= htmlspecialchars(uiText('search.large_thumbnail_view', 'Large thumbnail view'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="material-icons">photo_size_select_large</i>
            </button>
          </div>
        <?php } ?>
      </div>
    </div>
    <div class="multiSelect" id="multiSelect">
      <div class="selectionModeHeader" id="selectionModeHeader">
        <!-- Gmail-style master checkbox + count -->
        <div class="selectionModeLeft">
          <div class="multiMasterControl" id="multiMasterControl">
            <input type="checkbox" class="multiMasterCheckbox" id="multiMasterCheckbox"
              title="<?= htmlspecialchars(uiText('search.select_all', 'Select all'), ENT_QUOTES, 'UTF-8') ?>">
            <button
              type="button"
              class="multiMasterMenuButton"
              id="multiMasterMenuButton"
              aria-label="<?= htmlspecialchars(uiText('search.selection_options', 'Selection options'), ENT_QUOTES, 'UTF-8') ?>"
              aria-haspopup="true"
              aria-expanded="false">
              <i class="material-icons">arrow_drop_down</i>
            </button>
            <div class="multiMasterMenu" id="multiMasterMenu" aria-hidden="true">
              <button type="button" class="multiMasterMenuItem" data-mode="all"><?= htmlspecialchars(uiText('search.all', 'All'), ENT_QUOTES, 'UTF-8') ?></button>
              <?php if ($page === 'links' || $page === 'users') { ?>
                <button type="button" class="multiMasterMenuItem multiMasterMenuItemAllResults" data-mode="all-filtered"><?= htmlspecialchars(uiText('search.all_results', 'All results'), ENT_QUOTES, 'UTF-8') ?></button>
              <?php } ?>
              <button type="button" class="multiMasterMenuItem" data-mode="none"><?= htmlspecialchars(uiText('search.none', 'None'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
          </div>
          <div class="mobileSelectionQuickActions" id="mobileSelectionQuickActions">
            <button type="button" class="mobileSelectionQuickBtn" data-mode="all"><?= htmlspecialchars(uiText('search.all', 'All'), ENT_QUOTES, 'UTF-8') ?></button>
            <?php if ($page === 'links' || $page === 'users') { ?>
              <button type="button" class="mobileSelectionQuickBtn mobileSelectionQuickBtnAllResults" data-mode="all-filtered"><?= htmlspecialchars(uiText('search.all_results', 'All results'), ENT_QUOTES, 'UTF-8') ?></button>
            <?php } ?>
            <button type="button" class="mobileSelectionQuickBtn" data-mode="none"><?= htmlspecialchars(uiText('search.none', 'None'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
          <div class="selectionModeCount" id="selectionModeCount">0 <?= htmlspecialchars(uiText('js.search.selected', 'selected'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="selectionModeDivider"></div>
        <!-- Icon action buttons -->
        <div class="selectionBulkControls">
          <div class="multiActionIcons">
            <button type="button" class="multiActionBtn multiActionDelete tooltip bottomtool" data-action="delete"
              title="<?= htmlspecialchars(uiText('search.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="material-icons">delete</i>
              <span class="tooltiptext"><?= htmlspecialchars(uiText('search.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <?php if ($page === 'links') { ?>
              <button type="button" class="multiActionBtn tooltip bottomtool" data-action="archive"
                title="<?= htmlspecialchars(uiText('search.archive', 'Archive'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="material-icons">archive</i>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('search.archive', 'Archive'), ENT_QUOTES, 'UTF-8') ?></span>
              </button>
            <?php } elseif ($page === 'groups') { ?>
              <button type="button" class="multiActionBtn is-disabled tooltip bottomtool" aria-disabled="true"
                title="<?= htmlspecialchars(uiText('search.not_available_for_groups', 'Not available for groups'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="material-icons">archive</i>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('search.not_available_for_groups', 'Not available for groups'), ENT_QUOTES, 'UTF-8') ?></span>
              </button>
            <?php } elseif ($page === 'users' && checkAdmin()) { ?>
              <div class="multiActionDivider"></div>
              <button type="button" class="multiActionBtn tooltip bottomtool" data-action="roleSet"
                title="<?= htmlspecialchars(uiText('users.set_role', 'Set role'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="material-icons">manage_accounts</i>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('users.set_role', 'Set role'), ENT_QUOTES, 'UTF-8') ?></span>
              </button>
              <?php if ((string)($_SESSION['user']['role'] ?? '') === 'superadmin') { ?>
                <button type="button" class="multiActionBtn tooltip bottomtool" data-action="logout"
                  title="<?= htmlspecialchars(uiText('users.sign_out_everywhere', 'Sign out everywhere'), ENT_QUOTES, 'UTF-8') ?>">
                  <i class="material-icons">devices</i>
                  <span class="tooltiptext"><?= htmlspecialchars(uiText('users.sign_out_everywhere', 'Sign out everywhere'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
              <?php } ?>
            <?php } ?>
            <?php if ($page !== 'groups' && $page !== 'users') { ?>
              <div class="multiActionDivider"></div>
              <button type="button" class="multiActionBtn tooltip bottomtool" data-action="tagSync"
                title="<?= htmlspecialchars(uiText('search.manage_tags', 'Manage tags'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="material-icons">label</i>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('search.manage_tags', 'Manage tags'), ENT_QUOTES, 'UTF-8') ?></span>
              </button>
              <button type="button" class="multiActionBtn tooltip bottomtool" data-action="groupSync"
                title="<?= htmlspecialchars(uiText('search.manage_groups', 'Manage Groups'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="material-icons">folder</i>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('search.manage_groups', 'Manage Groups'), ENT_QUOTES, 'UTF-8') ?></span>
              </button>
            <?php } elseif ($page === 'groups') { ?>
              <div class="multiActionDivider"></div>
              <button type="button" class="multiActionBtn is-disabled tooltip bottomtool" aria-disabled="true"
                title="<?= htmlspecialchars(uiText('search.not_available_for_groups', 'Not available for groups'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="material-icons">label</i>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('search.not_available_for_groups', 'Not available for groups'), ENT_QUOTES, 'UTF-8') ?></span>
              </button>
              <button type="button" class="multiActionBtn is-disabled tooltip bottomtool" aria-disabled="true"
                title="<?= htmlspecialchars(uiText('search.not_available_for_groups', 'Not available for groups'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="material-icons">folder</i>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('search.not_available_for_groups', 'Not available for groups'), ENT_QUOTES, 'UTF-8') ?></span>
              </button>
            <?php } ?>
          </div>
          <div class="selectionModeChanges" id="selectionModeChanges" style="display:none;">
            <span id="selectionModeChangesCount">0</span> <?= htmlspecialchars(uiText('js.multi.pending_changes', 'changes'), ENT_QUOTES, 'UTF-8') ?>
          </div>
          <!-- Hidden select for JS state -->
          <select id="multiSelector" class="multiSelector" style="display:none">
            <?php if ($page === 'groups') { ?>
              <option value="delete"><?= htmlspecialchars(uiText('search.delete_groups', 'Delete groups'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } elseif ($page === 'visitors') { ?>
              <option value="delete"><?= htmlspecialchars(uiText('search.delete_visitors', 'Delete visitors'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="tagSync"><?= htmlspecialchars(uiText('search.manage_tags', 'Manage tags'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="groupSync"><?= htmlspecialchars(uiText('search.manage_groups', 'Manage Groups'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } elseif ($page === 'users') { ?>
              <option value="delete"><?= htmlspecialchars(uiText('users.delete_users', 'Delete users'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="roleSet"><?= htmlspecialchars(uiText('users.set_role', 'Set role'), ENT_QUOTES, 'UTF-8') ?></option>
              <?php if ((string)($_SESSION['user']['role'] ?? '') === 'superadmin') { ?>
                <option value="logout"><?= htmlspecialchars(uiText('users.sign_out_everywhere', 'Sign out everywhere'), ENT_QUOTES, 'UTF-8') ?></option>
              <?php } ?>
            <?php } else { ?>
              <option value="delete"><?= htmlspecialchars(uiText('search.delete_links', 'Delete links'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="archive"><?= htmlspecialchars(uiText('search.archive', 'Archive'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="tagSync"><?= htmlspecialchars(uiText('search.manage_tags', 'Manage tags'), ENT_QUOTES, 'UTF-8') ?></option>
              <option value="groupSync"><?= htmlspecialchars(uiText('search.manage_groups', 'Manage Groups'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php } ?>
          </select>
          <?php if ($page !== 'groups') { ?>
            <div class="multiActionDropdown" id="multiActionDropdown" aria-hidden="true">
              <div class="multiActionDropdownHeader">
                <p class="multiActionDropdownTitle" id="multiActionDropdownTitle"><?= htmlspecialchars(uiText('search.manage_tags', 'Manage tags'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="multiActionDropdownMetaRow">
                  <p class="multiActionDropdownSummary" id="multiActionDropdownSummary">0 <?= htmlspecialchars(uiText('js.search.selected', 'selected'), ENT_QUOTES, 'UTF-8') ?></p>
                  <div class="multiActionScopeToggle" id="multiActionScopeToggle" aria-label="<?= htmlspecialchars(uiText('search.scope', 'Selection scope'), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="multiActionScopeBtn active" data-scope="union"><?= htmlspecialchars(uiText('search.any', 'Any'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="button" class="multiActionScopeBtn" data-scope="intersection"><?= htmlspecialchars(uiText('search.all', 'All'), ENT_QUOTES, 'UTF-8') ?></button>
                  </div>
                </div>
                <input
                  type="text"
                  id="multiActionDropdownSearch"
                  class="multiActionDropdownSearch"
                  placeholder="<?= htmlspecialchars(uiText('search.search', 'Search'), ENT_QUOTES, 'UTF-8') ?>"
                  autocomplete="off">
              </div>
              <div class="multiActionDropdownList" id="multiActionDropdownList"></div>
              <div class="multiActionDropdownError" id="multiActionDropdownError"></div>
              <div class="multiActionDropdownFooter">
                <button id="multiActionDropdownCreateGroup" class="multiButton multiButtonGhost" type="button" hidden>
                  <i class="material-icons multiButtonIcon">add</i>
                  <span class="multiButtonLabel"><?= htmlspecialchars(uiText('search.create_group', 'Create group'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <button id="multiActionDropdownApply" class="multiButton" type="button">
                  <span class="multiButtonLabel"><?= htmlspecialchars(uiText('search.apply', 'Apply'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
              </div>
            </div>
          <?php } ?>
        </div>
        <!-- Collection dropdown (1024px breakpoint) -->
        <button type="button" class="collectionDropdownBtn" id="collectionDropdownBtn"
          aria-label="<?= htmlspecialchars(uiText('search.collection', 'Collection'), ENT_QUOTES, 'UTF-8') ?>">
          <span class="collectionDropdownLabel" id="collectionDropdownLabel"><?= htmlspecialchars(uiText('search.collection', 'Collection'), ENT_QUOTES, 'UTF-8') ?></span>
          <i class="material-icons collectionDropdownArrow">arrow_drop_down</i>
        </button>
      </div>
    </div>
    <div class="countContainer desktopHide">
      <div class="countWrapper">
        <div class="countRow">
          <h4 class="countText countLabel"><?= htmlspecialchars(uiText('search.showing', 'Showing'), ENT_QUOTES, 'UTF-8') ?></h4>

          <!-- Desktop selector (also used by JS) -->
          <div class="shownSelectWrapper shownSelectInline">
            <select class="shownSelect shownSelectTop" id="shownSelect" aria-label="<?= htmlspecialchars(uiText('search.items_per_page', 'Items per page'), ENT_QUOTES, 'UTF-8') ?>">
              <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
              <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
              <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
              <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
            </select>
          </div>

          <span> <?= htmlspecialchars(uiText('search.of', 'of'), ENT_QUOTES, 'UTF-8') ?> </span>
          <?php if ($page === 'links') { ?>
            <h4 id="linkCount" class="countValue"><?= linkCount() ?></h4>
            <span> <?= htmlspecialchars(uiText('search.links_count', 'links'), ENT_QUOTES, 'UTF-8') ?></span>
          <?php } else if ($page === 'visitors') { ?>
            <h4 id="visitorCount" class="countValue"><?= visitorCount() ?></h4>
            <span> <?= htmlspecialchars(uiText('search.visitors_count', 'visitors'), ENT_QUOTES, 'UTF-8') ?></span>
          <?php } else if ($page === 'groups') { ?>
            <h4 id="groupCount" class="countValue"><?= groupCount() ?></h4>
            <span> <?= htmlspecialchars(uiText('search.groups_count', 'groups'), ENT_QUOTES, 'UTF-8') ?></span>
          <?php } else if ($page === 'users') { ?>
            <h4 id="userCount" class="countValue"><?= count($data) ?></h4>
            <span> <?= htmlspecialchars(uiText('search.users_count', 'users'), ENT_QUOTES, 'UTF-8') ?></span>
          <?php } ?>
        </div>

        <!-- Keep original dataCount for compatibility -->
        <div class="dataCountHidden">
          <h4 id="dataCount"><?= count($data) ?></h4>
        </div>
      </div>
    </div>
    <div class="shownContainer desktopHide">
      <?php if (checkAdmin()) { ?>
        <div class="selectionSyncHidden" aria-hidden="true">
          <input type="checkbox" id="selectAll" class="linkCheckbox">
          <span class="checkBoxBackground"><i class="checkIcon material-icons">checkmark</i></span>
          <span id="selectAllText"><?= htmlspecialchars(uiText('search.select_all', 'Select all'), ENT_QUOTES, 'UTF-8') ?></span>
          <span id="totalSelected"></span>
        </div>
      <?php } ?>

      <div class="pageSpacer"></div>

      <div class="pageContainer" <?= $hasMultiplePages ? '' : ' style="display:none"' ?>>
        <button class="pageButton" data-action="first"><i class="material-icons">keyboard_double_arrow_left</i></button>
        <button class="pageButton" data-action="previous" id="previousPage"><i
            class="material-icons">chevron_left</i></button>
        <button class="pageButton page activePage" data-page="0">1</button>
        <button class="pageButton page" data-page="1">2</button>
        <button class="pageButton page" data-page="2">3</button>
        <button class="pageButton" data-action="next" id="nextPage"><i class="material-icons">chevron_right</i></button>
        <button class="pageButton" data-action="last"><i class="material-icons">keyboard_double_arrow_right</i></button>
      </div>
    </div>
  </div>
</div>
<script src="/javascript/filter.js" defer></script>