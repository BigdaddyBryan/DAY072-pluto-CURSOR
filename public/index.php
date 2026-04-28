<?php

// Enable gzip compression for HTML responses
if (!ob_get_level() && extension_loaded('zlib')) {
  ob_start('ob_gzhandler');
}

include '../config/config.php';

$sessionLifetimeSeconds = defined('APP_SESSION_LIFETIME_SECONDS')
  ? (int) APP_SESSION_LIFETIME_SECONDS
  : 2592000; // fallback: 30 days

if ($sessionLifetimeSeconds < 300) {
  $sessionLifetimeSeconds = 300;
}

// Use an isolated session save path so other PHP processes with shorter
// gc_maxlifetime values cannot garbage-collect this app's session files.
$appSessionSavePath = defined('APP_SESSION_SAVE_PATH')
  ? APP_SESSION_SAVE_PATH
  : __DIR__ . '/../custom/sessions';

if (!is_dir($appSessionSavePath)) {
  @mkdir($appSessionSavePath, 0770, true);
}

// Verify directory is actually writable (cloud-synced folders may appear to exist but reject writes)
$sessionPathUsable = false;
if (is_dir($appSessionSavePath)) {
  $testFile = $appSessionSavePath . DIRECTORY_SEPARATOR . '.write_test_' . getmypid();
  if (@file_put_contents($testFile, '1') !== false) {
    @unlink($testFile);
    $sessionPathUsable = true;
  }
}

if (!$sessionPathUsable) {
  // Fallback to system temp
  $appSessionSavePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-sessions';
  if (!is_dir($appSessionSavePath)) {
    @mkdir($appSessionSavePath, 0770, true);
  }
}

if (is_dir($appSessionSavePath)) {
  session_save_path($appSessionSavePath);
}

ini_set('session.gc_maxlifetime', (string) $sessionLifetimeSeconds);
ini_set('session.cookie_lifetime', (string) $sessionLifetimeSeconds);
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');

// Harden session cookie
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$domain = $_SERVER['HTTP_HOST'] ?? '';

$portSuffix = '';
if (preg_match('/:\\d+$/', $domain, $portMatch)) {
  $portSuffix = $portMatch[0];
}

$hostWithoutPort = preg_replace('/:\\d+$/', '', $domain);
$normalizedHost = trim($hostWithoutPort, '[]');
$isIpHost = filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false;
$isLocalHost = in_array(strtolower($normalizedHost), ['localhost', '127.0.0.1', '::1'], true);
$isLocalDomain = $isIpHost || $isLocalHost;
$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$isHttpsRequest =
  (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
  || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
  || $forwardedProto === 'https';
$uiLanguageCookieSecure = !$isLocalDomain && $isHttpsRequest;

$canonicalHost = $hostWithoutPort;
if (!$isLocalDomain && stripos($canonicalHost, 'www.') !== 0) {
  $canonicalHost = 'www.' . $canonicalHost;
}

$shortlinkBaseUrl = ($isLocalDomain ? 'http' : 'https') . '://' . $canonicalHost . $portSuffix;

include 'secure.php';

$domainWW = str_replace('www.', '', $domain);

// Parse the URI to route the request appropriately
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Include the necessary files
include '../api/sql/sql.php';
include '../api/users/users.php';
include '../api/links/links.php';
include '../api/visitors/visitors.php';
include '../api/groups/groups.php';
$version = json_decode(file_get_contents('json/version.json'), true)['version'];
$titles = json_decode(file_get_contents('custom/json/titles.json'), true);
$supportedUiLocales = ['en', 'nl'];
$defaultUiLocale = 'en';

if (!function_exists('normalizeUiLocale')) {
  function normalizeUiLocale($rawLocale)
  {
    global $supportedUiLocales, $defaultUiLocale;

    if (!is_string($rawLocale) || $rawLocale === '') {
      return $defaultUiLocale;
    }

    $locale = strtolower(trim($rawLocale));
    if (strpos($locale, '-') !== false) {
      $locale = explode('-', $locale)[0];
    }

    return in_array($locale, $supportedUiLocales, true) ? $locale : $defaultUiLocale;
  }
}

if (!function_exists('loadUiTextCatalog')) {
  function loadUiTextCatalog($locale)
  {
    $normalizedLocale = normalizeUiLocale($locale);
    $catalogPaths = [
      __DIR__ . '/custom/json/i18n.' . $normalizedLocale . '.json',
      __DIR__ . '/../custom/custom/json/i18n.' . $normalizedLocale . '.json',
    ];

    foreach ($catalogPaths as $catalogPath) {
      if (!is_file($catalogPath)) {
        continue;
      }

      $decoded = json_decode((string) file_get_contents($catalogPath), true);
      if (is_array($decoded)) {
        return $decoded;
      }
    }

    return [];
  }
}

if (!function_exists('readNestedUiText')) {
  function readNestedUiText($catalog, $path)
  {
    if (!is_array($catalog) || !is_string($path) || $path === '') {
      return null;
    }

    $paths = [$path];
    if (strpos($path, 'js.') === 0 && strlen($path) > 3) {
      $paths[] = substr($path, 3);
    }

    foreach ($paths as $candidatePath) {
      $parts = explode('.', $candidatePath);
      $cursor = $catalog;
      $found = true;

      foreach ($parts as $part) {
        if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
          $found = false;
          break;
        }
        $cursor = $cursor[$part];
      }

      if ($found && is_string($cursor)) {
        return $cursor;
      }
    }

    return null;
  }
}

$requestedUiLocale = $_GET['lang'] ?? ($_POST['lang'] ?? null);

$uiLocale = normalizeUiLocale(
  $requestedUiLocale
    ?? ($_SESSION['ui_language'] ?? ($_COOKIE['ui_language'] ?? $defaultUiLocale))
);

$_SESSION['ui_language'] = $uiLocale;
setcookie('ui_language', $uiLocale, [
  'expires' => time() + (365 * 24 * 60 * 60),
  'path' => '/',
  'secure' => $uiLanguageCookieSecure,
  'httponly' => false,
  'samesite' => 'Lax',
]);

$uiTextFallback = loadUiTextCatalog($defaultUiLocale);
$uiText = loadUiTextCatalog($uiLocale);

