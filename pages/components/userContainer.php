<?php
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if 'link' is set and not null
if (isset($data['link']) && !is_null($data['link'])) {
  $user = $data['link'];
}

$appTimezone = new DateTimeZone(date_default_timezone_get());
$now = new DateTimeImmutable('now', $appTimezone);
$hasValidLastLogin = !empty($user['last_login']) && $user['last_login'] !== '0000-00-00 00:00:00';
$isSuperAdminSession = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'superadmin';

$userIdString = (string) ($user['id'] ?? '');
$activeDeviceCount = (int) ($activeDeviceSessionCounts[$userIdString] ?? 0);
$lastDeviceSeenTimestamp = (int) ($latestDeviceSeenTimestamps[$userIdString] ?? 0);
$lastDeviceSeenLabel = uiText('users.never', 'Never');
if ($lastDeviceSeenTimestamp > 0) {
  $delta = max(0, $now->getTimestamp() - $lastDeviceSeenTimestamp);
  if ($delta < 60) {
    $lastDeviceSeenLabel = uiText('users.just_now', 'Just now');
  } elseif ($delta < 3600) {
    $lastDeviceSeenLabel = floor($delta / 60) . ' ' . uiText('users.minutes_ago_suffix', 'min ago');
  } elseif ($delta < 86400) {
    $hours = floor($delta / 3600);
    $lastDeviceSeenLabel = $hours . ' ' . uiText('users.hour', 'hour') . ($hours === 1 ? '' : 's') . ' ' . uiText('users.ago', 'ago');
  } else {
    $days = floor($delta / 86400);
    $lastDeviceSeenLabel = $days . ' ' . uiText('users.day', 'day') . ($days === 1 ? '' : 's') . ' ' . uiText('users.ago', 'ago');
  }
}

$lastLogin = null;
$interval = null;
$lastLoginDeltaSeconds = null;
if ($hasValidLastLogin) {
  try {
    $lastLogin = new DateTimeImmutable((string) $user['last_login'], $appTimezone);
    $interval = $now->diff($lastLogin);
    $lastLoginDeltaSeconds = max(0, $now->getTimestamp() - $lastLogin->getTimestamp());
  } catch (Throwable $e) {
    $hasValidLastLogin = false;
  }
}

// Determine if the user name should be faded (older than 7 days)
$isFaded = $interval ? ($interval->y > 0 || $interval->m > 0 || $interval->d > 7) : false;
// Highlight users that were active in the past hour with the primary color.
$recentHighlight = ($lastLoginDeltaSeconds !== null && $lastLoginDeltaSeconds < 3600) ? 'color: var(--primary-color);' : '';
$isOwnAccount = ((int) ($user['id'] ?? 0) === (int) ($_SESSION['user']['id'] ?? 0));
?>

