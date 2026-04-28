<!DOCTYPE html>
<?php
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

checkUser();
updateLastLogin($_SESSION['user']['id']);

$page = 'profile';
$currentUser = $_SESSION['user'];

$profilePicture = trim((string) ($currentUser['picture'] ?? ''));
if ($profilePicture === '') {
  $profilePicture = '/images/user.jpg';
}

$hasExistingPassword = !empty($currentUser['password']);
$isOneTimeSession = (
  (isset($_SESSION['user']['id']) && $_SESSION['user']['id'] === 'tempUser') ||
  !empty($_SESSION['user']['oneTimeToken']) ||
  !empty($_SESSION['user']['accessLinkToken'])
);

include 'components/navigation.php';
?>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars(uiText('profile.title', 'Profile Settings'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="css/custom.css">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/profile.css">
  <link rel="stylesheet" href="css/modal.css" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="css/modal.css">
  </noscript>
  <link rel="stylesheet" href="css/mobile.css">
  <link rel="stylesheet" href="css/material-icons.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="page-profile">
  <input type="hidden" id="page" value="profile">

  <main class="profilePageWrap">
    <div class="profileSplitLayout">
      <section class="profileColumn profileColumnLeft">
        <section class="profileCard profileIdentityCard">
          <div class="profileAvatarWrap">
            <img id="profileAvatarPreview" src="<?= htmlspecialchars($profilePicture, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(uiText('profile.avatar_alt', 'Profile photo'), ENT_QUOTES, 'UTF-8') ?>" class="profileAvatar" onerror="this.src='/images/user.jpg'">
          </div>
          <div class="profileIdentityText">
            <h1><?= htmlspecialchars(($currentUser['name'] ?? '') . ' ' . ($currentUser['family_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars((string) ($currentUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <span class="profileRoleBadge"><?= htmlspecialchars((string) ($currentUser['role'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </section>

        <section class="profileCard">
          <h2><?= htmlspecialchars(uiText('profile.details_title', 'Profile Details'), ENT_QUOTES, 'UTF-8') ?></h2>
          <?php if ($isOneTimeSession) { ?>
            <p class="profileHint"><?= htmlspecialchars(uiText('profile.one_time_read_only', 'Profile editing is disabled during access-link sessions.'), ENT_QUOTES, 'UTF-8') ?></p>
          <?php } else { ?>
            <p class="profileHint"><?= htmlspecialchars(uiText('profile.details_hint', 'These details are saved to your account and shown in admin user lists.'), ENT_QUOTES, 'UTF-8') ?></p>
            <form action="/profile/identity" method="POST" class="profileForm" id="profileIdentityForm">
              <div class="profileInlineFields">
                <div class="profileInputGroup">
                  <label for="profileName"><?= htmlspecialchars(uiText('profile.first_name', 'First name'), ENT_QUOTES, 'UTF-8') ?></label>
                  <input id="profileName" name="name" type="text" class="profileTextInput" required maxlength="80" value="<?= htmlspecialchars((string) ($currentUser['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="profileInputGroup">
                  <label for="profileFamilyName"><?= htmlspecialchars(uiText('profile.last_name', 'Last name'), ENT_QUOTES, 'UTF-8') ?></label>
                  <input id="profileFamilyName" name="family_name" type="text" class="profileTextInput" required maxlength="80" value="<?= htmlspecialchars((string) ($currentUser['family_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
              </div>
              <div class="createLinkButtonContainer profileButtonRow">
                <button class="submitButton profileSubmit" type="submit"><?= htmlspecialchars(uiText('profile.save_details', 'Save Details'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
            </form>
          <?php } ?>
        </section>

        <section class="profileCard">
          <h2><?= htmlspecialchars(uiText('profile.photo_title', 'Profile Photo'), ENT_QUOTES, 'UTF-8') ?></h2>
          <?php if ($isOneTimeSession) { ?>
            <p class="profileHint"><?= htmlspecialchars(uiText('profile.one_time_read_only', 'Profile editing is disabled during access-link sessions.'), ENT_QUOTES, 'UTF-8') ?></p>
          <?php } else { ?>
            <p class="profileHint"><?= htmlspecialchars(uiText('profile.photo_hint', 'Upload a clear photo to keep your profile visible across the app.'), ENT_QUOTES, 'UTF-8') ?></p>
            <form action="/profile/photo" method="POST" enctype="multipart/form-data" class="profileForm">
              <div class="profileUploadRow">
                <label class="profileUploadField" for="profilePhotoInput">
                  <i class="material-icons">add_a_photo</i>
                  <span><?= htmlspecialchars(uiText('profile.photo_choose', 'Choose image (JPG, PNG, GIF, WEBP - max 5MB)'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp" required>
              </div>
              <div class="createLinkButtonContainer profileButtonRow">
                <button class="submitButton profileSubmit" type="submit"><?= htmlspecialchars(uiText('profile.update_photo', 'Update Profile Photo'), ENT_QUOTES, 'UTF-8') ?></button>
              </div>
            </form>
          <?php } ?>
        </section>
      </section>

      <section class="profileColumn profileColumnRight">
        <section class="profileCard">
          <h2><?= htmlspecialchars(uiText($hasExistingPassword ? 'profile.password_title' : 'profile.set_password_title', $hasExistingPassword ? 'Change Password' : 'Set Password'), ENT_QUOTES, 'UTF-8') ?></h2>
          <?php if ($isOneTimeSession) { ?>
            <p class="profileHint"><?= htmlspecialchars(uiText('profile.one_time_read_only', 'Profile editing is disabled during access-link sessions.'), ENT_QUOTES, 'UTF-8') ?></p>
          <?php } else { ?>
            <p class="profileHint"><?= $hasExistingPassword ? htmlspecialchars(uiText('profile.password_hint_existing', 'Use a strong password with at least 8 characters.'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(uiText('profile.password_hint_new', 'Set a password for direct sign-in (minimum 8 characters).'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!$hasExistingPassword): ?>
              <div class="profilePasswordBanner">
                <i class="material-icons">lock_open</i>
                <span><?= htmlspecialchars(uiText('profile.no_password_banner', 'No password set yet. Set one to sign in directly without a shared link.'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            <?php endif; ?>
            <form action="/profile/password" method="POST" class="profileForm" id="profilePasswordForm" data-has-password="<?= $hasExistingPassword ? 'true' : 'false' ?>">
              <div class="profileFormNotice" id="passwordFormNotice" hidden aria-live="polite"></div>
              <?php if ($hasExistingPassword): ?>
                <div class="profileInputGroup">
                  <label for="currentPassword"><?= htmlspecialchars(uiText('profile.current_password', 'Current password'), ENT_QUOTES, 'UTF-8') ?></label>
                  <div class="profilePasswordField">
                    <input id="currentPassword" name="current_password" type="password" class="profilePasswordInput" autocomplete="current-password" required>
                    <button type="button" class="profileShowPass" data-target="currentPassword" aria-label="<?= htmlspecialchars(uiText('profile.toggle_current_password_visibility', 'Toggle current password visibility'), ENT_QUOTES, 'UTF-8') ?>">
                      <i class="material-icons">visibility_off</i>
                    </button>
                  </div>
                  <p class="profileFieldError" id="currentPasswordError" hidden><?= htmlspecialchars(uiText('profile.current_password_incorrect', 'Current password is incorrect.'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
              <?php endif; ?>
              <div class="profileInputGroup">
                <label for="newPassword"><?= htmlspecialchars(uiText('profile.new_password', 'New password'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="profilePasswordField">
                  <input id="newPassword" name="new_password" type="password" class="profilePasswordInput" autocomplete="new-password" required minlength="8">
                  <button type="button" class="profileShowPass" data-target="newPassword" aria-label="<?= htmlspecialchars(uiText('profile.toggle_new_password_visibility', 'Toggle new password visibility'), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="material-icons">visibility_off</i>
                  </button>
                </div>
              </div>
              <div class="profileInputGroup">
                <label for="confirmPassword"><?= htmlspecialchars(uiText('profile.confirm_password', 'Confirm new password'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="profilePasswordField">
                  <input id="confirmPassword" name="confirm_password" type="password" class="profilePasswordInput" autocomplete="new-password" required minlength="8">
                  <button type="button" class="profileShowPass" data-target="confirmPassword" aria-label="<?= htmlspecialchars(uiText('profile.toggle_confirm_password_visibility', 'Toggle confirm password visibility'), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="material-icons">visibility_off</i>
                  </button>
                </div>
              </div>
              <div class="createLinkButtonContainer profileButtonRow">
                <span class="tooltip profileSubmitTooltip" id="passwordSubmitTooltip">
                  <button class="submitButton profileSubmit" type="submit" id="passwordSubmitBtn" disabled aria-describedby="passwordSubmitTooltipText"><?= htmlspecialchars(uiText($hasExistingPassword ? 'profile.update_password' : 'profile.set_password_btn', $hasExistingPassword ? 'Update Password' : 'Set Password'), ENT_QUOTES, 'UTF-8') ?></button>
                  <span class="tooltiptext bottomtool" id="passwordSubmitTooltipText" role="tooltip"></span>
                </span>
              </div>
            </form>
          <?php } ?>
        </section>

        <section class="profileCard profileDeviceCard">
          <div class="profileDeviceHeader">
            <div>
              <h2><?= htmlspecialchars(uiText('profile.device_control_title', 'Device Control'), ENT_QUOTES, 'UTF-8') ?></h2>
              <p class="profileHint"><?= htmlspecialchars(uiText('profile.device_control_hint', 'Manage active sessions and quickly remove unknown devices.'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php if (!$isOneTimeSession) { ?>
              <button type="button" class="profileDeviceRefresh" id="refreshDeviceSessions">
                <i class="material-icons">refresh</i>
                <?= htmlspecialchars(uiText('profile.refresh', 'Refresh'), ENT_QUOTES, 'UTF-8') ?>
              </button>
            <?php } ?>
          </div>

          <div class="profileDeviceStats" id="profileDeviceStats">
            <div class="profileDeviceStat">
              <span class="profileDeviceStatLabel"><?= htmlspecialchars(uiText('profile.active_sessions', 'Active sessions'), ENT_QUOTES, 'UTF-8') ?></span>
              <strong id="deviceSessionCount">0</strong>
            </div>
            <div class="profileDeviceStat">
              <span class="profileDeviceStatLabel"><?= htmlspecialchars(uiText('profile.last_seen', 'Last seen'), ENT_QUOTES, 'UTF-8') ?></span>
              <strong id="deviceSessionLastSeen">-</strong>
            </div>
          </div>

          <?php if (!$isOneTimeSession) { ?>
            <div class="profileDeviceActions">
              <button type="button" class="profileDeviceAction" id="revokeOtherSessionsBtn"><?= htmlspecialchars(uiText('profile.logout_other_devices', 'Log out other devices'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
          <?php } ?>

          <div class="profileDeviceList" id="profileDeviceList"></div>
        </section>
      </section>
    </div>
  </main>
</body>

<script src="/javascript/script.js?v=<?= $version ?>" defer></script>
<script src="/javascript/profile.js?v=<?= $version ?>" defer></script>
<script src="/javascript/keyboard.js?v=<?= $version ?>" defer></script>

</html>