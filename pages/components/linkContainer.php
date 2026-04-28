<?php
if (!isset($link)) {
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);
  if (isset($data['link']) && !is_null($data['link'])) {
    $link = $data['link'];
  }
}

$shortlinkDisplayBase = isset($shortlinkBaseUrl) ? $shortlinkBaseUrl : ('https://' . $domain);
?>

<div class="outerLinkContainer" id="link<?= $link['id'] ?>">
  <?php
  $expiresTs = !empty($link['expires_at']) ? strtotime($link['expires_at']) : false;
  $isExpired = $expiresTs !== false && $expiresTs > 0 && $expiresTs < time();
  $isExpiringSoon = $expiresTs !== false && $expiresTs > time() && ($expiresTs - time()) <= 86400;
  $lastVisitedTs = !empty($link['last_visited_at']) ? strtotime($link['last_visited_at']) : false;
  $hasLastVisited = $lastVisitedTs !== false && $lastVisitedTs > 0;
  $createdTs = !empty($link['created_at']) ? strtotime($link['created_at']) : false;
  $hasCreatedAt = $createdTs !== false && $createdTs > 0;
  $isArchived = $link['status'] === 0 || $link['status'] === '0';
  $statusLabel = '';
  if ($isArchived && $isExpired) {
    $statusLabel = uiText('links.archived_expired', 'Archived + Expired');
  } elseif ($isArchived) {
    $statusLabel = uiText('links.archived', 'Archived');
  }
  $aliasCount = isset($link['alias_count']) ? (int) $link['alias_count'] : 0;
  ?>
  <div
    class="linkContainer linkSwitch container<?= $isArchived ? ' archived' : '' ?><?= isset($openId) && intval($openId) === intval($link['id']) ? ' open' : '' ?><?= $_SESSION['user']['view'] === 'view' ? ' view-mode' : '' ?>">
    <div class="archivedOverlay"></div>
    <?php if ($statusLabel) { ?>
      <hr data-content="<?= $statusLabel ?>" style="text-align: left; left: 120px; position: absolute; margin-top: 0; top: 5px"
        class="optionalDivider">
    <?php } ?>
    <div class="linkHeader<?php echo $_SESSION['user']['view'] === 'view' ? ' viewMode' : ''; ?>">
      <div class="titleContainer">
        <?php if (checkAdmin()) { ?>
          <div class="linkForm">
            <input type="checkbox" class="linkCheckbox checkbox" id="checkbox-<?= $link['id'] ?>"
              onclick="multiSelect(this.id, event)">
            <span class="checkBoxBackground"><i class="checkIcon material-icons">checkmark</i></span>
          </div>
        <?php } ?>
        <h3 class="linkTitle"><?= $link['title'] ?></h3>
        <div class="shownBody titleTags">
          <?php
          $hasVisibleTags = false;
          if (!empty($link['tags']) && is_array($link['tags'])) {
            foreach ($link['tags'] as $tag) {
              if (trim((string) ($tag['title'] ?? '')) !== '') {
                $hasVisibleTags = true;
                break;
              }
            }
          }
          if ($hasVisibleTags) { ?>
            <i class="day-icons tagTitle">&#xf04fc;</i>
            <?php foreach ($link['tags'] as $tag) {
              $tagTitle = trim((string) ($tag['title'] ?? ''));
              if ($tagTitle === '') {
                continue;
              }
            ?>
              <div id="<?= htmlspecialchars($tagTitle, ENT_QUOTES, 'UTF-8') ?>-link" class="tagContainer filterByTag">
                <p class="tag"><?= htmlspecialchars($tagTitle, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
          <?php }
          } ?>

          <?php
          $hasVisibleGroups = false;
          if (!empty($link['groups']) && is_array($link['groups'])) {
            foreach ($link['groups'] as $group) {
              if (trim((string) ($group['title'] ?? '')) !== '') {
                $hasVisibleGroups = true;
                break;
              }
            }
          }
          if ($hasVisibleGroups) { ?>
            <i class="groupTitle material-icons">folder</i>
            <?php foreach ($link['groups'] as $group) {
              $groupTitle = trim((string) ($group['title'] ?? ''));
              if ($groupTitle === '') {
                continue;
              }
            ?>
              <div id="<?= htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8') ?>-link" class="tagContainer filterByGroup">
                <p class="group"><?= htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
          <?php }
          } ?>
        </div>
      </div>
      <div class="linkActions fixedLocation">
        <a class="linkAction tooltip shownBody openInNew" href="<?= $shortlinkDisplayBase . '/' . $link['shortlink'] ?>"
          onclick="copyLink(this.href, event)">
          <i class="material-icons">content_copy</i>
          <span class="tooltiptext"><?= htmlspecialchars(uiText('links.copy_link', 'Copy Link'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php
        $isExpired = !empty($link['expires_at']) && strtotime($link['expires_at']) !== false && strtotime($link['expires_at']) < time();
        if ($isExpired) {
        ?>
          <a class="linkAction tooltip shownBody openInNew" href="#" onclick="return false;" style="opacity: 0.5; cursor: not-allowed;">
            <i class="material-icons">lock</i>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('links.link_expired', 'Link Expired'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        <?php } else { ?>
          <a class="linkAction tooltip shownBody openInNew" href="<?= $link['url'] ?>" target="_blank">
            <i class="material-icons">open_in_new</i>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('links.open_link', 'Open link'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        <?php } ?>
      </div>
      <div class="dateContainer shownBody">
        <div class="tooltip date">
          <p class="date"><?= $hasCreatedAt ? date('d M', $createdTs) : '-' ?></p>
          <span class="tooltiptext"><?= htmlspecialchars(uiText('links.created', 'Created'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php if ($hasLastVisited) { ?>
          <div class="tooltip date">
            <p class="date"><?= date('d M', $lastVisitedTs) ?></p>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('links.last_visited', 'Last Visited'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php } ?>

        <?php if ($expiresTs !== false && $expiresTs > 0) {
          $isExpired = $expiresTs < time();
          $isExpiringSoon = !$isExpired && ($expiresTs - time()) <= 86400; ?>
          <div class="tooltip date">
            <p class="date dateStatusBadge<?= $isExpired ? ' expired' : ($isExpiringSoon ? ' expiring' : '') ?>">
              <?= $isExpired ? htmlspecialchars(uiText('links.expired', 'Expired'), ENT_QUOTES, 'UTF-8') : date('d M', $expiresTs) ?></p>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('links.expires_prefix', 'Expires:'), ENT_QUOTES, 'UTF-8') ?> <?= date('j F Y - G:i', $expiresTs) ?></span>
          </div>
        <?php } ?>
      </div>
      <div class="linkFooter shownBody">
        <div class="urlContainer">
          <p class="shortLinkURL mobileHide"><?= preg_replace('#^https?://#', '', $shortlinkDisplayBase) ?>/<?= $link['shortlink'] ?></p>
          <p class="shortLinkURL mobileShow"><?= preg_replace('#^https?://#', '', $shortlinkDisplayBase) ?>/<?= $link['shortlink'] ?></p>
          <?php if ($aliasCount > 0) { ?>
            <a class="aliasCountPreview" onclick="createModal('/createModal?comp=aliasHistory&id=<?= (int) $link['id'] ?>')"
              title="<?= htmlspecialchars(uiText('links.shortlink_aliases', 'Shortlink aliases'), ENT_QUOTES, 'UTF-8') ?>">
              <?= $aliasCount ?>
            </a>
          <?php } ?>
        </div>
        <div class="visitCounter">
          <div class="visitCount tooltip">
            <p class="visitCount"><?= (int)($link['unique_visitors'] ?? 0) ?></p>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('links.unique_visitors', 'Unique Visitors'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <p>&nbsp;I&nbsp;</p>
          <div class="visitCount tooltip">
            <a href="/statistics?id=<?= $link['id'] ?>">
              <p class="visitCount"><?= (int)($link['visit_count'] ?? 0) ?></p>
            </a>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('links.visits', 'Visits'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <i class="material-icons" style="font-size: 16px;">&nbsp;person&nbsp;</i>
          <div class="visitCount tooltip">
            <p class="visitCount"><?= $link['visitsToday'] ? count($link['visitsToday']) : 0 ?></p>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('links.todays_visits', "Today's Visits"), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </div>
      <div class="linkBodyWrapper">
        <div class="linkBody hiddenBody linkQuickSettings">
          <div id="favourite-<?= $link['id'] ?>" class="favContainer" onclick="favourite(this.id)">
            <i class="material-icons favStar"
              style="color: <?= !empty($link['favourite']) ? 'var(--primary-color)' : '' ?>">grade</i>
          </div>
          <?php if (checkAdmin()) { ?>
            <div class="actions sameLine">
              <div class="action">
                <a class="linkAction editLink" onclick="createModal('/editModal?id=<?= $link['id'] ?>&comp=links')"><i
                    class="material-icons">edit</i><?= htmlspecialchars(uiText('links.edit', 'Edit'), ENT_QUOTES, 'UTF-8') ?></a>
              </div>
              <div class="action">
                <a class="linkAction" href="/duplicateLink?id=<?= $link['id'] ?>"><i
                    class="material-icons">file_copy</i><?= htmlspecialchars(uiText('links.clone', 'Clone'), ENT_QUOTES, 'UTF-8') ?></a>
              </div>
              <div class="action" onclick="createPopupModal('/deleteLink?id=<?= $link['id'] ?>&comp=links', this, event)"
                id="delete-<?= $link['id'] ?>">
                <a class="linkAction deleteLink"><i class="material-icons">delete</i><?= htmlspecialchars(uiText('links.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?></a>
              </div>
              <div class="action" id="status-<?= $link['id'] ?>"
                onclick="switchStatus(<?= $link['id'] ?>, <?= $link['status'] ?>)">
                <a class="linkAction">
                  <i class="day-icons">&#xf0521;</i>
                  <?= htmlspecialchars(uiText('links.status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                </a>
              </div>
              <div class="action">
                <a class="linkAction" href="/statistics?id=<?= $link['id'] ?>">
                  <i class="material-icons">bar_chart</i>
                  <?= htmlspecialchars(uiText('links.stats', 'Stats'), ENT_QUOTES, 'UTF-8') ?>
                </a>
              </div>
              <div class="action">
                <a class="linkAction" onclick="createModal('/createModal?comp=aliasHistory&id=<?= $link['id'] ?>')">
                  <i class="material-icons">history</i>
                  <?= htmlspecialchars(uiText('links.alias_history', 'Alias History'), ENT_QUOTES, 'UTF-8') ?>
                </a>
              </div>
            </div>
          <?php } ?>
          <div class="shortlinkBody">
            <div class="sameLine">
              <a class="linkAction tooltip" href="<?= $isExpired ? '#' : $link['url'] ?>" <?= $isExpired ? 'onclick="return false;" style="opacity:0.5; cursor:not-allowed;"' : 'target="_blank"' ?>>
                <i class="material-icons">open_in_new</i>
                <span class="tooltiptext"><?= $isExpired ? htmlspecialchars(uiText('links.link_expired', 'Link Expired'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(uiText('links.open_link', 'Open link'), ENT_QUOTES, 'UTF-8') ?></span>
              </a>
              <a class="shortlinkText linkAction" href="<?= $isExpired ? '#' : ($shortlinkDisplayBase . '/' . $link['shortlink']) ?>"
                <?= $isExpired ? 'onclick="return false;" style="opacity:0.5; cursor:not-allowed;"' : 'target="_blank"' ?>><?= $shortlinkDisplayBase ?>/<?= $link['shortlink'] ?></a>
            </div>
            <a id="copyLink" class="linkAction" href="<?= $shortlinkDisplayBase . '/' . $link['shortlink'] ?>" onclick="copyLink(this.href, event)"><i
                class="material-icons">content_copy</i></a>
            <div
              onclick="createPopupModal('/createModal?comp=qrModal&qrRef=<?= rawurlencode($shortlinkDisplayBase . '/' . $link['shortlink']) ?>', this, event)"
              class="showQr tooltip" id="getQrCode-<?= $link['id'] ?>">
              <i class="material-icons">qr_code</i>
              <span class="tooltiptext"><?= htmlspecialchars(uiText('links.scan_qr_code', 'Scan QR Code'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
          <?php if ($aliasCount > 0) { ?>
            <div class="linkAliasesSection">
              <p class="date" style="margin-bottom: 6px;"><?= htmlspecialchars(uiText('links.shortlink_aliases', 'Shortlink aliases'), ENT_QUOTES, 'UTF-8') ?></p>
              <div class="linkAliasesList">
                <a class="tagContainer" onclick="createModal('/createModal?comp=aliasHistory&id=<?= (int) $link['id'] ?>')" style="cursor:pointer;">
                  <?= $aliasCount === 1
                    ? '1 ' . htmlspecialchars(uiText('links.alias_singular', 'alias'), ENT_QUOTES, 'UTF-8')
                    : $aliasCount . ' ' . htmlspecialchars(uiText('links.alias_plural', 'aliases'), ENT_QUOTES, 'UTF-8') ?>
                </a>
              </div>
            </div>
          <?php } ?>
          <div class="secondaireData">
            <div class="qrFullCon">
              <div class="fullLinkCon tooltip destinationDragZone" data-horizontal-drag="true">
                <i class="material-icons">link</i>
                <a href="<?= $isExpired ? '#' : $link['url'] ?>" class="fullLink mobileHide" <?= $isExpired ? 'onclick="return false;" style="opacity:0.5; cursor:not-allowed;"' : 'target="_blank"' ?>><?= $link['url'] ?></a>
                <a href="<?= $isExpired ? '#' : $link['url'] ?>" class="fullLink mobileShow"
                  <?= $isExpired ? 'onclick="return false;" style="opacity:0.5; cursor:not-allowed;"' : 'target="_blank"' ?>><?= str_replace('https://', '', $link['url']) ?></a>
                <span class="tooltiptext"><?= $link['url'] ?></span>
              </div>
            </div>
            <div class="tagsGroupsCon">
              <fieldset class="tagsCon">
                <legend><?= htmlspecialchars(uiText('links.tags', 'Tags'), ENT_QUOTES, 'UTF-8') ?>:</legend>
                <?php if ($link['tags']) { ?>
                  <?php foreach ($link['tags'] as $tag) {
                    if (strlen($tag['title']) === 0) {
                      continue;
                    } ?>
                    <div id="<?= $tag['title'] ?>-link" class="tagContainer filterByTag">
                      <p class="tag"><?= $tag['title'] ?></p>
                    </div>
                  <?php } ?>
                <?php } ?>
              </fieldset>
              <fieldset class="groupsCon">
                <legend><?= htmlspecialchars(uiText('links.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?>:</legend>
                <?php if ($link['groups']) { ?>
                  <?php foreach ($link['groups'] as $group) {
                    if (strlen($group['title']) === 0) {
                      continue;
                    }  ?>
                    <div id="<?= $group['title'] ?>-link" class="tagContainer filterByGroup">
                      <p class="group"><?= $group['title'] ?></p>
                    </div>
                  <?php } ?>
                <?php } ?>
              </fieldset>
            </div>
            <div class="DateViewsCon">
              <div class="daviBorder">
                <div class="dateEntry createdCon">
                  <i class="material-icons" style="font-size: 18px; opacity: 0.8;">edit_calendar</i>
                  <p class="date mobileHide">
                    <?= is_numeric($link['creator']) ? getEmailById($link['creator']) : $link['creator'] ?></p>
                  <p class="mobileHide">-</p>
                  <p class="date mobileHide"><?= date('j F Y - G:i', strtotime($link['created_at'])) ?></p>
                  <p class="date mobileShow"><?= date('d M', strtotime($link['created_at'])) ?></p>
                </div>
                <div class="modifiedCon dateEntry">
                  <i class="material-icons" style="font-size: 18px; opacity: 0.8;">edit</i>
                  <p class="date mobileHide">
                    <?= is_numeric($link['modifier']) ? getEmailById($link['modifier']) : $link['modifier'] ?></p>
                  <p class="mobileHide">-</p>
                  <p class="date mobileHide"><?= date('j F Y - G:i', strtotime($link['modified_at'])) ?></p>
                  <p class="date mobileShow"><?= date('d M', strtotime($link['modified_at'])) ?></p>
                </div>

                <?php if (!empty($link['expires_at'])) {
                  $isExpired = strtotime($link['expires_at']) !== false && strtotime($link['expires_at']) < time(); ?>
                  <div class="dateEntry">
                    <i class="material-icons" style="font-size: 18px; opacity: 0.8;">event</i>
                    <p class="date mobileHide" style="color: <?= $isExpired ? '#d9534f' : '' ?>">
                      <?= $isExpired ? htmlspecialchars(uiText('links.expired', 'Expired'), ENT_QUOTES, 'UTF-8') : date('j F Y - G:i', strtotime($link['expires_at'])) ?></p>
                    <p class="date mobileShow" style="color: <?= $isExpired ? '#d9534f' : '' ?>">
                      <?= $isExpired ? htmlspecialchars(uiText('links.expired', 'Expired'), ENT_QUOTES, 'UTF-8') : date('d M', strtotime($link['expires_at'])) ?></p>
                  </div>
                <?php } ?>
                <div class="viewsCon dateEntry">
                  <div class="visitCount tooltip">
                    <p class="visitCount"><?= (int)($link['visit_count'] ?? 0) ?></p>
                    <span class="tooltiptext"><?= htmlspecialchars(uiText('links.visits', 'Visits'), ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                  <div class="tooltip">
                    <i class="material-icons" style="font-size: 16px; opacity: 0.8;">&nbsp;visibility&nbsp;</i>
                    <span class="tooltiptext"><?= htmlspecialchars(uiText('links.last_visit_prefix', 'Last Visit:'), ENT_QUOTES, 'UTF-8') ?> <br>
                      <?= $hasLastVisited ? date('d M', $lastVisitedTs) : htmlspecialchars(uiText('links.no_visits_yet', 'No visits yet'), ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                  <div class="visitCount tooltip">
                    <p class="visitCount"><?= $link['visitsToday'] ? count($link['visitsToday']) : 0 ?></p>
                    <span class="tooltiptext"><?= htmlspecialchars(uiText('links.todays_visits', "Today's Visits"), ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                  <!-- <div class="visitCount tooltip">
                  <p class="visitCount"><?= isset($link['unique_visitors']) ? $link['unique_visitors'] : 0 ?></p>
                  <span class="tooltiptext">Unique Visits</span>
                </div> -->
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function() {
    function adjustTitleTags() {
      document.querySelectorAll('.titleTags').forEach(function(container) {
        const tagEls = Array.from(container.querySelectorAll('.tagContainer.filterByTag'));
        const groupEls = Array.from(container.querySelectorAll('.tagContainer.filterByGroup'));

        // find server-rendered +N elements (tags)
        const plusTagEl = Array.from(container.querySelectorAll('.tagContainer')).find(function(el) {
          const p = el.querySelector('p');
          return p && /^\+\s*\d+/.test(p.textContent.trim());
        });

        // find server-rendered +N for groups (p.group starting with '+')
        const plusGroupP = Array.from(container.querySelectorAll('p.group')).find(function(p) {
          return /^\+\s*\d+/.test(p.textContent.trim());
        });

        // initialize original totals from server-rendered +N if present
        if (plusTagEl && !plusTagEl.dataset.originalTotal) {
          const num = parseInt(plusTagEl.querySelector('p').textContent.replace('+', '').trim()) || 0;
          plusTagEl.dataset.originalTotal = num + 2; // server showed first 2
        }
        if (plusGroupP && !plusGroupP.dataset.originalTotal) {
          const num = parseInt(plusGroupP.textContent.replace('+', '').trim()) || 0;
          plusGroupP.dataset.originalTotal = num + 2;
        }

        if (window.innerWidth < 1100) {
          // Tags: show only first tag
          if (tagEls.length > 1) {
            tagEls.forEach(function(el, idx) {
              if (idx >= 1) el.style.display = 'none';
            });
          }
          if (plusTagEl) {
            const originalTotal = parseInt(plusTagEl.dataset.originalTotal) || (tagEls.length + (parseInt(plusTagEl
              .querySelector('p').textContent.replace('+', '').trim()) || 0));
            const newPlus = Math.max(0, originalTotal - 1);
            plusTagEl.querySelector('p').textContent = (newPlus > 0 ? '+ ' + newPlus : '');
            plusTagEl.style.display = newPlus > 0 ? '' : 'none';
          }

          // Groups: show only first group
          if (groupEls.length > 1) {
            groupEls.forEach(function(el, idx) {
              if (idx >= 1) el.style.display = 'none';
            });
          }
          if (plusGroupP) {
            const originalTotalG = parseInt(plusGroupP.dataset.originalTotal) || (groupEls.length + (parseInt(
              plusGroupP.textContent.replace('+', '').trim()) || 0));
            const newPlusG = Math.max(0, originalTotalG - 1);
            plusGroupP.textContent = (newPlusG > 0 ? '+ ' + newPlusG : '');
            if (plusGroupP.parentElement) plusGroupP.parentElement.style.display = newPlusG > 0 ? '' : 'none';
          }
        } else {
          // restore default (server) state: show first two tags/groups and set +N back to original
          if (tagEls.length > 0) {
            tagEls.forEach(function(el) {
              el.style.display = '';
            });
          }
          if (plusTagEl) {
            const originalTotal = parseInt(plusTagEl.dataset.originalTotal) || (tagEls.length + (parseInt(plusTagEl
              .querySelector('p').textContent.replace('+', '').trim()) || 0));
            const serverPlus = Math.max(0, originalTotal - 2);
            plusTagEl.querySelector('p').textContent = (serverPlus > 0 ? '+ ' + serverPlus : '');
            plusTagEl.style.display = serverPlus > 0 ? '' : 'none';
          }

          if (groupEls.length > 0) {
            groupEls.forEach(function(el) {
              el.style.display = '';
            });
          }
          if (plusGroupP) {
            const originalTotalG = parseInt(plusGroupP.dataset.originalTotal) || (groupEls.length + (parseInt(
              plusGroupP.textContent.replace('+', '').trim()) || 0));
            const serverPlusG = Math.max(0, originalTotalG - 2);
            plusGroupP.textContent = (serverPlusG > 0 ? '+ ' + serverPlusG : '');
            if (plusGroupP.parentElement) plusGroupP.parentElement.style.display = serverPlusG > 0 ? '' : 'none';
          }
        }
      });
    }

    // run on load and on resize (debounced)
    document.addEventListener('DOMContentLoaded', function() {
      adjustTitleTags();
      let resizeTimer;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(adjustTitleTags, 150);
      });
    });
  })();
</script>