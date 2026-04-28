<?php
function getUserRolePriorityScore($role)
{
  $role = strtolower(trim((string) $role));
  $priorities = [
    'viewer' => 1,
    'limited' => 2,
    'user' => 3,
    'admin' => 4,
    'superadmin' => 5,
  ];

  return $priorities[$role] ?? 0;
}

function getDeviceSessionsFilePath()
{
  $canonicalPath = __DIR__ . '/../../public/json/deviceSessions.json';

  // Quick write-test: if the canonical location is writable, use it.
  $dir = dirname($canonicalPath);
  if (is_dir($dir)) {
    $testFile = $dir . DIRECTORY_SEPARATOR . '.write_test_' . getmypid();
    if (@file_put_contents($testFile, '1') !== false) {
      @unlink($testFile);
      return $canonicalPath;
    }
  }

  // Fallback: keep device sessions in a temp directory that persists across
  // server restarts (same approach as the database working copy).
  $projectHash = md5(realpath(__DIR__ . '/../../'));
  $fallbackDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'neptunus-data-' . $projectHash;
  if (!is_dir($fallbackDir)) {
    @mkdir($fallbackDir, 0770, true);
  }
  $fallbackPath = $fallbackDir . DIRECTORY_SEPARATOR . 'deviceSessions.json';

  // Seed from canonical if the fallback doesn't exist yet
  if (!file_exists($fallbackPath) && file_exists($canonicalPath)) {
    @copy($canonicalPath, $fallbackPath);
  }

  return $fallbackPath;
}

function loadDeviceSessionsStore()
{
  $filePath = getDeviceSessionsFilePath();

  if (!file_exists($filePath)) {
    return ['sessions' => []];
  }

  $fp = @fopen($filePath, 'r');
  if (!$fp) {
    return ['sessions' => []];
  }

  flock($fp, LOCK_SH);
  $raw = (string) stream_get_contents($fp);
  flock($fp, LOCK_UN);
  fclose($fp);

  $decoded = json_decode($raw, true);

  if (!is_array($decoded) || !isset($decoded['sessions']) || !is_array($decoded['sessions'])) {
    return ['sessions' => []];
  }

  return $decoded;
}