if (!function_exists('uiLocale')) {
  function uiLocale()
  {
    global $uiLocale;
    return $uiLocale;
  }
}

if (!function_exists('uiCatalog')) {
  function uiCatalog()
  {
    global $uiText, $uiTextFallback;
    return array_replace_recursive($uiTextFallback, $uiText);
  }
}

if (!function_exists('uiText')) {
  function uiText($path, $fallback = '')
  {
    global $uiText, $uiTextFallback;

    $resolved = readNestedUiText($uiText, $path);
    if (is_string($resolved)) {
      return $resolved;
    }

    $fallbackResolved = readNestedUiText($uiTextFallback, $path);
    if (is_string($fallbackResolved)) {
      return $fallbackResolved;
    }

    return $fallback;
  }
}

if (!function_exists('uiTextData')) {
  function uiTextData($path = '', $fallback = [])
  {
    global $uiText;

    if (!is_array($uiText)) {
      return $fallback;
    }

    if (!is_string($path) || $path === '') {
      return $uiText;
    }

    $parts = explode('.', $path);
    $cursor = $uiText;
    foreach ($parts as $part) {
      if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
        return $fallback;
      }
      $cursor = $cursor[$part];
    }

    return $cursor;
  }
}

if (!function_exists('uiTextJson')) {
  function uiTextJson($path = '', $fallback = [])
  {
    $value = uiTextData($path, $fallback);
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }
}

enforceOneTimeReadOnlyAccess($requestUri, $_SERVER['REQUEST_METHOD'] ?? 'GET');

// Fresh install detection — redirect to setup wizard if no users exist
if ($requestUri !== '/setup' && $requestUri !== '/generateTheme' && $requestUri !== '/importBundle' && $requestUri !== '/register' && $requestUri !== '/uploadBrandAsset' && $requestUri !== '/saveSetupPreferences') {
  try {
    $freshCheckPdo = connectToDatabase();
    $freshCheckStmt = $freshCheckPdo->query("SELECT COUNT(*) FROM users");
    $freshCheckCount = (int) $freshCheckStmt->fetchColumn();
    closeConnection($freshCheckPdo);
    if ($freshCheckCount === 0) {
      // Prevent stale authenticated session state when DB has no users.
      unset($_SESSION['user'], $_SESSION['groups'], $_SESSION['device_session_token_hash'], $_SESSION['device_session_id']);
      if (!headers_sent()) {
        setcookie('device_session', '', time() - 3600, '/', '', false, true);
      }
      header('Location: /setup');
      exit;
    }
  } catch (Exception $e) {
    // Database may not exist yet — let setup handle it
    if ($requestUri !== '/setup') {
      header('Location: /setup');
      exit;
    }
  }
}

$cssTemp = checkCustomFonts();

