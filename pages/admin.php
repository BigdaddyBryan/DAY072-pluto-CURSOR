<!DOCTYPE html>
<?php

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (!checkAdmin()) {
  ob_start();
  header('Location: /');
  ob_end_flush();
  exit;
}

$page = 'admin';
$isSuperAdmin = (($_SESSION['user']['role'] ?? '') === 'superadmin');

$directory = __DIR__ . '/../public/json/allowedTokens.json';
$tokens = json_decode(file_get_contents($directory), true);

$tokenEntries = [];
if (is_array($tokens) && isset($tokens['tokens']) && is_array($tokens['tokens'])) {
  foreach ($tokens['tokens'] as $tokenKey => $tokenData) {
    if (!is_array($tokenData)) {
      continue;
    }

    if (!isset($tokenData['token']) || trim((string) $tokenData['token']) === '') {
      $tokenData['token'] = (string) $tokenKey;
    }

    $tokenEntries[] = $tokenData;
  }
}

usort($tokenEntries, function ($a, $b) {
  $aCreated = isset($a['created']) ? (int) $a['created'] : 0;
  $bCreated = isset($b['created']) ? (int) $b['created'] : 0;
  return $bCreated <=> $aCreated;
});

$tokensPerPage = 4;
$tokenPage = isset($_GET['otp_page']) ? (int) $_GET['otp_page'] : 1;
if ($tokenPage < 1) {
  $tokenPage = 1;
}

$tokenCount = count($tokenEntries);
$tokenTotalPages = max(1, (int) ceil($tokenCount / $tokensPerPage));
if ($tokenPage > $tokenTotalPages) {
  $tokenPage = $tokenTotalPages;
}

$tokenOffset = ($tokenPage - 1) * $tokensPerPage;
$pagedTokenEntries = array_slice($tokenEntries, $tokenOffset, $tokensPerPage);

$pdoGroups = connectToDatabase();
$groupsStmt = $pdoGroups->prepare('SELECT id, title FROM groups ORDER BY title ASC');
$groupsStmt->execute();
$scopeGroups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);
closeConnection($pdoGroups);

include 'components/navigation.php';
?>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $titles['admin']['title'] ?></title>
  <link rel="stylesheet" href="css/custom.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="stylesheet" href="css/admin.css">
  <link rel="stylesheet" href="css/material-icons.css">
  <link rel="stylesheet" href="css/modal.css" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="css/modal.css">
  </noscript>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
  </style>
</head>