function saveDeviceSessionsStore($store)
{
  $filePath = getDeviceSessionsFilePath();
  $dir = dirname($filePath);

  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }

  if (!is_array($store)) {
    $store = ['sessions' => []];
  }

  if (!isset($store['sessions']) || !is_array($store['sessions'])) {
    $store['sessions'] = [];
  }

  return @file_put_contents($filePath, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function getDeviceSessionLifetimeSeconds()
{
  $lifetime = defined('APP_SESSION_LIFETIME_SECONDS') ? (int) APP_SESSION_LIFETIME_SECONDS : 604800;
  if ($lifetime < 300) {
    $lifetime = 300;
  }
  return $lifetime;
}

function hashDeviceSessionToken($token)
{
  return hash('sha256', (string) $token);
}

function issueUserDeviceSession($user)
{
  $userId = isset($user['id']) ? (string) $user['id'] : '';
  if ($userId === '' || $userId === 'tempUser') {
    return null;
  }

  $token = bin2hex(random_bytes(32));
  $tokenHash = hashDeviceSessionToken($token);
  $sessionId = bin2hex(random_bytes(8));
  $now = time();

  $store = loadDeviceSessionsStore();
  $store['sessions'][] = [
    'session_id' => $sessionId,
    'user_id' => $userId,
    'email' => (string) ($user['email'] ?? ''),
    'token_hash' => $tokenHash,
    'created_at' => $now,
    'last_seen_at' => $now,
    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    'revoked' => false,
    'revoked_at' => null,
  ];
  saveDeviceSessionsStore($store);

  if (!headers_sent()) {
    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('device_session', $token, time() + getDeviceSessionLifetimeSeconds(), '/', '', $secureCookie, true);
  }

  $_SESSION['device_session_token_hash'] = $tokenHash;
  $_SESSION['device_session_id'] = $sessionId;

  return $sessionId;
}

function validateOrIssueDeviceSession($user)
{
  $userId = isset($user['id']) ? (string) $user['id'] : '';
  if ($userId === '' || $userId === 'tempUser') {
    return true;
  }

  $cookieToken = isset($_COOKIE['device_session']) ? (string) $_COOKIE['device_session'] : '';
  if ($cookieToken === '') {
    issueUserDeviceSession($user);
    return true;
  }

  $tokenHash = hashDeviceSessionToken($cookieToken);
  $store = loadDeviceSessionsStore();
  $found = false;
  $now = time();

  foreach ($store['sessions'] as &$sessionData) {
    if ((string) ($sessionData['token_hash'] ?? '') !== $tokenHash) {
      continue;
    }

    if ((string) ($sessionData['user_id'] ?? '') !== $userId) {
      return false;
    }

    if (!empty($sessionData['revoked'])) {
      return false;
    }

    $sessionData['last_seen_at'] = $now;
    $found = true;
    $_SESSION['device_session_token_hash'] = $tokenHash;
    $_SESSION['device_session_id'] = (string) ($sessionData['session_id'] ?? '');
    break;
  }
  unset($sessionData);

  if (!$found) {
    return false;
  }

  saveDeviceSessionsStore($store);
  return true;
}

function revokeCurrentDeviceSession()
{
  $cookieToken = isset($_COOKIE['device_session']) ? (string) $_COOKIE['device_session'] : '';
  if ($cookieToken === '') {
    return;
  }

  $tokenHash = hashDeviceSessionToken($cookieToken);
  $store = loadDeviceSessionsStore();
  $now = time();

  foreach ($store['sessions'] as &$sessionData) {
    if ((string) ($sessionData['token_hash'] ?? '') !== $tokenHash) {
      continue;
    }

    $sessionData['revoked'] = true;
    $sessionData['revoked_at'] = $now;
    break;
  }
  unset($sessionData);

  saveDeviceSessionsStore($store);
}

function getCurrentDeviceSessionId()
{
  return (string) ($_SESSION['device_session_id'] ?? '');
}

function getUserDeviceSessions($userId)
{
  $userId = (string) $userId;
  if ($userId === '') {
    return [];
  }

  $store = loadDeviceSessionsStore();
  $sessions = [];

  foreach ($store['sessions'] as $sessionData) {
    if ((string) ($sessionData['user_id'] ?? '') !== $userId) {
      continue;
    }

    $sessions[] = [
      'session_id' => (string) ($sessionData['session_id'] ?? ''),
      'created_at' => (int) ($sessionData['created_at'] ?? 0),
      'last_seen_at' => (int) ($sessionData['last_seen_at'] ?? 0),
      'ip' => (string) ($sessionData['ip'] ?? ''),
      'user_agent' => (string) ($sessionData['user_agent'] ?? ''),
      'revoked' => (bool) ($sessionData['revoked'] ?? false),
      'revoked_at' => isset($sessionData['revoked_at']) ? (int) $sessionData['revoked_at'] : null,
    ];
  }

  usort($sessions, function ($a, $b) {
    return ($b['last_seen_at'] ?? 0) <=> ($a['last_seen_at'] ?? 0);
  });

  return $sessions;
}

function revokeUserDeviceSessionById($userId, $sessionId)
{
  $userId = (string) $userId;
  $sessionId = trim((string) $sessionId);
  if ($userId === '' || $sessionId === '') {
    return false;
  }

  $store = loadDeviceSessionsStore();
  $now = time();
  $changed = false;

  foreach ($store['sessions'] as &$sessionData) {
    if ((string) ($sessionData['user_id'] ?? '') !== $userId) {
      continue;
    }

    if ((string) ($sessionData['session_id'] ?? '') !== $sessionId) {
      continue;
    }

    $sessionData['revoked'] = true;
    $sessionData['revoked_at'] = $now;
    $changed = true;
    break;
  }
  unset($sessionData);

  if ($changed) {
    saveDeviceSessionsStore($store);
  }

  return $changed;
}

function revokeOtherUserDeviceSessions($userId, $currentSessionId)
{
  $userId = (string) $userId;
  $currentSessionId = (string) $currentSessionId;
  if ($userId === '') {
    return 0;
  }

  $store = loadDeviceSessionsStore();
  $now = time();
  $count = 0;

  foreach ($store['sessions'] as &$sessionData) {
    if ((string) ($sessionData['user_id'] ?? '') !== $userId) {
      continue;
    }

    if ($currentSessionId !== '' && (string) ($sessionData['session_id'] ?? '') === $currentSessionId) {
      continue;
    }

    if (!empty($sessionData['revoked'])) {
      continue;
    }

    $sessionData['revoked'] = true;
    $sessionData['revoked_at'] = $now;
    $count++;
  }
  unset($sessionData);

  if ($count > 0) {
    saveDeviceSessionsStore($store);
  }

  return $count;
}

function revokeAllUserDeviceSessions($userId)
{
  $userId = (string) $userId;
  if ($userId === '') {
    return 0;
  }

  $store = loadDeviceSessionsStore();
  $now = time();
  $count = 0;

  foreach ($store['sessions'] as &$sessionData) {
    if ((string) ($sessionData['user_id'] ?? '') !== $userId) {
      continue;
    }

    if (!empty($sessionData['revoked'])) {
      continue;
    }

    $sessionData['revoked'] = true;
    $sessionData['revoked_at'] = $now;
    $count++;
  }
  unset($sessionData);

  if ($count > 0) {
    saveDeviceSessionsStore($store);
  }

  return $count;
}

function checkUser()
{
  // Start the session if it hasn't been started yet
  if (session_status() == PHP_SESSION_NONE) {
    session_start();
  }

  // Check if the user is logged in
  if (!isset($_SESSION['user'])) {
    header('Location: /home');
    exit;
  }

  $hasTokenCookie = isset($_COOKIE['token']) && $_COOKIE['token'] !== '';
  $isOneTimeSession = isAccessLinkSession();

  if ($hasTokenCookie && $isOneTimeSession) {
    if (!checkCookieToken()) {
      if (!headers_sent()) {
        setcookie('token', '', time() - 3600, '/', '', false, true);
      }
      header('Location: /home');
      exit;
    }
    return true;
  }

  // Connect to the database
  $pdo = connectToDatabase();

  // Fetch the user from the database by stable primary key first.
  $user = false;
  if (!empty($_SESSION['user']['id']) && $_SESSION['user']['id'] !== 'tempUser') {
    $sql = 'SELECT * FROM users WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => (int) $_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
  }

  if (!$user && !empty($_SESSION['user']['email'])) {
    $sql = 'SELECT * FROM users WHERE lower(trim(email)) = lower(trim(:email)) ORDER BY id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => (string) $_SESSION['user']['email']]);
    $emailMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($emailMatches) === 1) {
      $user = $emailMatches[0];
    } elseif (count($emailMatches) > 1) {
      $bestMatch = null;
      $bestRoleScore = -1;
      $bestId = 0;

      foreach ($emailMatches as $candidate) {
        $candidateRoleScore = getUserRolePriorityScore($candidate['role'] ?? '');
        $candidateId = (int) ($candidate['id'] ?? 0);

        if ($bestMatch === null || $candidateRoleScore > $bestRoleScore || ($candidateRoleScore === $bestRoleScore && $candidateId > $bestId)) {
          $bestMatch = $candidate;
          $bestRoleScore = $candidateRoleScore;
          $bestId = $candidateId;
        }
      }

      $user = $bestMatch;
    }
  }

  closeConnection($pdo);

  // Check if the user exists and if the token is set
  if (!$user) {
    header('Location: /home');
    exit;
  }

  if (!validateOrIssueDeviceSession($user)) {
    revokeCurrentDeviceSession();
    if (!headers_sent()) {
      setcookie('device_session', '', time() - 3600, '/', '', false, true);
    }
    header('Location: /home');
    exit;
  }

  // Update the session with the fetched user data
  $_SESSION['user'] = $user;

  return true;
}