// Reroute all requests to the appropriate page and or function
switch ($requestUri) {
  case '/':
  case '/links':
  case '/dashboard':
    include __DIR__ . '/../pages/links.php';
    break;
  case '/home':
    include __DIR__ . '/../pages/home/home.php';
    break;
  case '/googleLogin':
    include __DIR__ . '/../api/users/auth/googleLogin.php';
    googleLogin();
    break;
  case '/login':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/users/auth/login.php';
    login($_POST);
    break;
  case '/register':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/users/auth/register.php';
    register($_POST, $_FILES);
    break;
  case '/logout':
    include __DIR__ . '/../api/users/auth/logout.php';
    header('Location: /home');
    break;
  case '/users':
    include __DIR__ . '/../pages/users.php';
    break;
  case '/profile':
    include __DIR__ . '/../pages/profile.php';
    break;
  case '/visits':
    include __DIR__ . '/../pages/visits.php';
    break;
  case '/visitors':
    include __DIR__ . '/../pages/visitors.php';
    break;
  case '/statistics':
    include __DIR__ . '/../pages/statistics.php';
    break;
  case '/admin':
    include __DIR__ . '/../pages/admin.php';
    break;
  case '/groups':
    include __DIR__ . '/../pages/groups.php';
    break;
  case '/backup':
    include __DIR__ . '/../config/backup.php';
    break;
  case '/backup-status':
    include __DIR__ . '/../config/backupStatus.php';
    break;
  case '/backup-delete':
    include __DIR__ . '/../config/backupDelete.php';
    break;
  case '/backup-download':
    include __DIR__ . '/../config/backupDownload.php';
    break;
  case '/archive':
    include __DIR__ . '/../pages/archive.php';
    break;
  case '/restore':
    $restoreComp = $_GET['comp'] ?? '';
    $restoreId = $_GET['id'] ?? null;

    switch ($restoreComp) {
      case 'links':
        include __DIR__ . '/../api/links/restoreLink.php';
        if (!function_exists('restoreLink')) {
          include __DIR__ . '/../pages/errors/404.php';
          break;
        }

        $rawIds = trim($_GET['ids'] ?? '');
        if ($rawIds !== '') {
          $idsToRestore = array_values(array_filter(array_map('intval', explode(',', $rawIds)), function ($id) {
            return $id > 0;
          }));

          if (empty($idsToRestore)) {
            include __DIR__ . '/../pages/errors/404.php';
            break;
          }

          $restoredCount = 0;
          $failedCount = 0;

          foreach ($idsToRestore as $rid) {
            $restoreResult = restoreLink($rid, ['suppressRedirect' => true]);
            if (is_array($restoreResult) && !empty($restoreResult['success'])) {
              $restoredCount++;
            } else {
              $failedCount++;
            }
          }

          if (session_status() === PHP_SESSION_NONE) {
            session_start();
          }

          if ($restoredCount > 0 && $failedCount === 0) {
            $_SESSION['ui_notice'] = $restoredCount . ' link(s) restored successfully.';
            $_SESSION['ui_notice_type'] = 'success';
          } elseif ($restoredCount > 0) {
            $_SESSION['ui_notice'] = $restoredCount . ' link(s) restored. ' . $failedCount . ' could not be restored.';
            $_SESSION['ui_notice_type'] = 'warning';
          } else {
            $_SESSION['ui_notice'] = 'No links were restored.';
            $_SESSION['ui_notice_type'] = 'error';
          }

          header('Location: /links');
          exit;
        }

        if ($restoreId === null) {
          include __DIR__ . '/../pages/errors/404.php';
          break;
        }

        restoreLink($restoreId);
        break;
      case 'linksUndo':
        if (!checkAdmin()) {
          http_response_code(401);
          header('Location: /');
          exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
          session_start();
        }

        $undoToken = trim((string) ($_GET['token'] ?? ''));
        $undoStore = isset($_SESSION['multiSelectUndoTokens']) && is_array($_SESSION['multiSelectUndoTokens'])
          ? $_SESSION['multiSelectUndoTokens']
          : [];

        $undoEntry = ($undoToken !== '' && isset($undoStore[$undoToken]) && is_array($undoStore[$undoToken]))
          ? $undoStore[$undoToken]
          : null;

        if ($undoToken !== '' && isset($_SESSION['multiSelectUndoTokens'][$undoToken])) {
          unset($_SESSION['multiSelectUndoTokens'][$undoToken]);
        }

        $undoPayload = isset($undoEntry['payload']) && is_array($undoEntry['payload'])
          ? $undoEntry['payload']
          : null;

        if (!$undoPayload) {
          $_SESSION['ui_notice'] = 'Undo is no longer available.';
          $_SESSION['ui_notice_type'] = 'error';
          header('Location: /links');
          exit;
        }

        $undoType = (string) ($undoPayload['type'] ?? '');
        $undoIds = array_values(array_filter(array_map('intval', (array) ($undoPayload['ids'] ?? [])), function ($id) {
          return $id > 0;
        }));

        if (empty($undoIds)) {
          $_SESSION['ui_notice'] = 'Undo is no longer available.';
          $_SESSION['ui_notice_type'] = 'error';
          header('Location: /links');
          exit;
        }

        if ($undoType === 'delete') {
          include __DIR__ . '/../api/links/restoreLink.php';

          $restoredCount = 0;
          $failedCount = 0;
          foreach ($undoIds as $rid) {
            $restoreResult = restoreLink($rid, ['suppressRedirect' => true]);
            if (is_array($restoreResult) && !empty($restoreResult['success'])) {
              $restoredCount++;
            } else {
              $failedCount++;
            }
          }

          if ($restoredCount > 0 && $failedCount === 0) {
            $_SESSION['ui_notice'] = $restoredCount . ' link(s) restored successfully.';
            $_SESSION['ui_notice_type'] = 'success';
          } elseif ($restoredCount > 0) {
            $_SESSION['ui_notice'] = $restoredCount . ' link(s) restored. ' . $failedCount . ' could not be restored.';
            $_SESSION['ui_notice_type'] = 'warning';
          } else {
            $_SESSION['ui_notice'] = 'No links were restored.';
            $_SESSION['ui_notice_type'] = 'error';
          }

          header('Location: /links');
          exit;
        }

        if ($undoType === 'archive') {
          include __DIR__ . '/../api/links/restoreArchivedLinks.php';
          $restoreResult = restoreArchivedLinks($undoIds);
          $restoredCount = (int) ($restoreResult['restoredCount'] ?? 0);
          $failedCount = (int) ($restoreResult['failedCount'] ?? 0);

          if ($restoredCount > 0 && $failedCount === 0) {
            $_SESSION['ui_notice'] = $restoredCount . ' link(s) restored from archive.';
            $_SESSION['ui_notice_type'] = 'success';
          } elseif ($restoredCount > 0) {
            $_SESSION['ui_notice'] = $restoredCount . ' link(s) restored. ' . $failedCount . ' could not be restored.';
            $_SESSION['ui_notice_type'] = 'warning';
          } else {
            $_SESSION['ui_notice'] = 'No links were restored.';
            $_SESSION['ui_notice_type'] = 'error';
          }

          header('Location: /links');
          exit;
        }

        // Undo for tag/group operations
        $undoValues = isset($undoPayload['values']) && is_array($undoPayload['values'])
          ? array_values(array_filter($undoPayload['values'], 'is_string'))
          : [];
        $undoAdd = isset($undoPayload['add']) && is_array($undoPayload['add'])
          ? array_values(array_filter($undoPayload['add'], 'is_string'))
          : [];
        $undoRemove = isset($undoPayload['remove']) && is_array($undoPayload['remove'])
          ? array_values(array_filter($undoPayload['remove'], 'is_string'))
          : [];

        if ($undoType === 'tagAdd' && !empty($undoValues)) {
          foreach ($undoIds as $id) {
            foreach ($undoValues as $value) {
              addToTag($id, $value);
            }
          }
          $_SESSION['ui_notice'] = 'Undo: tags restored for ' . count($undoIds) . ' item(s).';
          $_SESSION['ui_notice_type'] = 'success';
          header('Location: /links');
          exit;
        }

        if ($undoType === 'tagDel' && !empty($undoValues)) {
          foreach ($undoIds as $id) {
            foreach ($undoValues as $value) {
              removeTag($id, $value);
            }
          }
          $_SESSION['ui_notice'] = 'Undo: tags removed from ' . count($undoIds) . ' item(s).';
          $_SESSION['ui_notice_type'] = 'success';
          header('Location: /links');
          exit;
        }

        if ($undoType === 'tagSync' && (!empty($undoAdd) || !empty($undoRemove))) {
          foreach ($undoIds as $id) {
            foreach ($undoAdd as $value) {
              addToTag($id, $value);
            }
            foreach ($undoRemove as $value) {
              removeTag($id, $value);
            }
          }
          $_SESSION['ui_notice'] = 'Undo: tag changes reverted for ' . count($undoIds) . ' item(s).';
          $_SESSION['ui_notice_type'] = 'success';
          header('Location: /links');
          exit;
        }

        if ($undoType === 'groupAdd' && !empty($undoValues)) {
          foreach ($undoIds as $id) {
            foreach ($undoValues as $value) {
              addToGroup($id, $value);
            }
          }
          $_SESSION['ui_notice'] = 'Undo: groups restored for ' . count($undoIds) . ' item(s).';
          $_SESSION['ui_notice_type'] = 'success';
          header('Location: /links');
          exit;
        }

        if ($undoType === 'groupDel' && !empty($undoValues)) {
          foreach ($undoIds as $id) {
            foreach ($undoValues as $value) {
              removeGroup($id, $value);
            }
          }
          $_SESSION['ui_notice'] = 'Undo: groups removed from ' . count($undoIds) . ' item(s).';
          $_SESSION['ui_notice_type'] = 'success';
          header('Location: /links');
          exit;
        }

        if ($undoType === 'groupSync' && (!empty($undoAdd) || !empty($undoRemove))) {
          foreach ($undoIds as $id) {
            foreach ($undoAdd as $value) {
              addToGroup($id, $value);
            }
            foreach ($undoRemove as $value) {
              removeGroup($id, $value);
            }
          }
          $_SESSION['ui_notice'] = 'Undo: group changes reverted for ' . count($undoIds) . ' item(s).';
          $_SESSION['ui_notice_type'] = 'success';
          header('Location: /links');
          exit;
        }

        $_SESSION['ui_notice'] = 'Undo is no longer available.';
        $_SESSION['ui_notice_type'] = 'error';
        header('Location: /links');
        exit;
        // break omitted: all linksUndo paths above terminate with exit

      case 'groups':
        include __DIR__ . '/../api/groups/restoreGroup.php';
        $rawIds = trim($_GET['ids'] ?? '');
        if ($rawIds === '') {
          include __DIR__ . '/../pages/errors/404.php';
          break;
        }
        $idsToRestore = array_filter(array_map('intval', explode(',', $rawIds)));
        $allRestoreOk = true;
        foreach ($idsToRestore as $rid) {
          $res = restoreGroup($rid);
          if (!$res['success']) {
            $allRestoreOk = false;
          }
        }
        if (session_status() === PHP_SESSION_NONE) {
          session_start();
        }
        $_SESSION['ui_notice'] = $allRestoreOk
          ? count($idsToRestore) . ' group(s) restored successfully.'
          : 'Some groups could not be restored.';
        $_SESSION['ui_notice_type'] = $allRestoreOk ? 'success' : 'error';
        header('Location: /groups');
        exit;
      case 'linksArchive':
        include __DIR__ . '/../api/links/restoreArchivedLinks.php';
        $rawIds = trim($_GET['ids'] ?? '');
        if ($rawIds === '') {
          include __DIR__ . '/../pages/errors/404.php';
          break;
        }

        $idsToRestore = array_values(array_filter(array_map('intval', explode(',', $rawIds)), function ($id) {
          return $id > 0;
        }));

        if (empty($idsToRestore)) {
          include __DIR__ . '/../pages/errors/404.php';
          break;
        }

        $restoreResult = restoreArchivedLinks($idsToRestore);

        if (session_status() === PHP_SESSION_NONE) {
          session_start();
        }

        $restoredCount = (int) ($restoreResult['restoredCount'] ?? 0);
        $failedCount = (int) ($restoreResult['failedCount'] ?? 0);

        if ($restoredCount > 0 && $failedCount === 0) {
          $_SESSION['ui_notice'] = $restoredCount . ' link(s) restored from archive.';
          $_SESSION['ui_notice_type'] = 'success';
        } elseif ($restoredCount > 0) {
          $_SESSION['ui_notice'] = $restoredCount . ' link(s) restored. ' . $failedCount . ' could not be restored.';
          $_SESSION['ui_notice_type'] = 'warning';
        } else {
          $_SESSION['ui_notice'] = 'No links were restored.';
          $_SESSION['ui_notice_type'] = 'error';
        }

        header('Location: /links');
        exit;
      default:
        include __DIR__ . '/../pages/errors/404.php';
        break;
    }
    break;
  case '/restoreGroup':
    if (!checkAdmin()) {
      http_response_code(401);
      echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
      break;
    }
    include __DIR__ . '/../api/groups/restoreGroup.php';
    // POST JSON from filter modal
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      header('Content-Type: application/json');
      $groupPayload = json_decode(file_get_contents('php://input'), true);
      $gid = isset($groupPayload['id']) ? (int) $groupPayload['id'] : 0;
      if ($gid <= 0) {
        echo json_encode(['success' => false, 'message' => 'No group id provided']);
        break;
      }
      echo json_encode(restoreGroup($gid));
      break;
    }
    // GET with ids (undo snackbar redirect)
    $rawIds = trim($_GET['ids'] ?? '');
    if ($rawIds === '') {
      echo json_encode(['status' => 'error', 'message' => 'No ids provided']);
      break;
    }
    $idsToRestore = array_filter(array_map('intval', explode(',', $rawIds)));
    $allRestoreOk = true;
    foreach ($idsToRestore as $rid) {
      $res = restoreGroup($rid);
      if (!$res['success']) {
        $allRestoreOk = false;
      }
    }
    // When called from the undo snackbar the browser navigates here, so redirect back
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $_SESSION['ui_notice'] = $allRestoreOk
      ? count($idsToRestore) . ' group(s) restored successfully.'
      : 'Some groups could not be restored.';
    $_SESSION['ui_notice_type'] = $allRestoreOk ? 'success' : 'error';
    header('Location: /groups');
    exit;
  case '/restoreTag':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    if (!checkAdmin()) {
      http_response_code(401);
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'message' => 'Unauthorized']);
      break;
    }
    header('Content-Type: application/json');
    include __DIR__ . '/../api/tags/restoreTag.php';
    $tagPayload = json_decode(file_get_contents('php://input'), true);
    $tagId = isset($tagPayload['id']) ? (int) $tagPayload['id'] : 0;
    if ($tagId <= 0) {
      echo json_encode(['success' => false, 'message' => 'No tag id provided']);
      break;
    }
    echo json_encode(restoreTag($tagId));
    break;
  case '/renameTag':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    if (!checkAdmin()) {
      http_response_code(401);
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'message' => 'Unauthorized']);
      break;
    }
    header('Content-Type: application/json');
    include __DIR__ . '/../api/tags/renameTag.php';
    $tagPayload = json_decode(file_get_contents('php://input'), true);
    $tagId = isset($tagPayload['id']) ? (int) $tagPayload['id'] : 0;
    $tagTitle = isset($tagPayload['title']) ? trim($tagPayload['title']) : '';
    if ($tagId <= 0 || $tagTitle === '') {
      echo json_encode(['success' => false, 'message' => 'Missing tag id or title']);
      break;
    }
    echo json_encode(renameTag($tagId, $tagTitle));
    break;
  case '/renameGroup':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    if (!checkAdmin()) {
      http_response_code(401);
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'message' => 'Unauthorized']);
      break;
    }
    header('Content-Type: application/json');
    include __DIR__ . '/../api/groups/renameGroup.php';
    $groupPayload = json_decode(file_get_contents('php://input'), true);
    $groupId = isset($groupPayload['id']) ? (int) $groupPayload['id'] : 0;
    $groupTitle = isset($groupPayload['title']) ? trim($groupPayload['title']) : '';
    if ($groupId <= 0 || $groupTitle === '') {
      echo json_encode(['success' => false, 'message' => 'Missing group id or title']);
      break;
    }
    echo json_encode(renameGroup($groupId, $groupTitle));
    break;
  case '/downloadCustom':
    include __DIR__ . '/../api/custom/downloadCustom.php';
    break;
  case '/downloadCustomThemeCss':
    include __DIR__ . '/../api/custom/downloadCustomThemeCss.php';
    break;
  case '/uploadCustom':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/uploadCustom.php';
    break;
  case '/uploadCustomThemeCss':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/uploadCustomThemeCss.php';
    break;
  case '/uploadBrandAsset':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/uploadBrandAsset.php';
    break;
  case '/multiSelect':
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data['comp'] === 'links') {
      include __DIR__ . '/../api/links/multiSelect.php';
      handleMultiSelect();
    } else if ($data['comp'] === 'visitors') {
      include __DIR__ . '/../api/visitors/multiSelect.php';
      handleMultiSelect();
    } else if ($data['comp'] === 'groups') {
      include __DIR__ . '/../api/groups/multiSelect.php';
      handleMultiSelect();
    } else if ($data['comp'] === 'users') {
      include __DIR__ . '/../api/users/multiSelect.php';
      handleMultiSelect();
    }
    break;
  case '/confirmMulti':
    include __DIR__ . '/../pages/components/modals/confirmMulti.php';
    break;
  case '/editModal':
    if ($_GET['comp'] == 'users') {
      include __DIR__ . '/../pages/components/modals/users/editUserModal.php';
    } else if ($_GET['comp'] == 'links') {
      include __DIR__ . '/../pages/components/modals/links/editLinkModal.php';
    } else if ($_GET['comp'] == 'visitors') {
      include __DIR__ . '/../pages/components/modals/visitors/editVisitorModal.php';
    } else if ($_GET['comp'] == 'groups') {
      include __DIR__ . '/../pages/components/modals/groups/editGroupModal.php';
    } else {
      include __DIR__ . '/../pages/errors/404.php';
    }
    break;
  case '/switchLinkStatus':
    $data = json_decode(file_get_contents('php://input'), true);
    echo switchStatus($data);
    break;
  case '/createModal':
    switch ($_GET['comp']) {
      case 'links':
        include __DIR__ . '/../pages/components/modals/links/createLinkModal.php';
        break;
      case 'users':
        include __DIR__ . '/../pages/components/modals/users/createUserModal.php';
        break;
      case 'tagGroup':
        include __DIR__ . '/../pages/components/modals/tagGroup.php';
        break;
      case 'groups':
        include __DIR__ . '/../pages/components/modals/groups/createGroupModal.php';
        break;
      case 'sort':
        include __DIR__ . '/../pages/components/modals/sort.php';
        break;
      case 'qrModal':
        include __DIR__ . '/../pages/components/modals/qrModal.php';
        break;
      case 'status':
        include __DIR__ . '/../pages/components/modals/links/statusModal.php';
        break;
      case 'aliasHistory':
        include __DIR__ . '/../pages/components/modals/links/aliasHistoryModal.php';
        break;
      default:
        include __DIR__ . '/../pages/errors/404.php';
        break;
    }
    break;
  case '/userHistory':
    include __DIR__ . '/../pages/history.php';
    break;
  case '/createGroup':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/groups/createGroup.php';
    createGroup($_POST, $_FILES);
    break;
  case '/editLink':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/links/editLink.php';
    $editResult = editLink($_POST);

    if (is_array($editResult) && !empty($editResult['success'])) {
      if (!empty($editResult['shortlink_changed']) && !empty($editResult['alias_created'])) {
        $_SESSION['ui_notice'] = 'Shortlink changed, alias made';
        $_SESSION['ui_notice_type'] = 'success';
      } else if (!empty($editResult['shortlink_changed'])) {
        $_SESSION['ui_notice'] = 'Shortlink changed';
        $_SESSION['ui_notice_type'] = 'success';
      } else {
        $_SESSION['ui_notice'] = 'Link updated';
        $_SESSION['ui_notice_type'] = 'success';
      }
    } else if (is_array($editResult) && !empty($editResult['message'])) {
      $_SESSION['ui_notice'] = (string) $editResult['message'];
      $_SESSION['ui_notice_type'] = 'error';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
      $queryString = $_SERVER['QUERY_STRING'];

      parse_str($queryString, $queryArray);

      $queryArray['open'] = $_POST['id'];

      $newQueryString = http_build_query($queryArray);

      header('Location:' . '/links' . '?' . $newQueryString);
      exit;
    }
    break;
  case '/editVisitor':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/visitors/editVisitor.php';
    editVisitor($_POST);
    header('Location: /visitors');
    break;
  case '/updateOrder':
    $data = json_decode(file_get_contents('php://input'), true);
    include __DIR__ . '/../api/custom/updateOrder.php';
    updateOrder($data);
    break;
  case '/deleteLink':
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['delete'])) {
      if ($data['delete']) {
        if ($data['comp'] === 'links') {
          include __DIR__ . '/../api/links/deleteLink.php';
          deleteLink($data['id']);
        } else if ($data['comp'] === 'visitors') {
          include __DIR__ . '/../api/visitors/deleteVisitor.php';
          deleteVisitor($data['id']);
        } else if ($data['comp'] === 'users') {
          include __DIR__ . '/../api/users/deleteUser.php';
          deleteUser($data['id']);
        } else if ($data['comp'] === 'groups') {
          include __DIR__ . '/../api/groups/deleteGroup.php';
          $deleteResult = deleteGroup($data['id']);
          if (is_array($deleteResult) && empty($deleteResult['success'])) {
            http_response_code(409);
            echo json_encode([
              'status' => 'error',
              'message' => (string) ($deleteResult['message'] ?? 'Group could not be deleted.')
            ]);
            break;
          }
        } else if ($data['comp'] === 'tags') {
          include __DIR__ . '/../api/tags/deleteTag.php';
          $deleteResult = deleteTag($data['id']);
          if (is_array($deleteResult) && empty($deleteResult['success'])) {
            http_response_code(409);
            echo json_encode([
              'status' => 'error',
              'message' => (string) ($deleteResult['message'] ?? 'Tag could not be deleted.')
            ]);
            break;
          }
        }
        echo json_encode(['status' => 'success']);
      }
    } else {
      if (!checkAdmin()) {
        include __DIR__ . '/../pages/errors/404.php';
        break;
      } else {
        include __DIR__ . '/../pages/components/modals/deleteModal.php';
        break;
      }
    }
    break;
  case '/duplicateLink':
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/links/duplicateLink.php';
    duplicateLink($_GET['id']);
    break;
  case '/filter':
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    $filter = $data['filter'];
    if ($_GET['comp'] === 'links') {
      include __DIR__ . '/../api/links/filterLink.php';
      echo json_encode(getFilteredLinks($filter));
    } else if ($_GET['comp'] === 'visitors') {
      include __DIR__ . '/../api/visitors/filterVisitors.php';
      echo json_encode(getFilteredVisitors($filter));
    } else if ($_GET['comp'] === 'groups') {
      include __DIR__ . '/../api/groups/filterGroups.php';
      echo json_encode(getFilteredGroups($filter));
    } else if ($_GET['comp'] === 'users') {
      include __DIR__ . '/../api/users/filterUsers.php';
      echo json_encode(getFilteredUsers($filter));
    }
    break;
  case '/editUser':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/users/editUser.php';
    editUser($_POST, $_FILES);
    header('Location: /users');
    break;
  case '/editGroup';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/groups/editGroup.php';
    editGroup($_POST, $_FILES);
  case '/getTagGroupModal':
    include __DIR__ . '/../pages/components/modals/tagGroup.php';
    break;
  case '/getSortModal':
    include __DIR__ . '/../pages/components/modals/sort.php';
    break;
  case '/getGroups':
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    include __DIR__ . '/../api/groups/getGroups.php';
    echo json_encode(getGroups($data));
    break;
  case '/getTags':
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    include __DIR__ . '/../api/tags/getTags.php';
    echo json_encode(getTags($data));
    break;
  case '/suggestTags':
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    include __DIR__ . '/../api/tags/suggestTags.php';
    echo json_encode(suggestTags($data));
    break;
  case '/createLink':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/links/createLink.php';
    $createLinkResult = createLink($_POST);
    if (is_array($createLinkResult)) {
      header('Content-Type: application/json');
      if (!($createLinkResult['success'] ?? false)) {
        http_response_code(400);
      }
      echo json_encode($createLinkResult);
    }
    break;
  case '/fetchRelatedTags':
    include __DIR__ . '/../api/tags/fetchRelatedTags.php';
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data['comp'] === 'links') {
      echo json_encode(fetchRelatedTags($data));
    } else if ($data['comp'] === 'visitors') {
      echo json_encode(fetchRelatedVisitorTags($data));
    }
    break;
  case '/restoreLinkAlias':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    header('Content-Type: application/json');
    $aliasPayload = json_decode((string) file_get_contents('php://input'), true);
    $aliasResult = restoreLinkAlias(is_array($aliasPayload) ? $aliasPayload : []);
    echo json_encode($aliasResult);
    break;
  case '/deleteLinkAlias':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    header('Content-Type: application/json');
    $aliasPayload = json_decode((string) file_get_contents('php://input'), true);
    $aliasResult = deleteLinkAlias(is_array($aliasPayload) ? $aliasPayload : []);
    echo json_encode($aliasResult);
    break;
  case '/fetchTagsGroups':
    $data = json_decode(file_get_contents('php://input'), true);
    $tags = getAllTags($data['comp']);
    $groups = getAllGroups($data['comp']);

    echo json_encode(['tags' => $tags, 'groups' => $groups]);
    break;
  case '/getDeletedTagsGroups':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    header('Content-Type: application/json');
    echo json_encode(getRecentlyDeletedTagsGroups());
    break;
  case '/deleteTag':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    header('Content-Type: application/json');
    $tagPayload = json_decode(file_get_contents('php://input'), true);
    include __DIR__ . '/../api/tags/deleteTag.php';
    if (!empty($tagPayload['id'])) {
      $deleteResult = deleteTag($tagPayload['id']);
      if (is_array($deleteResult) && empty($deleteResult['success'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $deleteResult['message'] ?? 'Tag could not be deleted.']);
      } else {
        echo json_encode(['success' => true]);
      }
    } else if (!empty($tagPayload['title'])) {
      $pdo = connectToDatabase();
      $stmt = $pdo->prepare('SELECT id FROM tags WHERE title = :title LIMIT 1');
      $stmt->execute([':title' => $tagPayload['title']]);
      $tagRow = $stmt->fetch(PDO::FETCH_ASSOC);
      closeConnection($pdo);
      if ($tagRow) {
        $deleteResult = deleteTag($tagRow['id']);
        echo json_encode(['success' => true]);
      } else {
        echo json_encode(['success' => false, 'message' => 'Tag not found']);
      }
    } else {
      echo json_encode(['success' => false, 'message' => 'No tag specified']);
    }
    break;
  case '/deleteGroup':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    header('Content-Type: application/json');
    $groupPayload = json_decode(file_get_contents('php://input'), true);
    include __DIR__ . '/../api/groups/deleteGroup.php';
    if (!empty($groupPayload['id'])) {
      $deleteResult = deleteGroup($groupPayload['id']);
      if (is_array($deleteResult) && empty($deleteResult['success'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $deleteResult['message'] ?? 'Group could not be deleted.']);
      } else {
        echo json_encode(['success' => true]);
      }
    } else if (!empty($groupPayload['title'])) {
      $pdo = connectToDatabase();
      $stmt = $pdo->prepare('SELECT id FROM `groups` WHERE title = :title LIMIT 1');
      $stmt->execute([':title' => $groupPayload['title']]);
      $groupRow = $stmt->fetch(PDO::FETCH_ASSOC);
      closeConnection($pdo);
      if ($groupRow) {
        $deleteResult = deleteGroup($groupRow['id']);
        echo json_encode(['success' => true]);
      } else {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
      }
    } else {
      echo json_encode(['success' => false, 'message' => 'No group specified']);
    }
    break;
  case '/getShortlink':
    include __DIR__ . '/../api/links/getShortlink.php';
    echo json_encode(getShortlink());
    break;
  case '/getTitle':
    include __DIR__ . '/../api/links/getLink.php';
    echo json_encode(getLink());
    break;
  case '/linkContainer':
    include __DIR__ . '/../pages/components/linkContainer.php';
    break;
  case '/linkContainerBatch':
    $batchInput = json_decode(file_get_contents('php://input'), true);
    $htmlResults = [];
    if (isset($batchInput['links']) && is_array($batchInput['links'])) {
      foreach ($batchInput['links'] as $linkItem) {
        $link = $linkItem;
        ob_start();
        include __DIR__ . '/../pages/components/linkContainer.php';
        $htmlResults[] = ob_get_clean();
      }
    }
    header('Content-Type: application/json');
    echo json_encode(['html' => $htmlResults]);
    break;
  case '/visitorContainer':
    include __DIR__ . '/../pages/components/visitorContainer.php';
    break;
  case '/visitorContainerBatch':
    $batchInput = json_decode(file_get_contents('php://input'), true);
    $htmlResults = [];
    if (isset($batchInput['visitors']) && is_array($batchInput['visitors'])) {
      foreach ($batchInput['visitors'] as $visitorItem) {
        $visitor = $visitorItem;
        unset($link);
        ob_start();
        include __DIR__ . '/../pages/components/visitorContainer.php';
        $htmlResults[] = ob_get_clean();
      }
    }
    header('Content-Type: application/json');
    echo json_encode(['html' => $htmlResults]);
    break;
  case '/userContainer':
    $singleInput = json_decode(file_get_contents('php://input'), true);
    if (isset($singleInput['user']) && is_array($singleInput['user'])) {
      $user = $singleInput['user'];
    }

    $activeDeviceSessionCounts = [];
    $latestDeviceSeenTimestamps = [];
    $deviceStore = loadDeviceSessionsStore();
    if (isset($deviceStore['sessions']) && is_array($deviceStore['sessions'])) {
      foreach ($deviceStore['sessions'] as $sessionData) {
        if (!is_array($sessionData) || !empty($sessionData['revoked'])) {
          continue;
        }

        $sessionUserId = (string) ($sessionData['user_id'] ?? '');
        if ($sessionUserId === '') {
          continue;
        }

        if (!isset($activeDeviceSessionCounts[$sessionUserId])) {
          $activeDeviceSessionCounts[$sessionUserId] = 0;
        }
        $activeDeviceSessionCounts[$sessionUserId]++;

        $lastSeenAt = (int) ($sessionData['last_seen_at'] ?? 0);
        if ($lastSeenAt > 0 && (!isset($latestDeviceSeenTimestamps[$sessionUserId]) || $lastSeenAt > $latestDeviceSeenTimestamps[$sessionUserId])) {
          $latestDeviceSeenTimestamps[$sessionUserId] = $lastSeenAt;
        }
      }
    }

    include __DIR__ . '/../pages/components/userContainer.php';
    break;
  case '/userContainerBatch':
    $batchInput = json_decode(file_get_contents('php://input'), true);
    $htmlResults = [];

    $activeDeviceSessionCounts = [];
    $latestDeviceSeenTimestamps = [];
    $deviceStore = loadDeviceSessionsStore();
    if (isset($deviceStore['sessions']) && is_array($deviceStore['sessions'])) {
      foreach ($deviceStore['sessions'] as $sessionData) {
        if (!is_array($sessionData) || !empty($sessionData['revoked'])) {
          continue;
        }

        $sessionUserId = (string) ($sessionData['user_id'] ?? '');
        if ($sessionUserId === '') {
          continue;
        }

        if (!isset($activeDeviceSessionCounts[$sessionUserId])) {
          $activeDeviceSessionCounts[$sessionUserId] = 0;
        }
        $activeDeviceSessionCounts[$sessionUserId]++;

        $lastSeenAt = (int) ($sessionData['last_seen_at'] ?? 0);
        if ($lastSeenAt > 0 && (!isset($latestDeviceSeenTimestamps[$sessionUserId]) || $lastSeenAt > $latestDeviceSeenTimestamps[$sessionUserId])) {
          $latestDeviceSeenTimestamps[$sessionUserId] = $lastSeenAt;
        }
      }
    }

    if (isset($batchInput['users']) && is_array($batchInput['users'])) {
      foreach ($batchInput['users'] as $userItem) {
        $user = $userItem;
        unset($link, $visitor, $group);
        ob_start();
        include __DIR__ . '/../pages/components/userContainer.php';
        $htmlResults[] = ob_get_clean();
      }
    }

    header('Content-Type: application/json');
    echo json_encode(['html' => $htmlResults]);
    break;
  case '/groupContainer':
    include __DIR__ . '/../pages/components/groupContainer.php';
    break;
  case '/groupContainerBatch':
    $batchInput = json_decode(file_get_contents('php://input'), true);
    $htmlResults = [];
    if (isset($batchInput['groups']) && is_array($batchInput['groups'])) {
      foreach ($batchInput['groups'] as $groupItem) {
        $group = $groupItem;
        ob_start();
        include __DIR__ . '/../pages/components/groupContainer.php';
        $htmlResults[] = ob_get_clean();
      }
    }
    header('Content-Type: application/json');
    echo json_encode(['html' => $htmlResults]);
    break;
  case '/favourite':
    $data = json_decode(file_get_contents('php://input'), true);
    $data = $data['linkId'];
    include __DIR__ . '/../api/links/favourite.php';
    echo json_encode(favourite($data));
    break;
  case '/darkMode':
    include __DIR__ . '/../api/users/darkMode.php';
    echo json_encode(toggleDarkMode());
    break;
  case '/setLanguage':
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }

    $languageBody = json_decode((string) file_get_contents('php://input'), true);
    $nextLocale = normalizeUiLocale((string) ($languageBody['lang'] ?? ''));

    $_SESSION['ui_language'] = $nextLocale;
    setcookie('ui_language', $nextLocale, [
      'expires' => time() + (365 * 24 * 60 * 60),
      'path' => '/',
      'secure' => $uiLanguageCookieSecure,
      'httponly' => false,
      'samesite' => 'Lax',
    ]);

    $uiLocale = $nextLocale;
    $uiText = loadUiTextCatalog($uiLocale);

    header('Content-Type: application/json');
    echo json_encode([
      'success' => true,
      'lang' => $uiLocale,
      'texts' => uiCatalog(),
    ]);
    break;
  case '/i18nCatalog':
    header('Content-Type: application/json');
    echo json_encode([
      'success' => true,
      'lang' => uiLocale(),
      'texts' => uiCatalog(),
    ]);
    break;
  case '/profile/theme':
    include __DIR__ . '/../api/users/profileSettings.php';
    updateProfileTheme($_POST);
    break;
  case '/profile/identity':
    include __DIR__ . '/../api/users/profileSettings.php';
    updateProfileIdentity($_POST);
    break;
  case '/profile/password':
    include __DIR__ . '/../api/users/profileSettings.php';
    updateProfilePassword($_POST);
    break;
  case '/profile/photo':
    include __DIR__ . '/../api/users/profileSettings.php';
    updateProfilePhoto($_FILES);
    break;
  case '/getImages':
    include __DIR__ . '/../pages/components/getImages.php';
    break;
  case '/viewMode':
    include __DIR__ . '/../api/users/viewMode.php';
    echo json_encode(toggleViewMode());
    break;
  case '/profilePreferences':
    include __DIR__ . '/../api/users/profilePreferences.php';
    saveProfilePreferences();
    break;
  case '/changeProfilePassword':
    include __DIR__ . '/../api/users/changePassword.php';
    changeProfilePassword();
    break;
  case '/oneTimeLogin':
  case '/access-link-login':
    include __DIR__ . '/../api/users/auth/oneTimeLogin.php';
    oneTimeLogin();
    break;
  case '/createOneTime':
    include __DIR__ . '/../api/users/createOneTime.php';
    createOneTime($domain);
    break;
  case '/deactivateOneTime':
    include __DIR__ . '/../api/users/deactivateOneTime.php';
    $token = $_GET['token'] ?? ($_POST['token'] ?? null);
    deactivateOneTime($token);
    break;
  case '/deactivateAllOneTimes':
    include __DIR__ . '/../api/users/deactivateOneTime.php';
    deactivateAllOneTimes();
    break;
  case '/uploadImage':
    include __DIR__ . '/../api/custom/uploadImage.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
      $result = uploadImage($_FILES['image']);
      echo json_encode($result);
    } else {
      echo json_encode(['error' => 'Invalid request']);
    }
    break;
  case '/multiSelectDropdown':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/links/multiSelectDropdown.php';
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode(multiSelectDropdown($data ?? []));
    break;
  case '/deleteImage':
    include __DIR__ . '/../api/custom/deleteImage.php';
    echo json_encode(deleteImage($_GET['imageName']));
    break;
  case '/cleanupMedia':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/cleanupMedia.php';
    echo json_encode(cleanupMedia());
    break;
  case '/cleanupFragments':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/cleanupFragments.php';
    echo json_encode(cleanupFragments());
    break;
  case '/deleteOneTime':
    include __DIR__ . '/../api/users/deleteOneTime.php';
    $token = $_GET['token'] ?? ($_POST['token'] ?? null);
    deleteOneTime($token);
    break;
  case '/deviceSessions':
    include __DIR__ . '/../api/users/deviceSessions.php';
    getDeviceSessions();
    break;
  case '/revokeDeviceSession':
    include __DIR__ . '/../api/users/deviceSessions.php';
    revokeDeviceSession();
    break;
  case '/revokeOtherDeviceSessions':
    include __DIR__ . '/../api/users/deviceSessions.php';
    revokeOtherDeviceSessions();
    break;
  case '/revokeAllUserDeviceSessions':
    include __DIR__ . '/../api/users/deviceSessions.php';
    revokeAllDeviceSessionsForUser();
    break;
  case '/css':
  case '/custom-css-editor':
    $comp = strtolower(trim((string) ($_GET['comp'] ?? 'dashboard')));
    if ($comp === 'dark') {
      include __DIR__ . '/../api/custom/editDarkCss.php';
    } else if ($comp === 'light') {
      include __DIR__ . '/../api/custom/editLightCss.php';
    } else if ($comp === 'branding') {
      include __DIR__ . '/../api/custom/editBrandingDashboard.php';
    } else {
      include __DIR__ . '/../api/custom/editCssDashboard.php';
    }
    break;
  case '/loginModal':
    include __DIR__ . '/../pages/components/modals/loginModal.php';
    break;
  case '/uploadCustom404':
    include __DIR__ . '/../api/custom/uploadCustom404.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
      uploadCustom404($_FILES['file']);
    } else {
      echo 'no file';
      exit;
    }
    break;
  case '/keyboardShortcuts':
    include __DIR__ . '/../pages/components/modals/keyboardShortcuts.php';
    break;
  case '/deleteDuplicates':
    removeDuplicateTags();
    break;
  case '/deleteCustom404':
    include __DIR__ . '/../api/custom/uploadCustom404.php';
    deleteCustom404();
    break;
  case '/setup':
    include __DIR__ . '/../pages/setup.php';
    break;
  case '/generateTheme':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/generateTheme.php';
    break;
  case '/resetProject':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/resetProject.php';
    break;
  case '/saveSetupPreferences':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/saveSetupPreferences.php';
    break;
  case '/exportBundle':
    include __DIR__ . '/../api/custom/exportBundle.php';
    break;
  case '/importBundle':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      include __DIR__ . '/../pages/errors/404.php';
      break;
    }
    include __DIR__ . '/../api/custom/importBundle.php';
    break;
  default:
    $shortlink = ltrim($requestUri, '/');
    handleShortlink($shortlink);
    break;
}
