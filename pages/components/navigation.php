<?php

$buildVersionedAssetHref = static function ($absolutePath, $publicHref) {
  if (!is_file($absolutePath)) {
    return '';
  }

  return $publicHref . '?v=' . rawurlencode((string) filemtime($absolutePath));
};

$cookieTheme = isset($_COOKIE['theme']) ? strtolower(trim((string) $_COOKIE['theme'])) : '';
$themePreference = in_array($cookieTheme, ['light', 'dark', 'system'], true)
  ? $cookieTheme
  : strtolower((string) ($_SESSION['user']['mode'] ?? 'dark'));
if (!in_array($themePreference, ['light', 'dark', 'system'], true)) {
  $themePreference = 'light';
}
$initialTheme = $themePreference === 'system'
  ? (isset($_COOKIE['resolvedTheme']) && $_COOKIE['resolvedTheme'] === 'dark' ? 'dark' : 'light')
  : $themePreference;
$preloadBackground = $initialTheme === 'dark' ? '#0b1220' : '#f4f7fb';

$lightThemeCssPath = __DIR__ . '/../../public/custom/css/custom-light.css';
$darkThemeCssPath = __DIR__ . '/../../public/custom/css/custom-dark.css';
$lightThemeVersion = file_exists($lightThemeCssPath) ? (string) filemtime($lightThemeCssPath) : (string) time();
$darkThemeVersion = file_exists($darkThemeCssPath) ? (string) filemtime($darkThemeCssPath) : (string) time();
$lightThemeHref = '/custom/css/custom-light.css?v=' . rawurlencode($lightThemeVersion);
$darkThemeHref = '/custom/css/custom-dark.css?v=' . rawurlencode($darkThemeVersion);
$initialThemeHref = $initialTheme === 'dark' ? $darkThemeHref : $lightThemeHref;
$preloadThemeHref = $initialTheme === 'dark' ? $lightThemeHref : $darkThemeHref;

$logoLightPath = __DIR__ . '/../../public/custom/images/logo/logo-light.svg';
$logoDarkPath = __DIR__ . '/../../public/custom/images/logo/logo-dark.svg';
$logoLightHref = $buildVersionedAssetHref($logoLightPath, '/custom/images/logo/logo-light.svg');
$logoDarkHref = $buildVersionedAssetHref($logoDarkPath, '/custom/images/logo/logo-dark.svg');
if ($logoLightHref === '' && $logoDarkHref !== '') {
  $logoLightHref = $logoDarkHref;
}
if ($logoDarkHref === '' && $logoLightHref !== '') {
  $logoDarkHref = $logoLightHref;
}
if ($logoLightHref === '') {
  $logoLightHref = '/custom/images/logo/logo-light.svg';
}
if ($logoDarkHref === '') {
  $logoDarkHref = '/custom/images/logo/logo-dark.svg';
}
$initialLogoHref = $initialTheme === 'dark' ? $logoDarkHref : $logoLightHref;

$fallbackFaviconPath = __DIR__ . '/../../public/custom/images/icons/favicon.svg';
$fallbackFaviconHref = $buildVersionedAssetHref($fallbackFaviconPath, '/custom/images/icons/favicon.svg');
if ($fallbackFaviconHref === '') {
  $fallbackFaviconHref = '/custom/images/icons/favicon.svg';
}

$faviconLightPath = __DIR__ . '/../../public/custom/images/icons/favicon-light.svg';
$faviconDarkPath = __DIR__ . '/../../public/custom/images/icons/favicon-dark.svg';
$faviconLightHref = $buildVersionedAssetHref($faviconLightPath, '/custom/images/icons/favicon-light.svg');
$faviconDarkHref = $buildVersionedAssetHref($faviconDarkPath, '/custom/images/icons/favicon-dark.svg');
if ($faviconLightHref === '') {
  $faviconLightHref = $fallbackFaviconHref;
}
if ($faviconDarkHref === '') {
  $faviconDarkHref = $fallbackFaviconHref;
}
$initialFaviconHref = $initialTheme === 'dark' ? $faviconDarkHref : $faviconLightHref;