function updateLastLogin($id)
{
  $pdo = connectToDatabase();
  $sql = 'UPDATE users SET last_login = :last_login WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['last_login' => date('Y-m-d H:i:s'), 'id' => $id]);
  closeConnection($pdo);
}

function checkCookieToken()
{
  $filePath = __DIR__ . '/../../public/json/allowedTokens.json';
  if (!file_exists($filePath)) {
    return false;
  }
  $fileContents = file_get_contents($filePath);
  $allowedTokens = json_decode($fileContents, true);

  if (!is_array($allowedTokens) || !isset($allowedTokens['tokens']) || !is_array($allowedTokens['tokens'])) {
    return false;
  }

  if (!isset($_COOKIE['token']) || $_COOKIE['token'] === '') {
    return false;
  }

  $token = (string) $_COOKIE['token'];

  if (!array_key_exists($token, $allowedTokens['tokens'])) {
    return false;
  }

  // Check if token has been deactivated
  if ($allowedTokens['tokens'][$token]['deactivated'] === true) {
    return false;
  }

  $tokenData = $allowedTokens['tokens'][$token];
  $now = time();

  $loginValidFrom = isset($tokenData['login_valid_from']) ? (int) $tokenData['login_valid_from'] : (isset($tokenData['created']) ? (int) $tokenData['created'] : 0);
  $loginValidUntil = isset($tokenData['login_valid_until']) ? (int) $tokenData['login_valid_until'] : (isset($tokenData['expiration']) ? (int) $tokenData['expiration'] : 0);

  if (!empty($tokenData['used'])) {
    $usedOn = isset($tokenData['used_on']) ? (int) $tokenData['used_on'] : 0;
    $sessionMinutes = isset($tokenData['session_duration_minutes']) ? (int) $tokenData['session_duration_minutes'] : 60;

    if ($sessionMinutes < 5 || $sessionMinutes > 10080) {
      $sessionMinutes = 60;
    }

    if ($usedOn <= 0) {
      return false;
    }

    if ($now > ($usedOn + ($sessionMinutes * 60))) {
      return false;
    }

    return true;
  }

  if ($loginValidFrom > 0 && $now < $loginValidFrom) {
    return false;
  }

  if ($loginValidUntil > 0 && $now > $loginValidUntil) {
    return false;
  }

  return true;
}

