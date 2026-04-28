<?php

function oneTimeLogin()
{
  if (session_status() == PHP_SESSION_NONE) {
    session_start();
  }

  // Check if token is provided
  if (!isset($_GET['token']) || empty($_GET['token'])) {
    setErrorAndRedirect('No login token provided');
    exit();
  }

  $token = trim($_GET['token']);

  // Validate token format (should be 32 hex chars)
  if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
    setErrorAndRedirect('Invalid token format');
    exit();
  }

  try {
    $filePath = __DIR__ . '/../../../public/json/allowedTokens.json';

    if (!file_exists($filePath)) {
      setErrorAndRedirect('Authentication system error');
      exit();
    }

    $fileContents = file_get_contents($filePath);
    $allowedTokens = json_decode($fileContents, true);

    if (!is_array($allowedTokens) || !isset($allowedTokens['tokens'])) {
      setErrorAndRedirect('Authentication system error');
      exit();
    }

    // Check if token exists
    if (!array_key_exists($token, $allowedTokens['tokens'])) {
      setErrorAndRedirect('Invalid or expired login link');
      exit();
    }

    $clickTime = time();
    if (!empty($allowedTokens['tokens'][$token]['clicked'])) {
      $allowedTokens['tokens'][$token]['click_count'] = (int) ($allowedTokens['tokens'][$token]['click_count'] ?? 0) + 1;
    } else {
      $allowedTokens['tokens'][$token]['clicked'] = true;
      $allowedTokens['tokens'][$token]['clicked_on'] = $clickTime;
      $allowedTokens['tokens'][$token]['click_count'] = 1;
    }
    $allowedTokens['tokens'][$token]['last_clicked_on'] = $clickTime;

    file_put_contents($filePath, json_encode($allowedTokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    $tokenData = $allowedTokens['tokens'][$token];

    // Check if token is deactivated
    if (!empty($tokenData['deactivated'])) {
      setErrorAndRedirect('This login link has been deactivated');
      exit();
    }

    $now = time();
    $loginValidFrom = isset($tokenData['login_valid_from']) ? (int) $tokenData['login_valid_from'] : (isset($tokenData['created']) ? (int) $tokenData['created'] : 0);
    $loginValidUntil = isset($tokenData['login_valid_until']) ? (int) $tokenData['login_valid_until'] : (isset($tokenData['expiration']) ? (int) $tokenData['expiration'] : 0);

    if ($loginValidFrom > 0 && $now < $loginValidFrom) {
      setErrorAndRedirect('This login link is not active yet');
      exit();
    }

    // Check if token is expired
    if ($loginValidUntil > 0 && $loginValidUntil < $now) {
      setErrorAndRedirect('This login link has expired');
      exit();
    }

    $hasAuthenticatedSession = isset($_SESSION['user'])
      && is_array($_SESSION['user'])
      && !empty($_SESSION['user']['id'])
      && $_SESSION['user']['id'] !== 'tempUser';

    // If someone opens a one-time link while already signed in,
    // switch directly to one-time session instead of forcing manual logout.
    if ($hasAuthenticatedSession) {
      if (function_exists('revokeCurrentDeviceSession')) {
        revokeCurrentDeviceSession();
      }

      setcookie('device_session', '', time() - 3600, '/', '', false, true);
      setcookie('token', '', time() - 3600, '/', '', false, true);

      $_SESSION = [];
      if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
      }
    }

    if (!empty($tokenData['used'])) {
      $usedOn = isset($tokenData['used_on']) ? (int) $tokenData['used_on'] : 0;
      $sessionMinutes = isset($tokenData['session_duration_minutes']) ? (int) $tokenData['session_duration_minutes'] : 60;
      if ($sessionMinutes < 5 || $sessionMinutes > 10080) {
        $sessionMinutes = 60;
      }

      $usedSessionExpiresAt = $usedOn > 0 ? ($usedOn + ($sessionMinutes * 60)) : 0;
      if (($loginValidUntil > 0 && $now > $loginValidUntil) || ($usedSessionExpiresAt > 0 && $now > $usedSessionExpiresAt)) {
        setErrorAndRedirect('This login link has expired');
        exit();
      }

      setErrorAndRedirect('This login link has already been used');
      exit();
    }

    // Check if user is already logged in with same token
    if (isset($_COOKIE['token']) && $_COOKIE['token'] === $token) {
      header('Location: /');
      exit();
    }

    $sessionMinutes = isset($tokenData['session_duration_minutes']) ? (int) $tokenData['session_duration_minutes'] : 60;
    if ($sessionMinutes < 5 || $sessionMinutes > 10080) {
      $sessionMinutes = 60;
    }

    // Token is valid - set session
    $sessionExpiration = $sessionMinutes * 60;

    setcookie('token', $token, time() + $sessionExpiration, '/', '', false, true);

    // Update token as used
    $allowedTokens['tokens'][$token]['used'] = true;
    $allowedTokens['tokens'][$token]['used_on'] = time();
    $allowedTokens['tokens'][$token]['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Persist changes
    file_put_contents($filePath, json_encode($allowedTokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    // Create session
    $sessionGroups = [];

    if (isset($tokenData['scope_groups']) && is_array($tokenData['scope_groups'])) {
      foreach ($tokenData['scope_groups'] as $scopeGroup) {
        if (!is_array($scopeGroup)) {
          continue;
        }

        $groupId = isset($scopeGroup['id']) ? (int) $scopeGroup['id'] : 0;
        $groupTitle = trim((string) ($scopeGroup['title'] ?? ''));

        if ($groupId <= 0 || $groupTitle === '') {
          continue;
        }

        $sessionGroups[] = [
          'id' => $groupId,
          'title' => $groupTitle
        ];
      }
    }

    if (empty($sessionGroups)) {
      $scopedGroupId = isset($tokenData['scope_group_id']) ? (int) $tokenData['scope_group_id'] : 0;
      $scopedGroupTitle = trim((string) ($tokenData['scope_group_title'] ?? ''));

      if ($scopedGroupId > 0 && $scopedGroupTitle !== '') {
        $sessionGroups[] = [
          'id' => $scopedGroupId,
          'title' => $scopedGroupTitle
        ];
      }
    }

    $isScopedGroupToken = !empty($sessionGroups);

    $sessionRole = trim((string) ($tokenData['role'] ?? ''));
    if ($sessionRole === '') {
      $sessionRole = $isScopedGroupToken ? 'limited' : 'viewer';
    }

    $allowedSessionRoles = ['viewer', 'limited', 'user', 'admin', 'superadmin'];
    if (!in_array($sessionRole, $allowedSessionRoles, true)) {
      $sessionRole = $isScopedGroupToken ? 'limited' : 'viewer';
    }

    $_SESSION['user'] = [
      'id' => 'tempUser',
      'email' => 'guest@temporary.local',
      'name' => 'Access',
      'family_name' => 'User',
      'picture' => '/images/user.jpg',
      'role' => $sessionRole,
      'mode' => 'dark',
      'view' => 'full',
      'limit' => 10,
      'read_only' => true,
      'accessLinkToken' => $token,
      'accessLinkRole' => $sessionRole,
      'accessLinkSessionMinutes' => $sessionMinutes,
      'accessLinkTokenExpires' => time() + $sessionExpiration,
      'oneTimeToken' => $token,
      'oneTimeTokenRole' => $sessionRole,
      'oneTimeSessionMinutes' => $sessionMinutes,
      'oneTimeTokenExpires' => time() + $sessionExpiration,
      'loginedAt' => time()
    ];

    if ($isScopedGroupToken) {
      $_SESSION['groups'] = $sessionGroups;
    } else {
      unset($_SESSION['groups']);
    }

    // Redirect to dashboard
    header('Location: /');
    exit();
  } catch (Exception $e) {
    error_log('OneTimeLogin error: ' . $e->getMessage());
    setErrorAndRedirect('An unexpected error occurred');
    exit();
  }
}

/**
 * Sets an error message and redirects to home
 */
function setErrorAndRedirect($message)
{
  if (session_status() == PHP_SESSION_NONE) {
    session_start();
  }
  $_SESSION['error'] = $message;
  $_SESSION['errorType'] = 'login';
  header('Location: /');
}

function setUiNoticeAndRedirect($message, $redirectPath = '/')
{
  if (session_status() == PHP_SESSION_NONE) {
    session_start();
  }

  $_SESSION['ui_notice'] = $message;
  $_SESSION['ui_notice_type'] = 'error';

  header('Location: ' . $redirectPath);
}