$navigationCssPath = __DIR__ . '/../../public/css/navigation.css';
$navigationJsPath = __DIR__ . '/../../public/javascript/navigation.js';
$i18nJsPath = __DIR__ . '/../../public/javascript/i18n.js';
$navigationCssHref = '/css/navigation.css' . (is_file($navigationCssPath) ? '?v=' . rawurlencode((string) filemtime($navigationCssPath)) : '');
$navigationJsHref = '/javascript/navigation.js' . (is_file($navigationJsPath) ? '?v=' . rawurlencode((string) filemtime($navigationJsPath)) : '');
$i18nJsHref = '/javascript/i18n.js' . (is_file($i18nJsPath) ? '?v=' . rawurlencode((string) filemtime($i18nJsPath)) : '');

// If a page wants to hide the main page navigation (single-page views), it can set
// $showPageNav = false before including this file. Default to true when not set.
if (!isset($showPageNav)) {
  $showPageNav = true;
}

$currentYear = (int) date('Y');
$copyrightStartYear = isset($copyrightStartYear) ? (int) $copyrightStartYear : 2024;
if ($copyrightStartYear > 0 && $copyrightStartYear < $currentYear) {
  $copyrightYearLabel = $copyrightStartYear . ' - ' . $currentYear;
} else {
  $copyrightYearLabel = (string) $currentYear;
}

$uiLang = function_exists('uiLocale') ? strtolower((string) uiLocale()) : 'en';
if (!in_array($uiLang, ['en', 'nl'], true)) {
  $uiLang = 'en';
}

$isThemeDark = ($initialTheme === 'dark');
?>