function checkAdmin()
{
  $role = $_SESSION['user']['role'] ?? null;

  if ($role === 'admin' || $role === 'superadmin') {
    return true;
  } else {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isJsonRequest = strpos($acceptHeader, 'application/json') !== false;

    if ($method !== 'GET' || $isJsonRequest) {
      http_response_code(401);
    }
    return false;
  }
}

function checkSuperAdmin()
{
  $role = $_SESSION['user']['role'] ?? null;
  return $role === 'superadmin';
}

function getEmailById($id)
{
  $pdo = connectToDatabase();
  $sql = 'SELECT email FROM users WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $email = $stmt->fetch();
  closeConnection($pdo);

  if ($email === false) {
    return null;
  }

  return $email['email'];
}

function getAllUsers()
{
  $pdo = connectToDatabase();
  checkUser();
  $sql = 'SELECT * FROM users';
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $users = $stmt->fetchAll();

  foreach ($users as $key => $user) {
    $sql = 'SELECT tags.* FROM users_tags 
        INNER JOIN tags ON users_tags.tag_id = tags.id 
        WHERE users_tags.user_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $user['id']]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = 'SELECT groups.* FROM users_groups 
        INNER JOIN groups ON users_groups.group_id = groups.id 
        WHERE users_groups.user_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $user['id']]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $users[$key]['tags'] = $tags;
    $users[$key]['groups'] = $groups;
  }

  closeConnection($pdo);
  return $users;
}

function getVisitorsByGroup($group)
{
  $pdo = connectToDatabase();

  // Fetch visitors who have visited links in the specified group
  $sql = 'SELECT DISTINCT visitors.* FROM visitors
          INNER JOIN visits ON visitors.id = visits.visitor_id
          INNER JOIN link_groups ON visits.link_id = link_groups.link_id
          INNER JOIN groups ON link_groups.group_id = groups.id
          WHERE groups.title = :group';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['group' => $group]);
  $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($visitors as &$visitor) {
    // Fetch visits for the visitor
    $sql = 'SELECT * FROM visits WHERE visitor_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $visitor['id']]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch tags for the visitor
    $sql = 'SELECT visitors_tags.visitor_id, tags.* FROM visitors_tags LEFT JOIN tags ON visitors_tags.tag_id = tags.id WHERE visitors_tags.visitor_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $visitor['id']]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch groups for the links the visitor has visited
    $sql = 'SELECT DISTINCT groups.* FROM visits 
            INNER JOIN link_groups ON visits.link_id = link_groups.link_id 
            INNER JOIN groups ON link_groups.group_id = groups.id 
            WHERE visits.visitor_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $visitor['id']]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add tags, groups, and visits to the visitor
    $visitor['tags'] = $tags;
    $visitor['groups'] = $groups;
    $visitor['visits'] = $visits;
  }

  closeConnection($pdo);
  return $visitors;
}

function getVisitorById($id)
{
  $pdo = connectToDatabase();

  // Fetch the visitor
  $sql = 'SELECT * FROM visitors WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$visitor) {
    // Handle case where visitor is not found
    closeConnection($pdo);
    return null;
  }

  // Fetch visits for the visitor
  $sql = 'SELECT * FROM visits WHERE visitor_id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $visitor['id']]);
  $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch tags for the visitor
  $sql = 'SELECT visitors_tags.visitor_id, tags.* FROM visitors_tags LEFT JOIN tags ON visitors_tags.tag_id = tags.id WHERE visitors_tags.visitor_id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $visitor['id']]);
  $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch groups for the visitor
  $sql = 'SELECT visitors_groups.visitor_id, groups.* FROM visitors_groups LEFT JOIN groups ON visitors_groups.group_id = groups.id WHERE visitors_groups.visitor_id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $visitor['id']]);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Add tags, groups, and visits to the visitor
  $visitor['tags'] = $tags;
  $visitor['groups'] = $groups;
  $visitor['visits'] = $visits;

  closeConnection($pdo);
  return $visitor;
}

function getEditedLinksCount($id)
{
  $pdo = connectToDatabase();
  $sql = 'SELECT COUNT(*) FROM links WHERE creator = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $count = $stmt->fetchColumn();
  closeConnection($pdo);
  return $count;
}

function getUserById($id)
{
  $pdo = connectToDatabase();
  checkUser();
  $sql = 'SELECT * FROM users WHERE id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $user = $stmt->fetch();

  $sql = 'SELECT tags.* FROM users_tags 
        INNER JOIN tags ON users_tags.tag_id = tags.id 
        WHERE users_tags.user_id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $sql = 'SELECT groups.* FROM users_groups 
        INNER JOIN groups ON users_groups.group_id = groups.id 
        WHERE users_groups.user_id = :id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $user['tags'] = $tags;

  $user['groups'] = $groups;

  closeConnection($pdo);
  return $user;
}

