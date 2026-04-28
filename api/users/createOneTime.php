<?php

function createOneTime($domain = null)
{
  header('Content-Type: application/json; charset=utf-8');

  if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
  }

  try {
    $rawBody = file_get_contents('php://input');
    $jsonData = json_decode($rawBody, true);
    if (!is_array($jsonData)) {
      $jsonData = [];
    }

    $expirationDays = 7;
    if (isset($jsonData['expiration_days'])) {
      $expirationDays = (int) $jsonData['expiration_days'];
    } else if (isset($_POST['expiration_days'])) {
      $expirationDays = (int) $_POST['expiration_days'];
    }

    if ($expirationDays < 1 || $expirationDays > 365) {
      http_response_code(422);
      echo json_encode([
        'success' => false,
        'message' => 'Expiration days must be between 1 and 365'
      ]);
      exit;
    }

    $allowedRoles = ['viewer', 'user', 'admin', 'superadmin'];
    $sessionRole = 'viewer';
    if (isset($jsonData['role'])) {
      $sessionRole = trim((string) $jsonData['role']);
    } else if (isset($_POST['role'])) {
      $sessionRole = trim((string) $_POST['role']);
    }

    if (!in_array($sessionRole, $allowedRoles, true)) {
      http_response_code(422);
      echo json_encode([
        'success' => false,
        'message' => 'Role must be one of: viewer, user, admin, superadmin'
      ]);
      exit;
    }

    $creatorRole = $_SESSION['user']['role'] ?? '';
    if ($sessionRole === 'superadmin' && $creatorRole !== 'superadmin') {
      http_response_code(403);
      echo json_encode([
        'success' => false,
        'message' => 'Only superadmin can create superadmin login links'
      ]);
      exit;
    }

    $sessionMinutes = 60;
    if (isset($jsonData['session_minutes'])) {
      $sessionMinutes = (int) $jsonData['session_minutes'];
    } else if (isset($_POST['session_minutes'])) {
      $sessionMinutes = (int) $_POST['session_minutes'];
    }

    if ($sessionMinutes < 5 || $sessionMinutes > 10080) {
      http_response_code(422);
      echo json_encode([
        'success' => false,
        'message' => 'Session duration must be between 5 and 10080 minutes'
      ]);
      exit;
    }

    $validFromRaw = null;
    if (array_key_exists('valid_from', $jsonData)) {
      $validFromRaw = $jsonData['valid_from'];
    } else if (array_key_exists('valid_from', $_POST)) {
      $validFromRaw = $_POST['valid_from'];
    }

    $validUntilRaw = null;
    if (array_key_exists('valid_until', $jsonData)) {
      $validUntilRaw = $jsonData['valid_until'];
    } else if (array_key_exists('valid_until', $_POST)) {
      $validUntilRaw = $_POST['valid_until'];
    }

    $validFromTs = time();
    if ($validFromRaw !== null && trim((string) $validFromRaw) !== '') {
      $parsedValidFrom = strtotime((string) $validFromRaw);
      if ($parsedValidFrom === false) {
        http_response_code(422);
        echo json_encode([
          'success' => false,
          'message' => 'Invalid valid_from datetime format'
        ]);
        exit;
      }
      $validFromTs = $parsedValidFrom;
    }

    $validUntilTs = $validFromTs + ($expirationDays * 24 * 60 * 60);
    if ($validUntilRaw !== null && trim((string) $validUntilRaw) !== '') {
      $parsedValidUntil = strtotime((string) $validUntilRaw);
      if ($parsedValidUntil === false) {
        http_response_code(422);
        echo json_encode([
          'success' => false,
          'message' => 'Invalid valid_until datetime format'
        ]);
        exit;
      }
      $validUntilTs = $parsedValidUntil;
    }

    if ($validUntilTs <= $validFromTs) {
      http_response_code(422);
      echo json_encode([
        'success' => false,
        'message' => 'valid_until must be later than valid_from'
      ]);
      exit;
    }

    $computedExpirationDays = (int) ceil(($validUntilTs - $validFromTs) / (24 * 60 * 60));

    $scopeGroupIds = [];
    if (isset($jsonData['group_ids']) && is_array($jsonData['group_ids'])) {
      $scopeGroupIds = $jsonData['group_ids'];
    } else if (isset($_POST['group_ids']) && is_array($_POST['group_ids'])) {
      $scopeGroupIds = $_POST['group_ids'];
    } else {
      $legacyGroupId = null;
      if (array_key_exists('group_id', $jsonData)) {
        $legacyGroupId = $jsonData['group_id'];
      } else if (array_key_exists('group_id', $_POST)) {
        $legacyGroupId = $_POST['group_id'];
      }

      if ($legacyGroupId !== null && $legacyGroupId !== '' && $legacyGroupId !== 'all' && $legacyGroupId !== '0' && $legacyGroupId !== 0) {
        $scopeGroupIds = [$legacyGroupId];
      }
    }

    $scopeGroupIds = array_values(array_unique(array_filter(array_map('intval', $scopeGroupIds), function ($id) {
      return $id > 0;
    })));

    $host = $_SERVER['HTTP_HOST'] ?? $domain ?? null;
    if (!$host) {
      throw new Exception('Unable to determine host');
    }

    $hostWithoutPort = preg_replace('/:\\d+$/', '', (string) $host);
    $portSuffix = '';
    if (preg_match('/:\\d+$/', (string) $host, $portMatch)) {
      $portSuffix = $portMatch[0];
    }

    $normalizedHost = trim($hostWithoutPort, '[]');
    $isIpHost = filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false;
    $isLocalHost = in_array(strtolower($normalizedHost), ['localhost', '127.0.0.1', '::1'], true);
    $isLocalDomain = $isIpHost || $isLocalHost;

    $canonicalHost = $hostWithoutPort;
    if (!$isLocalDomain && stripos($canonicalHost, 'www.') !== 0) {
      $canonicalHost = 'www.' . $canonicalHost;
    }

    $protocol = $isLocalDomain ? 'http' : 'https';
    $requestedDomain = $protocol . '://' . $canonicalHost . $portSuffix;

    $filePath = __DIR__ . '/../../public/json/allowedTokens.json';

    if (!file_exists($filePath)) {
      throw new Exception('Tokens file not found');
    }

    $fileContents = file_get_contents($filePath);
    $allowedTokens = json_decode($fileContents, true);

    if (!is_array($allowedTokens) || !isset($allowedTokens['tokens'])) {
      $allowedTokens = ['tokens' => []];
    }

    $scopeGroups = [];
    if (!empty($scopeGroupIds)) {
      $pdo = connectToDatabase();
      $placeholders = implode(',', array_fill(0, count($scopeGroupIds), '?'));
      $scopeStmt = $pdo->prepare("SELECT id, title FROM groups WHERE id IN ($placeholders)");
      $scopeStmt->execute($scopeGroupIds);
      $scopeRows = $scopeStmt->fetchAll(PDO::FETCH_ASSOC);
      closeConnection($pdo);

      $scopeMap = [];
      foreach ($scopeRows as $scopeRow) {
        $scopeMap[(int) $scopeRow['id']] = $scopeRow;
      }

      foreach ($scopeGroupIds as $scopeGroupId) {
        if (!isset($scopeMap[$scopeGroupId])) {
          http_response_code(422);
          echo json_encode([
            'success' => false,
            'message' => 'One or more selected groups do not exist'
          ]);
          exit;
        }

        $scopeGroups[] = [
          'id' => (int) $scopeMap[$scopeGroupId]['id'],
          'title' => $scopeMap[$scopeGroupId]['title']
        ];
      }
    }

    $legacyScopeGroupId = null;
    $legacyScopeGroupTitle = null;
    if (count($scopeGroups) === 1) {
      $legacyScopeGroupId = (int) $scopeGroups[0]['id'];
      $legacyScopeGroupTitle = $scopeGroups[0]['title'];
    }

    $token = bin2hex(random_bytes(16));
    $expirationTime = $validUntilTs;

    $allowedTokens['tokens'][$token] = [
      'token' => $token,
      'created' => time(),
      'created_by' => $_SESSION['user']['email'] ?? 'admin',
      'role' => $sessionRole,
      'session_duration_minutes' => $sessionMinutes,
      'login_valid_from' => $validFromTs,
      'login_valid_until' => $validUntilTs,
      'scope_groups' => $scopeGroups,
      'scope_group_id' => $legacyScopeGroupId,
      'scope_group_title' => $legacyScopeGroupTitle,
      'clicked' => false,
      'clicked_on' => null,
      'last_clicked_on' => null,
      'click_count' => 0,
      'used_on' => null,
      'used' => false,
      'expiration' => $expirationTime,
      'expiration_days' => $computedExpirationDays,
      'ip' => null,
      'deactivated' => false,
      'deactivated_on' => null
    ];

    if (!isset($allowedTokens['history_events']) || !is_array($allowedTokens['history_events'])) {
      $allowedTokens['history_events'] = [];
    }

    $allowedTokens['history_events'][] = [
      'event' => 'created',
      'token' => $token,
      'at' => time(),
      'actor' => (string) ($_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown'),
      'role' => $sessionRole,
      'scope' => array_map(function ($group) {
        return (string) ($group['title'] ?? '');
      }, $scopeGroups),
    ];

    $written = file_put_contents($filePath, json_encode($allowedTokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    if ($written === false) {
      throw new Exception('Failed to save token');
    }

    $loginUrl = rtrim($requestedDomain, '/') . '/access-link-login?token=' . $token;

    echo json_encode([
      'success' => true,
      'message' => 'Access link created successfully',
      'token' => $token,
      'url' => $loginUrl,
      'scope_groups' => $scopeGroups,
      'scope_group_id' => $legacyScopeGroupId,
      'scope_group_title' => $legacyScopeGroupTitle,
      'role' => $sessionRole,
      'session_duration_minutes' => $sessionMinutes,
      'valid_from' => date('Y-m-d H:i:s', $validFromTs),
      'valid_until' => date('Y-m-d H:i:s', $validUntilTs),
      'expiration_days' => $computedExpirationDays,
      'expiration' => date('Y-m-d H:i:s', $expirationTime)
    ]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => 'Error: ' . $e->getMessage()
    ]);
  }

  exit;
}