<div class="outerUserContainer" id="user<?= $user['id'] ?>">
  <div class="userContainer userSwitch container">
    <div class="linkHeader">
      <?php if (checkAdmin()) { ?>
        <div class="userForm<?= $isOwnAccount ? ' userForm-disabled' : '' ?>" <?= $isOwnAccount ? ' title="' . htmlspecialchars(uiText('users.cannot_select_own_account', 'Cannot select your own account'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
          <input type="checkbox" class="userCheckbox checkbox" id="checkbox-<?= $user['id'] ?>"
            onclick="multiSelect(this.id, event)"
            <?= $isOwnAccount ? 'disabled title="Cannot select your own account"' : '' ?>>
          <span class="checkBoxBackground"><i class="checkIcon material-icons">checkmark</i></span>
        </div>
      <?php } ?>
      <div class="titleContainer" tabindex="0" role="button" aria-label="<?= htmlspecialchars($user['name'] . ' ' . $user['family_name'], ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">
        <div class="tooltip userRole">
          <i class="material-icons userRoleIcon" style="<?= $isFaded ? 'opacity: 0.6;' : '' ?>">
            <?php
            $role = (string) ($user['role'] ?? 'user');
            if ($role === 'superadmin') {
              echo 'verified_user';
            } elseif ($role === 'admin') {
              echo 'security';
            } else {
              echo 'person';
            }
            ?>
          </i>
          <span class="tooltiptext"><?= $user['role'] ?></span>
        </div>
        <div class="tooltip userNameContainer">
          <img
            src="<?= htmlspecialchars(!empty(trim((string) ($user['picture'] ?? ''))) ? (string) $user['picture'] : '/images/user.jpg', ENT_QUOTES, 'UTF-8') ?>"
            alt="User photo"
            onerror="this.onerror=null;this.src='/images/user.jpg';"
            class="userNameAvatar">
          <h3 class="linkTitle" style="<?= $isFaded ? 'opacity: 0.6;' : '' ?> <?= $recentHighlight ?>">
            <?= $user['name'] . ' ' . $user['family_name'] ?></h3>
          <span class="tooltiptext"><?= $user['email'] ?></span>
          <div class="shownBody titleTags">
            <?php if ($user['tags']) {
              $tagCount = count($user['tags']);
              $index = 0; ?>
              <i class="day-icons tagTitle" style="<?= $isFaded ? 'opacity: 0.6;' : '' ?>">&#xf04fc;</i>
              <?php foreach ($user['tags'] as $tag) {
                if (strlen($tag['title']) === 0) {
                  $tagCount--;
                  continue;
                }
                if ($tagCount > 3 && $index === 2) {
                  $overflowTags = array_slice($user['tags'], 2);
                  $overflowTagTitles = array_map(fn($t) => $t['title'], $overflowTags); ?>
                  <div class="tagContainer tooltip">
                    <p class="tag">+ <?= $tagCount - 2 ?></p>
                    <span class="tooltiptext">
                      <p><?= htmlspecialchars(implode(', ', $overflowTagTitles), ENT_QUOTES, 'UTF-8') ?></p>
                    </span>
                  </div>
                <?php }
                if ($index < 2 || count($user['tags']) <= 3) { ?>
                  <div id="<?= $tag['title'] ?>-link" class="tagContainer filterByTag">
                    <p class="tag"><?= $tag['title'] ?></p>
                  </div>
            <?php }
                $index++;
              }
            } ?>

            <?php if ($user['groups']) {
              $groupCount = count($user['groups']);
              $index = 0; ?>
              <i class="groupTitle material-icons" style="<?= $isFaded ? 'opacity: 0.6;' : '' ?>">folder</i>
              <?php foreach ($user['groups'] as $group) {
                if (strlen($group['title']) === 0) {
                  $groupCount--;
                  continue;
                }
                if ($groupCount > 3 && $index === 2) {
                  $overflowGroups = array_slice($user['groups'], 2);
                  $overflowGroupTitles = array_map(fn($g) => $g['title'], $overflowGroups); ?>
                  <div class="tagContainer tooltip">
                    <p class="group">+ <?= $groupCount - 2 ?></p>
                    <span class="tooltiptext">
                      <p><?= htmlspecialchars(implode(', ', $overflowGroupTitles), ENT_QUOTES, 'UTF-8') ?></p>
                    </span>
                  </div>
                <?php }
                if ($index < 2 || count($user['groups']) <= 3) { ?>
                  <div id="<?= $group['title'] ?>-link" class="tagContainer filterByGroup">
                    <p class="group"><?= $group['title'] ?></p>
                  </div>
            <?php }
                $index++;
              }
            } ?>
          </div>
        </div>

        <div class="userData shownBody">
          <?php $editedCount = getEditedLinksCount($user['id']); ?>
          <div class="tooltip">
            <p><?php if ($editedCount == 0) {
                  echo '<span class="userStatZero">0</span>';
                } else {
                  echo $editedCount;
                } ?></p>
            <span class="tooltiptext"><?= htmlspecialchars(uiText('users.added_links', 'Added links'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="tooltip userLastSeenItem<?= $isFaded ? ' is-faded' : '' ?>">
            <i class="day-icons userLastSeenIcon">&#xf0b56;</i>
            <p>
              <?php
              if (!$hasValidLastLogin || !$lastLogin || !$interval) {
                echo '<span class="userStatZero">' . htmlspecialchars(uiText('users.unknown', 'Unknown'), ENT_QUOTES, 'UTF-8') . '</span>';
              } else {
                $isToday = $lastLogin->format('Y-m-d') === $now->format('Y-m-d');
                $yesterday = $now->sub(new DateInterval('P1D'));
                $isYesterday = $lastLogin->format('Y-m-d') === $yesterday->format('Y-m-d');

                if ($lastLoginDeltaSeconds !== null && $lastLoginDeltaSeconds < 60) {
                  echo '<span style="color: var(--primary-color);">' . htmlspecialchars(uiText('users.just_now', 'Just now'), ENT_QUOTES, 'UTF-8') . '</span>';
                } elseif ($lastLoginDeltaSeconds !== null && $lastLoginDeltaSeconds < 3600) {
                  $minutesAgo = max(1, (int) floor($lastLoginDeltaSeconds / 60));
                  echo '<span style="color: var(--primary-color);">' . $minutesAgo . ' ' . htmlspecialchars(uiText('users.minutes_ago_suffix_full', 'minutes ago'), ENT_QUOTES, 'UTF-8') . '</span>';
                } elseif ($interval->y > 0 || $interval->m > 0 || $interval->d > 7) {
                  echo '<span style="color: var(--text-color); opacity: 0.5;">' . $lastLogin->format('d M') . '</span>';
                } elseif ($interval->d > 1) {
                  echo '<span style="color: var(--text-color); opacity: 0.5;">' . $lastLogin->format('D H:i') . '</span>';
                } elseif ($isYesterday) {
                  echo '<span style="color: var(--text-color)">' . htmlspecialchars(uiText('users.yesterday', 'Yesterday'), ENT_QUOTES, 'UTF-8') . ', ' . $lastLogin->format('H:i') . '</span>';
                } elseif ($interval->d === 0 && $isToday) {
                  if ($interval->h > 0) {
                    echo '<span style="color: var(--primary-color);">' . htmlspecialchars(uiText('users.today', 'Today'), ENT_QUOTES, 'UTF-8') . ', ' . $lastLogin->format('H:i') . '</span>';
                  } elseif ($interval->i > 0) {
                    echo '<span style="color: var(--primary-color);">' . $interval->i . ' ' . htmlspecialchars(uiText('users.minutes_ago_suffix_full', 'minutes ago'), ENT_QUOTES, 'UTF-8') . '</span>';
                  } else {
                    echo '<span style="color: var(--primary-color);">' . htmlspecialchars(uiText('users.just_now', 'Just now'), ENT_QUOTES, 'UTF-8') . '</span>';
                  }
                }
              }
              ?>
            </p>
            <span class="tooltiptext"><?= $lastLogin ? $lastLogin->format('l d F, H:i') : htmlspecialchars(uiText('users.unknown', 'Unknown'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </div>

      <div class="linkBodyWrapper">
        <div class="linkBody hiddenBody quickSettings">
          <?php if (checkAdmin()) { ?>
            <div class="userActionBarBlock">
              <div class="actions sameLine userActionBar" role="group" aria-label="<?= htmlspecialchars(uiText('users.primary_actions', 'Primary actions'), ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($_SESSION['user']['role'] === 'superadmin' || ($_SESSION['user']['role'] === 'admin' && $user['role'] === 'user')) { ?>
                  <div class="action">
                    <span class="tooltip">
                      <a class="linkAction" onclick="createModal('/editModal?id=<?= $user['id'] ?>&comp=users')"><i
                          class="material-icons userActionIcon">edit</i><?= htmlspecialchars(uiText('users.edit', 'Edit'), ENT_QUOTES, 'UTF-8') ?></a>
                      <span class="tooltiptext"><?= htmlspecialchars(uiText('users.tooltip_edit_user', 'Edit user details'), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                  </div>
                  <div class="action actionDanger" onclick="createPopupModal('/deleteLink?id=<?= $user['id'] ?>&comp=users', this, event)"
                    id="delete-<?= $user['id'] ?>">
                    <span class="tooltip">
                      <a class="linkAction deleteLink"><i class="material-icons userActionIcon">delete</i><?= htmlspecialchars(uiText('users.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?></a>
                      <span class="tooltiptext"><?= htmlspecialchars(uiText('users.tooltip_delete_user', 'Delete this user'), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                  </div>
                  <?php if ($isSuperAdminSession) { ?>
                    <div class="action actionDanger" onclick="logoutUserAllDevices(<?= (int) $user['id'] ?>)">
                      <span class="tooltip">
                        <a class="linkAction"><i class="material-icons userActionIcon">devices</i><?= htmlspecialchars(uiText('users.logout_all_devices', 'Sign out everywhere'), ENT_QUOTES, 'UTF-8') ?></a>
                        <span class="tooltiptext"><?= htmlspecialchars(uiText('users.tooltip_sign_out_everywhere', 'Sign this user out everywhere'), ENT_QUOTES, 'UTF-8') ?></span>
                      </span>
                    </div>
                  <?php } ?>
                <?php } ?>
                <div class="action">
                  <span class="tooltip">
                    <a class="linkAction" href="/userHistory?id=<?= $user['id'] ?>">
                      <i class="material-icons userActionIcon">bar_chart</i>
                      <?= htmlspecialchars(uiText('users.history', 'History'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <span class="tooltiptext"><?= htmlspecialchars(uiText('users.tooltip_view_history', 'Open user history'), ENT_QUOTES, 'UTF-8') ?></span>
                  </span>
                </div>
              </div>
            </div>
          <?php } ?>

          <?php if (checkAdmin() && $isSuperAdminSession) { ?>
            <div class="userDevicePanel" data-user-id="<?= (int) $user['id'] ?>">
              <div class="userDevicePanelHeader">
                <div class="userDeviceTitleRow">
                  <i class="material-icons">devices</i>
                  <h4 data-i18n="users.device_sessions" data-i18n-fallback="<?= htmlspecialchars(uiText('users.device_sessions', 'Device Sessions'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('users.device_sessions', 'Device Sessions'), ENT_QUOTES, 'UTF-8') ?></h4>
                </div>
                <span class="tooltip userDeviceRefreshTooltip">
                  <button type="button" class="userDeviceRefreshButton" data-load-user-sessions="<?= (int) $user['id'] ?>" data-i18n="users.refresh" data-i18n-fallback="<?= htmlspecialchars(uiText('users.refresh', 'Refresh'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('users.refresh', 'Refresh'), ENT_QUOTES, 'UTF-8') ?></button>
                  <span class="tooltiptext"><?= htmlspecialchars(uiText('users.tooltip_refresh_sessions', 'Refresh active sessions'), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
              </div>

              <div class="userDeviceStatsRow">
                <div class="userDeviceStatBox">
                  <span data-i18n="users.active" data-i18n-fallback="<?= htmlspecialchars(uiText('users.active', 'Active'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('users.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></span>
                  <strong data-user-session-count><?= $activeDeviceCount ?></strong>
                </div>
                <div class="userDeviceStatBox">
                  <span data-i18n="users.last_seen" data-i18n-fallback="<?= htmlspecialchars(uiText('users.last_seen', 'Last seen'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('users.last_seen', 'Last seen'), ENT_QUOTES, 'UTF-8') ?></span>
                  <strong data-user-last-seen><?= htmlspecialchars($lastDeviceSeenLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
              </div>

              <div class="userDeviceSessionList" data-user-session-list>
                <div class="userDeviceEmpty" data-i18n="users.click_refresh_sessions" data-i18n-fallback="<?= htmlspecialchars(uiText('users.click_refresh_sessions', 'Click Refresh to view active sessions.'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('users.click_refresh_sessions', 'Click Refresh to view active sessions.'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
          <?php } ?>

          <div class="tagsGroupsCon">
            <fieldset class="tagsCon">
              <legend><?= htmlspecialchars(uiText('users.tags', 'Tags'), ENT_QUOTES, 'UTF-8') ?>:</legend>
              <?php if ($user['tags']) { ?>
                <?php foreach ($user['tags'] as $tag) {
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
              <legend><?= htmlspecialchars(uiText('users.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?>:</legend>
              <?php if ($user['groups']) { ?>
                <?php foreach ($user['groups'] as $group) {
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
              <div class="modifiedCon dateEntry">
                <i class="material-icons" style="font-size: 16px;">edit</i>
                <p class="date mobileHide">
                  <?= is_numeric($user['modifier']) ? getEmailById($user['modifier']) : $user['modifier'] ?></p>
                <p class="mobileHide">-</p>
                <p class="date mobileHide"><?= $user['modified_at'] ?></p>
                <p class="date mobileShow"><?= date('l, d M Y', strtotime($user['modified_at'])) ?></p>
              </div>
              <div class="dateEntry createdCon">
                <p class="date mobileHide"><?= htmlspecialchars(uiText('users.created', 'Created'), ENT_QUOTES, 'UTF-8') ?> <?php if ($editedCount == 0) { ?>
                    <span style="color: gray;">&nbsp;0&nbsp;</span>
                  <?php
                                                                                                                            } else {
                                                                                                                              echo $editedCount;
                                                                                                                            } ?> <?= htmlspecialchars(uiText('users.links', 'links'), ENT_QUOTES, 'UTF-8') ?>
                </p>
              </div>
              <div class="dateEntry tooltip">
                <p class="date mobileHide"><?= $user['last_login'] ?></p>
                <p class="date mobileShow"><?= date('d M Y', strtotime($user['last_login'])) ?></p>
                <span class="tooltiptext"><?= htmlspecialchars(uiText('users.last_login', 'Last login'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>