function getUserHistory($id)
{
  $pdo = connectToDatabase();
  checkUser();

  $email = getEmailById($id);
  $groupHistory = [];
  $accessLinkHistory = [];

  $sql = 'SELECT * FROM links WHERE modifier = :id OR modifier = :email ORDER BY modified_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id, 'email' => $email]);
  $linkHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $sql = 'SELECT * FROM users WHERE modifier = :id OR modifier = :email ORDER BY modified_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id, 'email' => $email]);
  $userHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $sql = 'SELECT * FROM visitors WHERE modifier = :id OR modifier = :email ORDER BY modified_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id, 'email' => $email]);
  $visitorHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($email) {
    $sql = 'SELECT * FROM groups WHERE modifier = :email OR modifier = :id ORDER BY modified_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email, 'id' => $id]);
    $groupHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  $hydrateTags = function (array $records, string $joinTable, string $recordColumn) use ($pdo) {
    if (count($records) === 0) {
      return $records;
    }

    $ids = array_values(array_unique(array_map(function ($record) {
      return (int) ($record['id'] ?? 0);
    }, $records)));
    $ids = array_values(array_filter($ids, function ($idValue) {
      return $idValue > 0;
    }));

    if (count($ids) === 0) {
      foreach ($records as &$record) {
        $record['tags'] = [];
      }
      unset($record);
      return $records;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT jt.$recordColumn AS record_id, t.title
            FROM $joinTable jt
            INNER JOIN tags t ON t.id = jt.tag_id
            WHERE jt.$recordColumn IN ($placeholders)
            ORDER BY t.title ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $tagRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tagMap = [];
    foreach ($tagRows as $tagRow) {
      $recordId = (int) ($tagRow['record_id'] ?? 0);
      if ($recordId <= 0) {
        continue;
      }

      if (!isset($tagMap[$recordId])) {
        $tagMap[$recordId] = [];
      }

      $tagMap[$recordId][] = [
        'title' => (string) ($tagRow['title'] ?? '')
      ];
    }

    foreach ($records as &$record) {
      $recordId = (int) ($record['id'] ?? 0);
      $record['tags'] = $tagMap[$recordId] ?? [];
    }
    unset($record);

    return $records;
  };

  $linkHistory = $hydrateTags($linkHistory, 'link_tags', 'link_id');
  $userHistory = $hydrateTags($userHistory, 'users_tags', 'user_id');
  $visitorHistory = $hydrateTags($visitorHistory, 'visitors_tags', 'visitor_id');

  $tokenFilePath = __DIR__ . '/../../public/json/allowedTokens.json';
  if (is_file($tokenFilePath)) {
    $tokensData = json_decode((string) file_get_contents($tokenFilePath), true);
    if (is_array($tokensData) && isset($tokensData['tokens']) && is_array($tokensData['tokens'])) {
      $idAsString = (string) $id;
      $emailLower = strtolower((string) $email);

      foreach ($tokensData['tokens'] as $tokenKey => $tokenData) {
        if (!is_array($tokenData)) {
          continue;
        }

        $createdBy = strtolower(trim((string) ($tokenData['created_by'] ?? '')));
        if ($createdBy === '') {
          continue;
        }

        if ($createdBy !== $emailLower && $createdBy !== strtolower($idAsString)) {
          continue;
        }

        $token = trim((string) ($tokenData['token'] ?? $tokenKey));
        if ($token === '') {
          continue;
        }

        $createdTs = isset($tokenData['created']) ? (int) $tokenData['created'] : 0;
        $usedOnTs = isset($tokenData['used_on']) ? (int) $tokenData['used_on'] : 0;
        $deactivatedOnTs = isset($tokenData['deactivated_on']) ? (int) $tokenData['deactivated_on'] : 0;
        $lastClickedTs = isset($tokenData['last_clicked_on']) ? (int) $tokenData['last_clicked_on'] : 0;

        $eventTs = max($createdTs, $usedOnTs, $deactivatedOnTs, $lastClickedTs);
        if ($eventTs <= 0) {
          $eventTs = $createdTs;
        }

        $status = 'active';
        if (!empty($tokenData['deactivated'])) {
          $status = 'deactivated';
        } else if (!empty($tokenData['used'])) {
          $status = 'used';
        }

        $role = trim((string) ($tokenData['role'] ?? 'viewer'));
        if ($role === '') {
          $role = 'viewer';
        }

        $scopeGroups = isset($tokenData['scope_groups']) && is_array($tokenData['scope_groups'])
          ? $tokenData['scope_groups']
          : [];

        $scopeTitles = [];
        foreach ($scopeGroups as $scopeGroup) {
          if (!is_array($scopeGroup)) {
            continue;
          }

          $scopeTitle = trim((string) ($scopeGroup['title'] ?? ''));
          if ($scopeTitle !== '') {
            $scopeTitles[] = $scopeTitle;
          }
        }

        if (count($scopeTitles) === 0) {
          $legacyScopeTitle = trim((string) ($tokenData['scope_group_title'] ?? ''));
          if ($legacyScopeTitle !== '') {
            $scopeTitles[] = $legacyScopeTitle;
          }
        }

        $scopeSummary = count($scopeTitles) > 0 ? implode(', ', array_values(array_unique($scopeTitles))) : 'All groups';

        $accessLinkHistory[] = [
          'id' => $token,
          'title' => 'Access link ' . substr($token, 0, 8),
          'modifier' => $createdBy,
          'created_at' => $createdTs > 0 ? date('Y-m-d H:i:s', $createdTs) : null,
          'modified_at' => $eventTs > 0 ? date('Y-m-d H:i:s', $eventTs) : null,
          'status' => $status,
          'role' => $role,
          'scope' => $scopeSummary,
          'click_count' => isset($tokenData['click_count']) ? (int) $tokenData['click_count'] : 0,
          'used' => !empty($tokenData['used']),
          'deactivated' => !empty($tokenData['deactivated'])
        ];
      }
    }

    if (is_array($tokensData) && isset($tokensData['history_events']) && is_array($tokensData['history_events'])) {
      $emailLower = strtolower((string) $email);
      $idAsString = strtolower((string) $id);

      foreach ($tokensData['history_events'] as $eventItem) {
        if (!is_array($eventItem)) {
          continue;
        }

        $actor = strtolower(trim((string) ($eventItem['actor'] ?? '')));
        if ($actor === '' || ($actor !== $emailLower && $actor !== $idAsString)) {
          continue;
        }

        $eventAt = isset($eventItem['at']) ? (int) $eventItem['at'] : 0;
        if ($eventAt <= 0) {
          continue;
        }

        $event = trim((string) ($eventItem['event'] ?? 'updated'));
        if ($event === '') {
          $event = 'updated';
        }

        $token = trim((string) ($eventItem['token'] ?? 'unknown'));
        $scopeLabels = [];
        if (isset($eventItem['scope']) && is_array($eventItem['scope'])) {
          foreach ($eventItem['scope'] as $scopeTitle) {
            $title = trim((string) $scopeTitle);
            if ($title !== '') {
              $scopeLabels[] = $title;
            }
          }
        }

        $scopeSummary = count($scopeLabels) > 0 ? implode(', ', $scopeLabels) : 'All groups';

        $accessLinkHistory[] = [
          'id' => $token . '-' . $eventAt . '-' . $event,
          'title' => 'Access link ' . $event,
          'modifier' => $actor,
          'created_at' => date('Y-m-d H:i:s', $eventAt),
          'modified_at' => date('Y-m-d H:i:s', $eventAt),
          'status' => $event,
          'role' => trim((string) ($eventItem['role'] ?? 'viewer')),
          'scope' => $scopeSummary,
          'click_count' => 0,
          'used' => $event === 'used',
          'deactivated' => $event === 'deactivated'
        ];
      }
    }
  }

  $history = [
    'users' => $userHistory,
    'visitors' => $visitorHistory,
    'groups' => $groupHistory,
    'links' => $linkHistory,
    'access_links' => $accessLinkHistory
  ];

  usort($history['links'], function ($a, $b) {
    return strtotime($b['modified_at']) - strtotime($a['modified_at']);
  });
  usort($history['users'], function ($a, $b) {
    return strtotime($b['modified_at']) - strtotime($a['modified_at']);
  });
  usort($history['visitors'], function ($a, $b) {
    return strtotime($b['modified_at']) - strtotime($a['modified_at']);
  });
  usort($history['groups'], function ($a, $b) {
    return strtotime($b['modified_at']) - strtotime($a['modified_at']);
  });
  usort($history['access_links'], function ($a, $b) {
    return strtotime((string) $b['modified_at']) - strtotime((string) $a['modified_at']);
  });
  closeConnection($pdo);
  return $history;
}

function getUserIP()
{
  $client  = @$_SERVER['HTTP_CLIENT_IP'];
  $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
  $remote  = $_SERVER['REMOTE_ADDR'];

  if (filter_var($client, FILTER_VALIDATE_IP)) {
    $ip = $client;
  } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
    $ip = $forward;
  } else {
    $ip = $remote;
  }

  return $ip;
}

function visitorCount()
{
  $pdo = connectToDatabase();
  $sql = 'SELECT COUNT(*) FROM visitors';
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $count = $stmt->fetchColumn();
  closeConnection($pdo);
  return $count;
}

function generateRandomName()
{
  $animals = [
    'dog',
    'cat',
    'bird',
    'fish',
    'rabbit',
    'hamster',
    'turtle',
    'parrot',
    'snake',
    'lizard',
    'chicken',
    'cow',
    'pig',
    'goat',
    'sheep',
    'horse',
    'donkey',
    'duck',
    'goose',
    'turkey',
    'pigeon',
    'peacock',
    'ostrich',
    'flamingo',
    'penguin',
    'seagull',
    'eagle',
    'hawk',
    'owl',
    'crow',
    'raven',
    'swan',
    'crane',
    'heron',
    'stork',
    'pelican',
    'albatross',
    'kingfisher',
    'woodpecker',
    'hummingbird',
    'sparrow',
    'robin',
    'bluebird',
    'cardinal',
    'goldfinch',
    'canary',
    'finch',
    'budgie'
  ];

  $adjectives = [
    'happy',
    'sad',
    'angry',
    'sleepy',
    'hungry',
    'thirsty',
    'bored',
    'excited',
    'scared',
    'brave',
    'shy',
    'proud',
    'embarrassed',
    'confused',
    'surprised',
    'disappointed',
    'annoyed',
    'jealous',
    'guilty',
    'lonely',
    'sick',
    'tired',
    'nervous',
    'curious',
    'grateful',
    'hopeful',
    'pessimistic',
    'optimistic',
    'insecure',
    'confident',
    'indecisive',
    'determined',
    'stubborn',
    'sensible',
    'sensitive',
    'reliable',
    'responsible',
    'patient',
    'impulsive',
    'generous',
    'selfish',
    'polite',
    'rude',
    'honest',
    'dishonest',
    'loyal',
    'disloyal',
    'flexible',
    'sociable',
    'talkative',
    'quiet',
    'cheerful',
    'serious',
    'easygoing',
    'moody'
  ];

  $actions = [
    'running',
    'jumping',
    'swimming',
    'flying',
    'crawling',
    'walking',
    'sitting',
    'standing',
    'sleeping',
    'eating',
    'drinking',
    'singing',
    'dancing',
    'playing',
    'working',
    'reading',
    'writing',
    'drawing',
    'painting',
    'cooking',
    'baking',
    'cleaning',
    'washing',
    'shopping',
    'gardening',
    'fishing',
    'hunting',
    'travelling',
    'cycling',
    'driving',
    'riding',
    'flying',
    'sailing',
    'rowing',
    'climbing',
    'hiking',
    'skiing',
    'skating',
    'surfing',
    'diving',
    'swimming',
    'jogging',
    'exercising',
    'training',
    'studying',
    'learning',
    'teaching',
    'helping',
    'saving',
    'protecting',
    'rescuing',
    'caring',
    'sharing',
    'giving',
    'receiving',
    'borrowing',
    'lending',
    'buying',
    'selling',
    'renting',
    'leasing',
    'investing',
    'earning',
    'spending',
    'wasting',
    'losing',
    'finding',
    'winning',
    'losing',
    'quitting',
    'starting',
    'finishing',
    'continuing',
    'stopping',
    'pausing',
    'resuming',
    'repeating',
    'remembering',
    'forgetting',
    'regretting',
    'forgiving',
    'apologizing',
    'thanking',
    'praising',
    'complaining',
    'criticizing',
    'blaming',
    'praying',
    'meditating',
    'wishing',
    'hoping',
    'dreaming',
    'imagining',
    'creating',
    'destroying',
    'building',
    'repairing',
    'fixing',
    'breaking',
    'hurting',
    'healing',
    'curing',
    'treating',
    'diagnosing',
    'examining',
    'testing',
    'experimenting',
    'researching'
  ];
  $randomName = $adjectives[array_rand($adjectives)] . '-' . $actions[array_rand($actions)] . '-' . $animals[array_rand($animals)];
  return $randomName;
}

function getVisitor()
{
  $pdo = connectToDatabase();

  $isSecureRequest = false;
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $isSecureRequest = true;
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $isSecureRequest = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
  }

  $visitorCookieName = 'dl_visitor_id';
  $visitorCookieId = isset($_COOKIE[$visitorCookieName])
    ? (int) $_COOKIE[$visitorCookieName]
    : 0;

  if ($visitorCookieId > 0) {
    $sql = 'SELECT * FROM visitors WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $visitorCookieId]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($visitor) {
      // Keep latest network/client info up-to-date for the same visitor profile.
      $sql = 'UPDATE visitors SET ip = :ip, browser = :browser WHERE id = :id';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'ip' => getUserIP(),
        'browser' => $_SERVER['HTTP_USER_AGENT'],
        'id' => $visitorCookieId
      ]);

      $visitor['ip'] = getUserIP();
      $visitor['browser'] = $_SERVER['HTTP_USER_AGENT'];
      closeConnection($pdo);
      return $visitor;
    }
  }

  // When no valid visitor cookie exists, create a dedicated visitor profile
  // instead of matching by IP/browser to avoid merging different people.
  $name = generateRandomName();
  $sql = 'INSERT INTO visitors (name, ip, browser, created_at) VALUES (:name, :ip, :browser, :created_at)';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['name' => $name, 'ip' => getUserIP(), 'browser' => $_SERVER['HTTP_USER_AGENT'], 'created_at' => date('Y-m-d H:i:s')]);
  $visitor = [
    'id' => $pdo->lastInsertId(),
    'name' => $name,
    'ip' => getUserIP(),
    'browser' => $_SERVER['HTTP_USER_AGENT']
  ];

  setcookie($visitorCookieName, (string) $visitor['id'], [
    'expires' => time() + (60 * 60 * 24 * 365),
    'path' => '/',
    'secure' => $isSecureRequest,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);

  closeConnection($pdo);
  return $visitor;
}