<body class="page-admin" data-user-role="<?= htmlspecialchars((string) ($_SESSION['user']['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" id="page" value="<?= $page ?>">

  <div class="adminDashboard">
    <!-- Custom Styling Dropdown Trigger -->
    <div class="adminDropdownTrigger" onclick="openAdminSection('customStyling', this)">
      <i class="material-icons dropdownIcon">palette</i>
      <span class="dropdownTitle"><?= htmlspecialchars(uiText('admin.custom_styling', 'Custom Styling'), ENT_QUOTES, 'UTF-8') ?></span>
      <i class="material-icons dropdownArrow">expand_more</i>
    </div>

    <!-- Access Links Dropdown Trigger -->
    <div class="adminDropdownTrigger" onclick="openAdminSection('oneTimeLogin', this)">
      <i class="material-icons dropdownIcon">vpn_key</i>
      <span class="dropdownTitle"><?= htmlspecialchars(uiText('admin.one_time_login', 'Access Links'), ENT_QUOTES, 'UTF-8') ?></span>
      <i class="material-icons dropdownArrow">expand_more</i>
    </div>

    <!-- Log-in Backgrounds Dropdown Trigger -->
    <div class="adminDropdownTrigger" onclick="openAdminSection('backgrounds', this)">
      <i class="material-icons dropdownIcon">wallpaper</i>
      <span class="dropdownTitle"><?= htmlspecialchars(uiText('admin.login_backgrounds', 'Log-in Backgrounds'), ENT_QUOTES, 'UTF-8') ?></span>
      <i class="material-icons dropdownArrow">expand_more</i>
    </div>

    <!-- Maintenance Dropdown Trigger -->
    <div class="adminDropdownTrigger" onclick="openAdminSection('maintenance', this)">
      <i class="material-icons dropdownIcon">build</i>
      <span class="dropdownTitle"><?= htmlspecialchars(uiText('admin.maintenance', 'Maintenance'), ENT_QUOTES, 'UTF-8') ?></span>
      <i class="material-icons dropdownArrow">expand_more</i>
    </div>

    <!-- Backups Dropdown Trigger -->
    <div class="adminDropdownTrigger" onclick="openAdminSection('backups', this)">
      <i class="material-icons dropdownIcon">backup</i>
      <span class="dropdownTitle"><?= htmlspecialchars(uiText('admin.backups', 'Backups'), ENT_QUOTES, 'UTF-8') ?></span>
      <i class="material-icons dropdownArrow">expand_more</i>
    </div>

  </div>

  <!-- Custom Styling Section (Hidden by default) -->
  <div id="section-customStyling" class="adminSectionOverlay">
    <div class="adminSectionHeader">
      <button class="backButton" onclick="closeAdminSection('customStyling')"><i class="material-icons">close</i> <?= htmlspecialchars(uiText('admin.close', 'Close'), ENT_QUOTES, 'UTF-8') ?></button>
      <h2><?= htmlspecialchars(uiText('admin.custom_styling', 'Custom Styling'), ENT_QUOTES, 'UTF-8') ?></h2>
    </div>
    <div class="adminSectionContent">
      <div class="customStylingLayout">
        <div class="customStylingIntro">
          <p><?= htmlspecialchars(uiText('admin.custom_styling_intro', 'Manage theme editing, branding assets, and deployment files from one clear overview.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <section class="customStylingGroup">
          <div class="customStylingGroupHeader">
            <h3 class="customStylingGroupTitle"><i class="material-icons">palette</i> <?= htmlspecialchars(uiText('admin.custom_styling_group_editors', 'Visual Editors'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="customStylingGroupHint"><?= htmlspecialchars(uiText('admin.custom_styling_group_editors_hint', 'Open focused tools to adjust theme tokens and branding visuals.'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <div class="buttonGrid customStylingGrid">
            <button onclick="createModal('/custom-css-editor?comp=dashboard', 'large')" class="submitButton"><i class="material-icons">palette</i> <?= htmlspecialchars(uiText('admin.custom_styling_dashboard', 'Theme Token Editor'), ENT_QUOTES, 'UTF-8') ?></button>
            <button onclick="createModal('/custom-css-editor?comp=branding', 'large')" class="submitButton"><i class="material-icons">branding_watermark</i> <?= htmlspecialchars(uiText('admin.branding_dashboard', 'Brand Assets'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </section>

        <section class="customStylingGroup">
          <div class="customStylingGroupHeader">
            <h3 class="customStylingGroupTitle"><i class="material-icons">folder</i> <?= htmlspecialchars(uiText('admin.custom_styling_group_files', 'Files & Packages'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="customStylingGroupHint"><?= htmlspecialchars(uiText('admin.custom_styling_group_files_hint', 'Import, export, and maintain custom styling files in one place.'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <div class="buttonGrid customStylingGrid">
            <input type="file" id="cssUploadInput" name="file" oninput="uploadCustomCSS()" accept=".zip">
            <button class="submitButton" onclick="document.getElementById('cssUploadInput').click()"><i class="material-icons">upload_file</i> <?= htmlspecialchars(uiText('admin.upload_css', 'Upload Theme Package'), ENT_QUOTES, 'UTF-8') ?></button>

            <button class="submitButton" onclick="downloadCustomFolder()"><i class="material-icons">download</i> <?= htmlspecialchars(uiText('admin.download_css', 'Download Theme Package'), ENT_QUOTES, 'UTF-8') ?></button>

            <input type="file" id="404Input" name="file" oninput="uploadCustom404()" accept=".html">
            <button class="submitButton" onclick="document.getElementById('404Input').click()"><i class="material-icons">error</i> <?= htmlspecialchars(uiText('admin.upload_custom_404', 'Upload Custom 404 Page'), ENT_QUOTES, 'UTF-8') ?></button>

            <button class="submitButton" onclick="deleteCustom404()"><i class="material-icons">delete</i> <?= htmlspecialchars(uiText('admin.delete_custom_404', 'Remove Custom 404 Page'), ENT_QUOTES, 'UTF-8') ?></button>

            <?php if ($isSuperAdmin): ?>
              <a href="/exportBundle" class="submitButton submitButtonLink"><i class="material-icons">inventory_2</i> <?= htmlspecialchars(uiText('admin.export_bundle', 'Export Full Bundle'), ENT_QUOTES, 'UTF-8') ?></a>

              <input type="file" id="importBundleInput" name="file" accept=".zip" style="display:none" onchange="importBundle(this)">
              <button class="submitButton" onclick="document.getElementById('importBundleInput').click()"><i class="material-icons">upload</i> <?= htmlspecialchars(uiText('admin.import_bundle', 'Import Full Bundle'), ENT_QUOTES, 'UTF-8') ?></button>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($isSuperAdmin): ?>
          <section class="customStylingGroup customStylingGroup-critical">
            <div class="customStylingGroupHeader">
              <h3 class="customStylingGroupTitle"><i class="material-icons">admin_panel_settings</i> <?= htmlspecialchars(uiText('admin.custom_styling_group_superadmin', 'Superadmin Tools'), ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="customStylingGroupHint"><?= htmlspecialchars(uiText('admin.custom_styling_group_superadmin_hint', 'Use these only for guided reconfiguration or a full project reset.'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="buttonGrid customStylingGrid">
              <a href="/setup" class="submitButton submitButtonLink"><i class="material-icons">auto_fix_high</i> <?= htmlspecialchars(uiText('admin.setup_wizard', 'Setup Wizard'), ENT_QUOTES, 'UTF-8') ?></a>
              <button class="submitButton submitButton-danger" onclick="resetProject()"><i class="material-icons">restart_alt</i> <?= htmlspecialchars(uiText('admin.reset_project', 'Reset Project'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Access Links Section (Hidden by default) -->
  <div id="section-oneTimeLogin" class="adminSectionOverlay">
    <div class="adminSectionHeader">
      <button class="backButton" onclick="closeAdminSection('oneTimeLogin')"><i class="material-icons">close</i> <?= htmlspecialchars(uiText('admin.close', 'Close'), ENT_QUOTES, 'UTF-8') ?></button>
      <h2><?= htmlspecialchars(uiText('admin.one_time_login', 'Access Links'), ENT_QUOTES, 'UTF-8') ?></h2>
    </div>
    <div class="adminSectionContent">
      <div class="adminContentWrapper">
        <div class="adminContentButtons">
          <div class="oneTimeCreateSettings">
            <div class="oneTimeSettingsSection">
              <p class="oneTimeSettingsTitle"><?= htmlspecialchars(uiText('admin.timing', 'Timing'), ENT_QUOTES, 'UTF-8') ?></p>
              <label for="oneTimeExpirationDays"><?= htmlspecialchars(uiText('admin.valid_for', 'Valid for'), ENT_QUOTES, 'UTF-8') ?></label>
              <select id="oneTimeExpirationDays" class="oneTimeSelect" aria-label="One time link expiration in days">
                <option value="1"><?= htmlspecialchars(uiText('admin.1_day', '1 day'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="3"><?= htmlspecialchars(uiText('admin.3_days', '3 days'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="7" selected><?= htmlspecialchars(uiText('admin.7_days', '7 days'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="14"><?= htmlspecialchars(uiText('admin.14_days', '14 days'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="30"><?= htmlspecialchars(uiText('admin.30_days', '30 days'), ENT_QUOTES, 'UTF-8') ?></option>
              </select>

              <label class="oneTimeToggle" for="oneTimeUseAdvancedTiming">
                <input type="checkbox" id="oneTimeUseAdvancedTiming">
                <span><?= htmlspecialchars(uiText('admin.custom_login_window', 'Custom login window + session time'), ENT_QUOTES, 'UTF-8') ?></span>
              </label>

              <div id="oneTimeAdvancedTimingFields" class="oneTimeAdvancedTimingFields" style="display: none;">
                <label for="oneTimeValidFrom"><?= htmlspecialchars(uiText('admin.login_valid_from', 'Login valid from'), ENT_QUOTES, 'UTF-8') ?></label>
                <input
                  type="datetime-local"
                  id="oneTimeValidFrom"
                  class="oneTimeDateInput"
                  value="<?= date('Y-m-d\\TH:i') ?>"
                  aria-label="One time login valid from">

                <label for="oneTimeValidUntil"><?= htmlspecialchars(uiText('admin.login_valid_until', 'Login valid until'), ENT_QUOTES, 'UTF-8') ?></label>
                <input
                  type="datetime-local"
                  id="oneTimeValidUntil"
                  class="oneTimeDateInput"
                  value="<?= date('Y-m-d\\TH:i', strtotime('+7 days')) ?>"
                  aria-label="One time login valid until">

                <label for="oneTimeSessionMinutes"><?= htmlspecialchars(uiText('admin.view_session_duration', 'View session duration'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="oneTimeSessionMinutes" class="oneTimeSelect" aria-label="One time login view session duration in minutes">
                  <option value="15"><?= htmlspecialchars(uiText('admin.15_minutes', '15 minutes'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="30"><?= htmlspecialchars(uiText('admin.30_minutes', '30 minutes'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="60" selected><?= htmlspecialchars(uiText('admin.1_hour', '1 hour'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="120"><?= htmlspecialchars(uiText('admin.2_hours', '2 hours'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="240"><?= htmlspecialchars(uiText('admin.4_hours', '4 hours'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="480"><?= htmlspecialchars(uiText('admin.8_hours', '8 hours'), ENT_QUOTES, 'UTF-8') ?></option>
                  <option value="1440"><?= htmlspecialchars(uiText('admin.24_hours', '24 hours'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
              </div>
            </div>

            <div class="oneTimeSettingsSection">
              <p class="oneTimeSettingsTitle"><?= htmlspecialchars(uiText('admin.access', 'Access'), ENT_QUOTES, 'UTF-8') ?></p>
              <label for="oneTimeRole"><?= htmlspecialchars(uiText('admin.access_role', 'Access role'), ENT_QUOTES, 'UTF-8') ?></label>
              <select id="oneTimeRole" class="oneTimeSelect" aria-label="<?= htmlspecialchars(uiText('admin.one_time_login_access_role', 'One time login access role'), ENT_QUOTES, 'UTF-8') ?>">
                <option value="viewer" selected><?= htmlspecialchars(uiText('admin.view_only', 'View only'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="user"><?= htmlspecialchars(uiText('modals.users.role_user', 'User'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="admin"><?= htmlspecialchars(uiText('modals.users.role_admin', 'Admin'), ENT_QUOTES, 'UTF-8') ?></option>
                <?php if (($_SESSION['user']['role'] ?? '') === 'superadmin') { ?>
                  <option value="superadmin"><?= htmlspecialchars(uiText('modals.users.role_superadmin', 'Superadmin'), ENT_QUOTES, 'UTF-8') ?></option>
                <?php } ?>
              </select>

              <label for="oneTimeGroupSearch"><?= htmlspecialchars(uiText('admin.group_access', 'Group access'), ENT_QUOTES, 'UTF-8') ?></label>
              <div class="oneTimeGroupPicker" id="oneTimeGroupPicker">
                <div class="oneTimeGroupPickerHeader">
                  <span id="oneTimeGroupCount" class="oneTimeGroupCount"><?= htmlspecialchars(uiText('admin.all_groups', 'All groups'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <input
                  type="text"
                  id="oneTimeGroupSearch"
                  class="oneTimeGroupSearch"
                  placeholder="<?= htmlspecialchars(uiText('admin.search_groups', 'Search groups...'), ENT_QUOTES, 'UTF-8') ?>"
                  aria-label="Search groups for one time link scope">
                <div id="oneTimeGroupList" class="oneTimeGroupList" role="listbox" aria-label="One time link group scope list">
                  <label class="oneTimeGroupOption oneTimeGroupOptionAll" data-title="all groups">
                    <input type="checkbox" id="oneTimeAllGroupsCheckbox" class="oneTimeGroupAllCheckbox" checked>
                    <span><?= htmlspecialchars(uiText('admin.all_groups', 'All groups'), ENT_QUOTES, 'UTF-8') ?></span>
                  </label>
                  <?php if (empty($scopeGroups)) { ?>
                    <p class="oneTimeNoGroups"><?= htmlspecialchars(uiText('admin.no_groups_available', 'No groups available'), ENT_QUOTES, 'UTF-8') ?></p>
                  <?php } else { ?>
                    <?php foreach ($scopeGroups as $scopeGroup) { ?>
                      <label class="oneTimeGroupOption" data-title="<?= strtolower($scopeGroup['title']) ?>">
                        <input type="checkbox" class="oneTimeGroupCheckbox" value="<?= (int) $scopeGroup['id'] ?>" data-title="<?= htmlspecialchars($scopeGroup['title']) ?>">
                        <span><?= htmlspecialchars($scopeGroup['title']) ?></span>
                      </label>
                    <?php } ?>
                  <?php } ?>
                </div>
              </div>
            </div>

            <label class="oneTimeToggle" for="oneTimeAutoCopy">
              <input type="checkbox" id="oneTimeAutoCopy" checked>
              <span><?= htmlspecialchars(uiText('admin.auto_copy', 'Auto copy'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
          </div>

          <button class="createTokenButton submitButton" onclick="createOneTime()">
            <i class="material-icons">add</i> <?= htmlspecialchars(uiText('admin.create_new_login_link', 'Create New Access Link'), ENT_QUOTES, 'UTF-8') ?>
          </button>
          <button class="createTokenButton secondary submitButton" onclick="createOneTimeAndOpen()">
            <i class="material-icons">open_in_new</i> <?= htmlspecialchars(uiText('admin.create_open_link', 'Create & Open Access Link'), ENT_QUOTES, 'UTF-8') ?>
          </button>
          <button class="createTokenButton secondary submitButton" id="deactivateAll" style="display: none;" onclick="deactivateAllOneTimes()">
            <i class="material-icons">block</i> <?= htmlspecialchars(uiText('admin.deactivate_all_unused', 'Deactivate All Unused'), ENT_QUOTES, 'UTF-8') ?>
          </button>
        </div>
        <div class="innerAdminContent">
          <?php
          $allDeactivated = true;
          $nowTs = time();
          foreach ($pagedTokenEntries as $token) {
            $oneTimeBaseUrl = isset($shortlinkBaseUrl) ? $shortlinkBaseUrl : ('https://' . $domain);
            $fullToken = $oneTimeBaseUrl . '/access-link-login?token=' . $token['token'];
            $createdAt = (int) ($token['created'] ?? 0);
            $createdLabel = $createdAt > 0 ? date('M d H:i', $createdAt) : uiText('admin.unknown', 'Unknown');
            $expirationTs = isset($token['expiration']) ? (int) $token['expiration'] : 0;
            $expirationLabel = $expirationTs > 0 ? date('M d H:i:s', $expirationTs) : uiText('admin.unknown', 'Unknown');
            $validFromTs = isset($token['login_valid_from']) ? (int) $token['login_valid_from'] : $createdAt;
            $validUntilTs = isset($token['login_valid_until']) ? (int) $token['login_valid_until'] : $expirationTs;
            $validFromLabel = $validFromTs > 0 ? date('M d H:i:s', $validFromTs) : uiText('admin.immediately', 'Immediately');
            $validUntilLabel = $validUntilTs > 0 ? date('M d H:i:s', $validUntilTs) : $expirationLabel;
            $tokenRole = trim((string) ($token['role'] ?? ''));
            if ($tokenRole === '') {
              $tokenRole = !empty($token['scope_groups']) ? 'limited' : 'viewer';
            }
            $sessionMinutes = isset($token['session_duration_minutes']) ? (int) $token['session_duration_minutes'] : 60;
            if ($sessionMinutes < 5 || $sessionMinutes > 10080) {
              $sessionMinutes = 60;
            }
            $scopeLabels = [];
            if (isset($token['scope_groups']) && is_array($token['scope_groups'])) {
              foreach ($token['scope_groups'] as $scopeGroup) {
                if (!is_array($scopeGroup)) {
                  continue;
                }

                $scopeTitle = trim((string) ($scopeGroup['title'] ?? ''));
                if ($scopeTitle !== '') {
                  $scopeLabels[] = $scopeTitle;
                }
              }
            }

            if (empty($scopeLabels)) {
              $legacyScopeTitle = trim((string) ($token['scope_group_title'] ?? ''));
              if ($legacyScopeTitle !== '') {
                $scopeLabels[] = $legacyScopeTitle;
              }
            }

            $scopeLabels = array_values(array_unique($scopeLabels));
            $scopeGroupText = empty($scopeLabels) ? uiText('admin.all_groups', 'All groups') : implode(', ', $scopeLabels);
            $clicked = !empty($token['clicked']) || !empty($token['used']) || !empty($token['used_on']);
            $lastClickedOn = $token['last_clicked_on'] ?? ($token['used_on'] ?? null);
            $clickCount = isset($token['click_count']) ? (int) $token['click_count'] : ($clicked ? 1 : 0);
            $status = 'active';
            $statusText = '';
            $statusIcon = '';

            if ($token['deactivated']) {
              $status = 'deactivated';
              $statusText = uiText('admin.status_deactivated', 'Deactivated');
              $statusIcon = '✗';
            } else if ($token['used']) {
              $status = 'used';
              $statusText = uiText('admin.status_used', 'Used');
              $statusIcon = '✓';
            } else if ($validFromTs > 0 && $validFromTs > $nowTs) {
              $status = 'scheduled';
              $statusText = uiText('admin.status_scheduled', 'Scheduled');
              $statusIcon = '⏳';
              $allDeactivated = false;
            } else if ($expirationTs > 0 && $expirationTs < time()) {
              $status = 'expired';
              $statusText = uiText('admin.status_expired', 'Expired');
              $statusIcon = '⏱';
            } else {
              $statusText = uiText('admin.status_active', 'Active');
              $statusIcon = '●';
              $allDeactivated = false;
            }
          ?>
            <div class="tokenContainer" data-token="<?= $token['token'] ?>" data-status="<?= $status ?>">
              <div class="tokenHeader">
                <div class="tokenStatus <?= $status ?>">
                  <span class="statusIcon"><?= $statusIcon ?></span>
                  <span class="statusText"><?= $statusText ?></span>
                </div>
                <div class="tokenCreated">
                  <?= htmlspecialchars(uiText('admin.created', 'Created'), ENT_QUOTES, 'UTF-8') ?>: <?= $createdLabel ?>
                </div>
              </div>

              <div class="adminInput" onclick="copyLink('<?= $fullToken ?>', event)">
                <label title="<?= htmlspecialchars(uiText('admin.click_copy_link', 'Click to copy link'), ENT_QUOTES, 'UTF-8') ?>"><i class="material-icons adminInput">content_copy</i></label>
                <input value="<?= $fullToken ?>" disabled title="<?= htmlspecialchars(uiText('admin.click_copy_this_link', 'Click to copy this link'), ENT_QUOTES, 'UTF-8') ?>">
              </div>

              <div class="tokenData">
                <div class="tokenDataRow">
                  <span class="label"><?= htmlspecialchars(uiText('admin.scope', 'Scope'), ENT_QUOTES, 'UTF-8') ?>:</span>
                  <span class="value"><?= htmlspecialchars($scopeGroupText) ?></span>
                </div>
                <div class="tokenDataRow">
                  <span class="label"><?= htmlspecialchars(uiText('admin.role', 'Role'), ENT_QUOTES, 'UTF-8') ?>:</span>
                  <span class="value"><?= htmlspecialchars(ucfirst($tokenRole)) ?></span>
                </div>
                <div class="tokenDataRow">
                  <span class="label"><?= htmlspecialchars(uiText('admin.login_from', 'Login from'), ENT_QUOTES, 'UTF-8') ?>:</span>
                  <span class="value"><?= $validFromLabel ?></span>
                </div>
                <div class="tokenDataRow">
                  <span class="label"><?= htmlspecialchars(uiText('admin.login_until', 'Login until'), ENT_QUOTES, 'UTF-8') ?>:</span>
                  <span class="value"><?= $validUntilLabel ?></span>
                </div>
                <div class="tokenDataRow">
                  <span class="label"><?= htmlspecialchars(uiText('admin.view_time', 'View time'), ENT_QUOTES, 'UTF-8') ?>:</span>
                  <span class="value"><?= $sessionMinutes ?> <?= htmlspecialchars(uiText('admin.minutes', 'minutes'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="tokenDataRow">
                  <span class="label"><?= htmlspecialchars(uiText('admin.window', 'Window'), ENT_QUOTES, 'UTF-8') ?>:</span>
                  <span class="value"><?= $token['expiration_days'] ?? '?' ?> <?= htmlspecialchars(uiText('admin.days_suffix', 'day(s)'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <?php if ($token['used']) { ?>
                  <div class="tokenDataRow">
                    <span class="label"><?= htmlspecialchars(uiText('admin.used_by', 'Used by'), ENT_QUOTES, 'UTF-8') ?>:</span>
                    <span class="value"><?= $token['ip'] ?? uiText('admin.unknown_lower', 'unknown') ?></span>
                  </div>
                  <div class="tokenDataRow">
                    <span class="label"><?= htmlspecialchars(uiText('admin.used_at', 'Used at'), ENT_QUOTES, 'UTF-8') ?>:</span>
                    <span class="value"><?= !empty($token['used_on']) ? date('M d H:i:s', $token['used_on']) : uiText('admin.unknown', 'Unknown') ?></span>
                  </div>
                <?php } else if ($token['deactivated']) { ?>
                  <div class="tokenDataRow">
                    <span class="label"><?= htmlspecialchars(uiText('admin.deactivated_at', 'Deactivated at'), ENT_QUOTES, 'UTF-8') ?>:</span>
                    <span class="value"><?= date('M d H:i:s', $token['deactivated_on'] ?? $token['created']) ?></span>
                  </div>
                <?php } ?>

                <?php if ($clicked) { ?>
                  <div class="tokenDataRow">
                    <span class="label"><?= htmlspecialchars(uiText('admin.clicked_at', 'Clicked at'), ENT_QUOTES, 'UTF-8') ?>:</span>
                    <span class="value"><?= !empty($lastClickedOn) ? date('M d H:i:s', $lastClickedOn) : uiText('admin.unknown', 'Unknown') ?></span>
                  </div>
                  <div class="tokenDataRow">
                    <span class="label"><?= htmlspecialchars(uiText('admin.click_count', 'Click count'), ENT_QUOTES, 'UTF-8') ?>:</span>
                    <span class="value"><?= $clickCount ?></span>
                  </div>
                <?php } ?>
              </div>

              <div class="tokenButtons">
                <?php if (!$token['deactivated'] && !$token['used']) { ?>
                  <button class="deactivateButton submitButton tokenButton"
                    onclick="deactivateOneTime('<?= $token['token'] ?>')">
                    <i class="material-icons">block</i> <?= htmlspecialchars(uiText('admin.deactivate', 'Deactivate'), ENT_QUOTES, 'UTF-8') ?>
                  </button>
                <?php } ?>
                <button class="deleteButton submitButton tokenButton"
                  onclick="deleteOneTime('<?= $token['token'] ?>')">
                  <i class="material-icons">delete</i> <?= htmlspecialchars(uiText('admin.delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?>
                </button>
              </div>
            </div>
          <?php
          }

          if ($tokenCount === 0) {
          ?>
            <div class="emptyState">
              <p><?= htmlspecialchars(uiText('admin.no_login_links', 'No access links yet. Create one to get started!'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          <?php } ?>

          <?php if ($tokenTotalPages > 1) { ?>
            <div class="tokenPagination">
              <a
                class="submitButton tokenPaginationButton <?= $tokenPage <= 1 ? 'disabled' : '' ?>"
                href="<?= $tokenPage <= 1 ? '#' : '/admin?otp_page=' . ($tokenPage - 1) ?>">
                <?= htmlspecialchars(uiText('admin.previous', 'Previous'), ENT_QUOTES, 'UTF-8') ?>
              </a>

              <div class="tokenPaginationNumbers">
                <?php for ($pageNumber = 1; $pageNumber <= $tokenTotalPages; $pageNumber++) { ?>
                  <a
                    class="tokenPaginationNumber <?= $pageNumber === $tokenPage ? 'active' : '' ?>"
                    href="/admin?otp_page=<?= $pageNumber ?>">
                    <?= $pageNumber ?>
                  </a>
                <?php } ?>
              </div>

              <a
                class="submitButton tokenPaginationButton <?= $tokenPage >= $tokenTotalPages ? 'disabled' : '' ?>"
                href="<?= $tokenPage >= $tokenTotalPages ? '#' : '/admin?otp_page=' . ($tokenPage + 1) ?>">
                <?= htmlspecialchars(uiText('admin.next', 'Next'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <?php if (!$allDeactivated) { ?>
    <input type="hidden" id="deactivateAllVisible" value="true">
  <?php } ?>

  <!-- Maintenance Section (Hidden by default) -->
  <div id="section-maintenance" class="adminSectionOverlay">
    <div class="adminSectionHeader">
      <button class="backButton" onclick="closeAdminSection('maintenance')"><i class="material-icons">close</i> <?= htmlspecialchars(uiText('admin.close', 'Close'), ENT_QUOTES, 'UTF-8') ?></button>
      <h2><?= htmlspecialchars(uiText('admin.maintenance', 'Maintenance'), ENT_QUOTES, 'UTF-8') ?></h2>
    </div>
    <div class="adminSectionContent">
      <div class="maintenanceGrid">
        <div class="maintenanceCard">
          <div class="maintenanceCardIcon"><i class="material-icons">cleaning_services</i></div>
          <h3 class="maintenanceCardTitle"><?= htmlspecialchars(uiText('admin.media_cleanup', 'Media Cleanup'), ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="maintenanceCardDesc"><?= htmlspecialchars(uiText('admin.media_cleanup_description', 'Remove unused or excess media files. Keeps 3 newest background images, and deletes orphaned group and profile images not linked to any record.'), ENT_QUOTES, 'UTF-8') ?></p>
          <button class="submitButton" id="cleanupMediaBtn" onclick="cleanupMedia()"><i class="material-icons">cleaning_services</i> <?= htmlspecialchars(uiText('admin.cleanup_media', 'Clean Up Media'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div class="maintenanceCard">
          <div class="maintenanceCardIcon"><i class="material-icons">auto_fix_high</i></div>
          <h3 class="maintenanceCardTitle"><?= htmlspecialchars(uiText('admin.cleanup_fragments', 'Cleanup Fragments'), ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="maintenanceCardDesc"><?= htmlspecialchars(uiText('admin.cleanup_fragments_description', 'Delete tags that are no longer used by any link, user, or visitor.'), ENT_QUOTES, 'UTF-8') ?></p>
          <button class="submitButton" id="cleanupFragmentsBtn" onclick="cleanupFragments()"><i class="material-icons">auto_fix_high</i> <?= htmlspecialchars(uiText('admin.cleanup_fragments_action', 'Clean Up Unused Tags'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
    </div>
  </div>

  <!-- Backups Section (Hidden by default) -->
  <div id="section-backups" class="adminSectionOverlay">
    <div class="adminSectionHeader">
      <button class="backButton" onclick="closeAdminSection('backups')"><i class="material-icons">close</i> <?= htmlspecialchars(uiText('admin.close', 'Close'), ENT_QUOTES, 'UTF-8') ?></button>
      <h2><?= htmlspecialchars(uiText('admin.backups', 'Backups'), ENT_QUOTES, 'UTF-8') ?></h2>
    </div>
    <div class="adminSectionContent">
      <p class="backupDescription"><?= htmlspecialchars(uiText('admin.backup_description', 'Create and manage backups of your database and custom files. Download or delete individual snapshots below.'), ENT_QUOTES, 'UTF-8') ?></p>

      <div class="backupActionsRow">
        <button class="submitButton" id="backupCreateButton" onclick="createBackup()"><i class="material-icons">backup</i> <?= htmlspecialchars(uiText('admin.create_backup', 'Create Backup'), ENT_QUOTES, 'UTF-8') ?></button>
        <button class="submitButton" id="backupRefreshButton" onclick="refreshAdminBackupStatus()"><i class="material-icons">refresh</i> <?= htmlspecialchars(uiText('admin.refresh_status', 'Refresh'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>

      <div id="backupStatusNotice" class="backupStatusNotice backupStatusNotice-neutral"><?= htmlspecialchars(uiText('admin.loading_backup_status', 'Loading backup status...'), ENT_QUOTES, 'UTF-8') ?></div>

      <div id="backupSnapshotsList" class="backupSnapshotsList">
        <p class="backupListEmpty"><?= htmlspecialchars(uiText('admin.no_snapshots_found', 'No snapshots found.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>

      <details class="backupLogDetails">
        <summary><?= htmlspecialchars(uiText('admin.backup_activity_log', 'Activity log'), ENT_QUOTES, 'UTF-8') ?></summary>
        <pre class="backupLogOutput" id="backupLogOutput">-</pre>
      </details>
    </div>
  </div>

  <!-- Log-in Backgrounds Section (Hidden by default) -->
  <div id="section-backgrounds" class="adminSectionOverlay">
    <div class="adminSectionHeader">
      <button class="backButton" onclick="closeAdminSection('backgrounds')"><i class="material-icons">close</i> <?= htmlspecialchars(uiText('admin.close', 'Close'), ENT_QUOTES, 'UTF-8') ?></button>
      <h2><?= htmlspecialchars(uiText('admin.login_backgrounds', 'Log-in Backgrounds'), ENT_QUOTES, 'UTF-8') ?></h2>
    </div>
    <div class="adminSectionContent">
      <div class="adminImagesWrapper">
        <div class="innerAdminContentImages" id="customImageGrid">
          <?php include 'components/getImages.php'; ?>
        </div>
        <div class="adminContentButtons imageButtons">
          <input type="file" id="bgInput" name="file" oninput="handleFileChange(this)" accept="image/*" multiple>
          <button class="submitButton" onclick="document.getElementById('bgInput').click()"><i class="material-icons">add_photo_alternate</i> <?= htmlspecialchars(uiText('admin.add_new_images', 'Add New Images'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="submitButton" id="selectAllButton" onclick="selectAll()"><i class="material-icons">select_all</i> <?= htmlspecialchars(uiText('admin.select_all', 'Select All'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="submitButton" id="deselectAllButton" style="display: none;" onclick="deselectAll()"><i class="material-icons">deselect</i> <?= htmlspecialchars(uiText('admin.deselect_all', 'Deselect All'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="submitButton" onclick="randomizeImages()"><i class="material-icons">shuffle</i> <?= htmlspecialchars(uiText('admin.shuffle_order', 'Shuffle Order'), ENT_QUOTES, 'UTF-8') ?></button>
          <button class="submitButton" id="deleteSelectedButton" style="display: none;" onclick="deleteSelected()"><i class="material-icons">delete</i> <?= htmlspecialchars(uiText('admin.delete_selected', 'Delete Selected'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
      </div>
    </div>
  </div>

  <div class="newLinkContainer">
    <button class="rocketContainer newLinkButton" id="rocketContainer" onclick="scrollUp(this)"><i
        class="day-icons rocket">&#xf0463;</i></button>
  </div>

  </div>

</body>
<script src="/javascript/script.js?v=<?= $version ?>" defer></script>
<script src="/javascript/keyboard.js?v=<?= $version ?>" defer></script>
<script src="/javascript/admin.js?v=<?= $version ?>" defer></script>

</html>