<head>
  <meta name="color-scheme" content="light dark">
  <style>
    html,
    body {
      background-color: <?= htmlspecialchars($preloadBackground, ENT_QUOTES, 'UTF-8') ?>;
    }
  </style>
  <script>
    (function() {
      var serverPreference = "<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>";
      var preference = serverPreference;

      try {
        var storedPreference = localStorage.getItem("themePreference");
        if (storedPreference) {
          preference = storedPreference;
        }
      } catch (error) {}

      var resolvedTheme = preference;
      if (resolvedTheme === "system") {
        try {
          var cachedResolvedTheme = localStorage.getItem("resolvedTheme");
          if (cachedResolvedTheme === "dark" || cachedResolvedTheme === "light") {
            resolvedTheme = cachedResolvedTheme;
          }
        } catch (error) {}

        try {
          resolvedTheme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        } catch (error) {
          resolvedTheme = "light";
        }
      }

      if (resolvedTheme !== "dark" && resolvedTheme !== "light") {
        resolvedTheme = "light";
      }

      var preloadBg = resolvedTheme === "dark" ? "#0b1220" : "#f4f7fb";
      document.documentElement.style.backgroundColor = preloadBg;
      document.documentElement.style.colorScheme = resolvedTheme;

      // Sync cookie so next page load renders the correct theme server-side
      try {
        document.cookie = "theme=" + encodeURIComponent(preference) + ";path=/;max-age=31536000;SameSite=Lax";
        document.cookie = "resolvedTheme=" + encodeURIComponent(resolvedTheme) + ";path=/;max-age=31536000;SameSite=Lax";
      } catch (e) {}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="/fonts/material-icons.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap">
  </noscript>
  <link
    id="themeFavicon"
    rel="icon"
    type="image/svg+xml"
    data-light-icon-href="<?= htmlspecialchars($faviconLightHref, ENT_QUOTES, 'UTF-8') ?>"
    data-dark-icon-href="<?= htmlspecialchars($faviconDarkHref, ENT_QUOTES, 'UTF-8') ?>"
    href="<?= htmlspecialchars($initialFaviconHref, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($navigationCssHref, ENT_QUOTES, 'UTF-8') ?>">
  <link
    id="darkMode"
    rel="stylesheet"
    data-light-href="<?= htmlspecialchars($lightThemeHref, ENT_QUOTES, 'UTF-8') ?>"
    data-dark-href="<?= htmlspecialchars($darkThemeHref, ENT_QUOTES, 'UTF-8') ?>"
    href="<?= htmlspecialchars($initialThemeHref, ENT_QUOTES, 'UTF-8') ?>">
  <link
    id="themePreload"
    rel="preload"
    as="style"
    href="<?= htmlspecialchars($preloadThemeHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="manifest" href="/manifest.webmanifest">
</head>

<body data-theme-preference="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
  <?php
  $oneTimeTokenExpiresAt = 0;
  $isOneTimeSession = (
    (isset($_SESSION['user']['id']) && $_SESSION['user']['id'] === 'tempUser') ||
    !empty($_SESSION['user']['oneTimeToken']) ||
    !empty($_SESSION['user']['accessLinkToken'])
  );
  if ($isOneTimeSession) {
    if (!empty($_SESSION['user']['accessLinkTokenExpires'])) {
      $oneTimeTokenExpiresAt = (int) $_SESSION['user']['accessLinkTokenExpires'];
    } else if (!empty($_SESSION['user']['oneTimeTokenExpires'])) {
      $oneTimeTokenExpiresAt = (int) $_SESSION['user']['oneTimeTokenExpires'];
    }
  }

  $uiNoticeMessage = '';
  $uiNoticeType = '';
  if (!empty($_SESSION['ui_notice'])) {
    $uiNoticeMessage = (string) $_SESSION['ui_notice'];
    $uiNoticeType = isset($_SESSION['ui_notice_type']) ? (string) $_SESSION['ui_notice_type'] : 'info';
    unset($_SESSION['ui_notice'], $_SESSION['ui_notice_type']);
  }
  ?>
  <?php if ($oneTimeTokenExpiresAt > 0) { ?>
    <input type="hidden" id="oneTimeTokenExpiresAt" value="<?= $oneTimeTokenExpiresAt ?>">
  <?php } ?>
  <input type="hidden" id="themePreference" value="<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" id="uiLanguageCurrent" value="<?= htmlspecialchars($uiLang, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($uiNoticeMessage !== '') { ?>
    <input
      type="hidden"
      id="globalUiNotice"
      value="<?= htmlspecialchars($uiNoticeMessage, ENT_QUOTES, 'UTF-8') ?>"
      data-type="<?= htmlspecialchars($uiNoticeType, ENT_QUOTES, 'UTF-8') ?>">
  <?php } ?>

  <div class="header">
    <!-- <div id="nav-icon2" class="nav-toggle">
      <span></span>
      <span></span>
      <span></span>
      <span></span>
      <span></span>
      <span></span>
    </div> -->
    <div id="nav-icon3" class="nav-toggle" aria-label="Toggle navigation" role="button" tabindex="0">
      <div class="circle circle-1"></div>
      <div class="circle circle-2"></div>
      <div class="circle circle-3"></div>
      <div class="circle circle-4"></div>
      <div class="circle circle-5"></div>
    </div>
    <a href="/" class="logoLink">
      <div class="logoContainer">
        <img
          id="logoLink"
          src="<?= htmlspecialchars($initialLogoHref, ENT_QUOTES, 'UTF-8') ?>"
          data-light-logo-src="<?= htmlspecialchars($logoLightHref, ENT_QUOTES, 'UTF-8') ?>"
          data-dark-logo-src="<?= htmlspecialchars($logoDarkHref, ENT_QUOTES, 'UTF-8') ?>"
          alt="Logo"
          class="logo">
      </div>
    </a>
    <div class="userImageContainer profileNavCon toggleSec" onclick="toggleSecondaire(event)">
      <?php
      $navPicture = trim((string) ($_SESSION['user']['picture'] ?? ''));
      // Verify local custom images actually exist on disk to avoid a 404 request
      if (!empty($navPicture) && strpos($navPicture, '/custom/images/') === 0) {
        $navLocalPath = __DIR__ . '/../../public' . $navPicture;
        if (!file_exists($navLocalPath)) {
          $navPicture = '';
        }
      }
      ?>
      <?php if (!empty($navPicture)) { ?>
        <img src="<?php echo htmlspecialchars($navPicture, ENT_QUOTES, 'UTF-8'); ?>" alt="User picture" class="userPicture" onerror="this.onerror=null;this.src='/images/user.jpg';">
      <?php } else { ?>
        <img src="/images/user.jpg" alt="User picture" class="userPicture" id="switchSecNav">
      <?php } ?>
      <div class="profileNav" id="secondaryNav" onclick="event.stopPropagation();">
        <div class="bottomNav profileSimpleNav">
          <a class="navItem" href="/profile">
            <i class="material-icons">manage_accounts</i>
            <span class="navLink" data-i18n="navigation.profile_settings" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.profile_settings', 'Profile settings'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.profile_settings', 'Profile settings'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
          <a class="navItem" href="#" onclick="createModal('/keyboardShortcuts'); return false;">
            <i class="material-icons">help_outline</i>
            <span class="navLink" data-i18n="navigation.support" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.support', 'Support'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.support', 'Support'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
          <div class="navItem navLanguageItem" role="group" aria-label="<?= htmlspecialchars(uiText('navigation.language', 'Language'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="material-icons">language</i>
            <div class="navLanguageControl">
              <span class="navLink navLanguageLabel" id="navLanguageLabel"><?= $uiLang === 'nl' ? 'Nederlands' : 'Engels' ?></span>
              <div
                id="uiLanguageToggle"
                class="navLangToggle"
                data-active-lang="<?= htmlspecialchars($uiLang, ENT_QUOTES, 'UTF-8') ?>"
                role="group"
                aria-label="<?= htmlspecialchars(uiText('navigation.language', 'Language'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="navLangOption" data-lang="en">English</span>
                <button
                  id="uiLanguageSwitch"
                  type="button"
                  class="navLangThumbBtn"
                  role="switch"
                  aria-checked="<?= $uiLang === 'nl' ? 'true' : 'false' ?>"
                  aria-label="<?= htmlspecialchars(uiText('navigation.language', 'Language'), ENT_QUOTES, 'UTF-8') ?>">
                  <span class="navLangThumb" aria-hidden="true"></span>
                </button>
                <span class="navLangOption" data-lang="nl">Nederlands</span>
              </div>
            </div>
          </div>

          <div class="navItem navThemeItem" role="group" aria-label="<?= htmlspecialchars(uiText('navigation.dark_mode', 'Dark mode'), ENT_QUOTES, 'UTF-8') ?>" onclick="event.stopPropagation(); if (!event.target.closest('#uiThemeSwitch')) { switchDark(); }">
            <i class="material-icons">brightness_4</i>
            <span
              class="navLink navThemeLabel"
              id="darkSwitch"><?= $isThemeDark ? htmlspecialchars(uiText('navigation.light_mode', 'Light mode'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(uiText('navigation.dark_mode', 'Dark mode'), ENT_QUOTES, 'UTF-8') ?></span>
            <button
              id="uiThemeSwitch"
              type="button"
              class="navThemeToggleBtn"
              role="switch"
              aria-checked="<?= $isThemeDark ? 'true' : 'false' ?>"
              aria-label="<?= htmlspecialchars(uiText('navigation.dark_mode', 'Dark mode'), ENT_QUOTES, 'UTF-8') ?>"
              onclick="event.stopPropagation(); switchDark();">
              <span class="navThemeThumb" aria-hidden="true"></span>
            </button>
          </div>

          <?php if ($page === 'links' || $page === 'visitors') { ?>
            <a class="navItem" onclick="switchList()">
              <i class="material-icons">visibility</i>
              <span class="navLink"
                id="listSwitch"><?= $_SESSION['user']['view'] === 'view' ? htmlspecialchars(uiText('navigation.list_mode', 'List mode'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(uiText('navigation.view_mode', 'View mode'), ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php } ?>
          <a class="navItem" href="/logout">
            <i class="material-icons">logout</i>
            <span class="navLink" data-i18n="navigation.logout" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.logout', 'Logout'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.logout', 'Logout'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="headerSpacer">
  </div>

  <?php if ($showPageNav) { ?>
    <div class="navContainer nav-inactive">
      <div class="nav">
        <a class="navItem<?= $page === 'links' ? ' active' : '' ?>" href="/">
          <i class="material-icons">share</i>
          <span class="navLink" data-i18n="navigation.links" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.links', 'Links'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.links', 'Links'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <a class="navItem<?= $page === 'profile' ? ' active' : '' ?>" href="/profile">
          <i class="material-icons">account_circle</i>
          <span class="navLink" data-i18n="navigation.profile" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.profile', 'Profile'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.profile', 'Profile'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php if (checkAdmin()) { ?>
          <a class="navItem<?= $page === 'users' ? ' active' : '' ?>" href="/users">
            <i class="material-icons">people</i>
            <span class="navLink" data-i18n="navigation.users" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.users', 'Users'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.users', 'Users'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        <?php } ?>
        <a class="navItem<?= $page === 'visitors' ? ' active' : '' ?>" href="/visitors">
          <i class="material-icons">list</i>
          <span class="navLink" data-i18n="navigation.visitors" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.visitors', 'Visitors'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.visitors', 'Visitors'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php if (checkAdmin()) { ?>
          <a class="navItem<?= $page === 'admin' ? ' active' : '' ?>" href="/admin">
            <i class="material-icons">insert_chart</i>
            <span class="navLink" data-i18n="navigation.admin" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.admin', 'Admin'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.admin', 'Admin'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        <?php } ?>
        <?php if ($_SESSION['user']['role'] !== 'viewer' && $_SESSION['user']['role'] !== 'limited') { ?>
          <a class="navItem<?= $page === 'groups' ? ' active' : '' ?>" href="/groups">
            <i class="material-icons">folder</i>
            <span class="navLink navGroupsLabelDesktop" data-i18n="navigation.groups" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.groups', 'Groups'), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="navLink navGroupsLabelMobile" data-i18n="navigation.groups_mobile" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.groups_mobile', 'Groups'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.groups_mobile', 'Groups'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        <?php } ?>
      </div>
      <div class="nav-bottom">
        <p class="navCopyright">
          <span class="navCopyrightYear">&copy; <?= htmlspecialchars($copyrightYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="navCopyrightText" data-i18n="navigation.copyright" data-i18n-fallback="<?= htmlspecialchars(uiText('navigation.copyright', 'All rights reserved - Daylinq'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(uiText('navigation.copyright', 'All rights reserved - Daylinq'), ENT_QUOTES, 'UTF-8') ?></span>
        </p>
      </div>
    </div>

    <div class="backgroundDarken nav-toggle" style="display: none;">
    </div>

    <script src="<?= htmlspecialchars($navigationJsHref, ENT_QUOTES, 'UTF-8') ?>"></script>
  <?php } ?>

  <script>
    window.__I18N_LANG = <?= json_encode(uiLocale(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.__I18N_TEXTS = <?= uiTextJson('', []) ?>;
    window.__UI_TEXT__ = window.__I18N_TEXTS;
  </script>
  <script src="<?= htmlspecialchars($i18nJsHref, ENT_QUOTES, 'UTF-8') ?>"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const oneTimeExpiresInput = document.getElementById('oneTimeTokenExpiresAt');
      if (!oneTimeExpiresInput) {
        return;
      }

      const expiresAtSeconds = Number.parseInt(oneTimeExpiresInput.value || '0', 10);
      if (!Number.isFinite(expiresAtSeconds) || expiresAtSeconds <= 0) {
        return;
      }

      const expiresAtMs = expiresAtSeconds * 1000;
      const remainingMs = expiresAtMs - Date.now();

      if (remainingMs <= 0) {
        window.location.href = '/logout';
        return;
      }

      window.setTimeout(function() {
        window.location.href = '/logout';
      }, remainingMs);
    });
  </script>

</body>