function isAccessLinkSession()
{
  if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    return false;
  }

  $isLegacyTempUser = isset($_SESSION['user']['id']) && $_SESSION['user']['id'] === 'tempUser';
  $hasLegacyToken = !empty($_SESSION['user']['oneTimeToken']);
  $hasAccessLinkToken = !empty($_SESSION['user']['accessLinkToken']);

  return $isLegacyTempUser || $hasLegacyToken || $hasAccessLinkToken;
}

function isOneTimeReadOnlyUser()
{
  if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    return false;
  }

  $isOneTimeSession = isAccessLinkSession();

  $role = $_SESSION['user']['role'] ?? '';
  return $isOneTimeSession && $role === 'viewer';
}

function enforceOneTimeReadOnlyAccess($requestUri, $method = 'GET')
{
  if (!isOneTimeReadOnlyUser()) {
    return;
  }

  $method = strtoupper((string) $method);

  $alwaysAllowedPaths = [
    '/home',
    '/login',
    '/googleLogin',
    '/logout'
  ];

  if (in_array($requestUri, $alwaysAllowedPaths, true)) {
    return;
  }

  $blockedPaths = [
    '/register',
    '/createGroup',
    '/editLink',
    '/editVisitor',
    '/updateOrder',
    '/deleteLink',
    '/duplicateLink',
    '/editUser',
    '/editGroup',
    '/createLink',
    '/switchLinkStatus',
    '/restore',
    '/createOneTime',
    '/deactivateOneTime',
    '/deactivateAllOneTimes',
    '/uploadImage',
    '/multiSelectDropdown',
    '/deleteImage',
    '/deleteOneTime',
    '/uploadCustom404',
    '/deleteDuplicates',
    '/deleteCustom404',
    '/uploadCustom',
    '/favourite',
    '/darkMode',
    '/viewMode',
    '/multiSelect',
    '/downloadCustom',
    '/css',
    '/profile/identity',
    '/profile/password',
    '/profile/photo',
    '/profile/theme'
  ];

  $readOnlyPostPaths = [
    '/filter',
    '/fetchTagsGroups',
    '/fetchRelatedTags',
    '/getGroups',
    '/getTags',
    '/getTagGroupModal',
    '/getSortModal',
    '/linkContainer',
    '/visitorContainer',
    '/groupContainer'
  ];

  $isBlockedPath = in_array($requestUri, $blockedPaths, true);
  $isWriteMethod = $method !== 'GET';
  $isAllowedReadPost = $isWriteMethod && in_array($requestUri, $readOnlyPostPaths, true);

  if ($isAllowedReadPost) {
    return;
  }

  if (!$isBlockedPath && !$isWriteMethod) {
    return;
  }

  $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
  $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
  $isJsonRequest = strpos($acceptHeader, 'application/json') !== false
    || $requestedWith === 'xmlhttprequest'
    || strpos($contentType, 'application/json') !== false;

  if ($isJsonRequest) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'success' => false,
      'message' => 'Read-only access-link viewer session: changes are not allowed.'
    ]);
    exit;
  }

  if ($isWriteMethod) {
    $_SESSION['ui_notice'] = 'Read-only access-link viewer session: changes are not allowed.';
    $_SESSION['ui_notice_type'] = 'error';

    $redirectPath = '/home';
    if ($requestUri === '/profile' || strpos($requestUri, '/profile/') === 0) {
      $redirectPath = '/profile';
    }

    header('Location: ' . $redirectPath);
    exit;
  }

  $_SESSION['error'] = 'Read-only access-link viewer session: changes are not allowed.';
  $_SESSION['errorType'] = 'login';
  header('Location: /home');
  exit;
}
