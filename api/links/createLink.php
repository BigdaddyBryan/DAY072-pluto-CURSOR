<?php

function createLinkExpectsJsonRequest(): bool
{
  $xRequestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  if ($xRequestedWith === 'xmlhttprequest') {
    return true;
  }

  $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
  return strpos($acceptHeader, 'application/json') !== false;
}

function createLinkErrorResponse(string $message, bool $expectsJson)
{
  if ($expectsJson) {
    return [
      'success' => false,
      'message' => $message,
    ];
  }

  header('Location: /');
  return null;
}

function createLink($postData)
{
  $expectsJson = createLinkExpectsJsonRequest();

  if (!checkAdmin()) {
    if ($expectsJson) {
      return [
        'success' => false,
        'message' => 'Unauthorized',
      ];
    }

    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  $title = trim((string) ($postData['title'] ?? ''));
  $url = trim((string) ($postData['url'] ?? ''));

  // Server-side URL validation: must be valid URL and not internal/embedded links
  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    return createLinkErrorResponse('Invalid URL format', $expectsJson);
  }

  // Check for internal/embedded links
  $parsed_url = parse_url($url);
  if (!isset($parsed_url['host']) || empty($parsed_url['host'])) {
    return createLinkErrorResponse('Invalid URL: missing host', $expectsJson);
  }

  $scheme = strtolower((string) ($parsed_url['scheme'] ?? ''));
  if ($scheme !== 'http' && $scheme !== 'https') {
    return createLinkErrorResponse('Invalid URL: only http/https links are allowed', $expectsJson);
  }

  $shortlink = trim((string) ($postData['shortlink'] ?? ''));
  $allowAliasOverwrite = isset($postData['overwrite_alias']) && (string) $postData['overwrite_alias'] === '1';
  if (strlen($shortlink) === 0) {
    do {
      $shortlink = generateRandomString();
    } while (getLinkByShortlink($shortlink));
  } else if (str_contains($shortlink, ' ')) {
    $shortlink = str_replace(' ', '-', $shortlink);
  }
  $tags = json_decode($postData['tags'], true);
  $groups = json_decode($postData['groups'], true);
  $status = $postData['status'] === 'on' ? 1 : 0;

  // Handle optional expires_at
  $expires_at = null;
  if (!empty($postData['expires_at'])) {
    // datetime-local input may be like 2025-08-26T14:30
    $ts = strtotime($postData['expires_at']);
    if ($ts !== false) {
      $expires_at = date('Y-m-d H:i:s', $ts);
    }
  }

  $pdo = connectToDatabase();
  ensureLinkAliasesTable($pdo);

  // Ensure the expires_at column exists (SQLite)
  try {
    $cols = $pdo->query("PRAGMA table_info(links)")->fetchAll(PDO::FETCH_ASSOC);
    $hasExpires = false;
    foreach ($cols as $c) {
      if ($c['name'] === 'expires_at') {
        $hasExpires = true;
        break;
      }
    }
    if (!$hasExpires) {
      $pdo->exec("ALTER TABLE links ADD COLUMN expires_at TEXT NULL");
    }
  } catch (Exception $e) {
    // ignore and proceed
  }

  $sql = "SELECT id FROM links WHERE REPLACE(LOWER(shortlink), '/', '') = REPLACE(LOWER(:shortlink), '/', '') LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['shortlink' => $shortlink]);
  $link = $stmt->fetch();

  if ($link) {
    closeConnection($pdo);
    return createLinkErrorResponse('Shortlink already exists', $expectsJson);
  }

  $sql = "SELECT link_aliases.id, link_aliases.link_id
      FROM link_aliases
      INNER JOIN links ON links.id = link_aliases.link_id
      WHERE REPLACE(LOWER(link_aliases.alias), '/', '') = REPLACE(LOWER(:alias), '/', '')
          LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['alias' => $shortlink]);
  $alias = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($alias) {
    if (!$allowAliasOverwrite) {
      closeConnection($pdo);
      return createLinkErrorResponse('Alias already exists. Enable alias overwrite to use this shortlink', $expectsJson);
    }
  }

  $sql = "SELECT * FROM links WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $title]);
  $link = $stmt->fetch();

  if ($link || empty($title)) {
    closeConnection($pdo);
    return createLinkErrorResponse(
      empty($title) ? 'Title cannot be empty' : 'Title already exists',
      $expectsJson
    );
  }

  // Insert with optional expires_at
  $sql = "INSERT INTO links (title, shortlink, url, creator, created_at, modifier, modified_at, status, expires_at) VALUES (:title, :shortlink, :url, :creator, :created_at, :creator, :created_at, :status, :expires_at)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'title' => $title,
    'shortlink' => $shortlink,
    'url' => $url,
    'creator' => $_SESSION['user']['id'],
    'created_at' => date('Y-m-d H:i:s'),
    'status' => $status,
    'expires_at' => $expires_at
  ]);

  $linkId = $pdo->lastInsertId();

  if ($alias) {
    // Remove all normalized alias duplicates for the overwritten value.
    $sql = "DELETE FROM link_aliases
            WHERE REPLACE(LOWER(alias), '/', '') = REPLACE(LOWER(:alias), '/', '')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['alias' => $shortlink]);
  }

  if (empty($tags)) {
  } else {
    for ($i = 0; $i < count($tags); $i++) {
      $tag = $tags[$i];
      $sql = "SELECT * FROM tags WHERE LOWER(title) = LOWER(:tag)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['tag' => $tag]);
      $tagData = $stmt->fetch();

      $tagId = null;

      if ($tagData) {
        $tagId = $tagData['id'];
      } else {
        continue;
      }

      $sql = 'SELECT * FROM link_tags WHERE link_id = :linkId AND tag_id = :tagId';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'linkId' => $linkId,
        'tagId' => $tagId
      ]);
      $tagLink = $stmt->fetch();

      if ($tagLink) {
        continue;
      }

      $sql = "INSERT INTO link_tags (link_id, tag_id) VALUES (:linkId, :tagId)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['linkId' => $linkId, 'tagId' => $tagId]);
    }
  }

  if (empty($groups)) {
  } else {
    for ($i = 0; $i < count($groups); $i++) {
      $group = $groups[$i];

      $sql = "SELECT * FROM groups WHERE LOWER(title) = LOWER(:group)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['group' => $group]);
      $groupData = $stmt->fetch();
      $groupId = null;

      if ($groupData) {
        $groupId = $groupData['id'];
      } else {
        $sql = "INSERT INTO groups (title) VALUES (:group)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['group' => ucfirst($group)]);
        $groupId = $pdo->lastInsertId();
      }

      $sql = 'SELECT * FROM link_groups WHERE link_id = :linkId AND group_id = :groupId';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'linkId' => $linkId,
        'groupId' => $groupId
      ]);
      $linkGroup = $stmt->fetch();

      if ($linkGroup) {
        continue;
      }

      $sql = "INSERT INTO link_groups (link_id, group_id) VALUES (:linkId, :groupId)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['linkId' => $linkId, 'groupId' => $groupId]);
    }
  }

  closeConnection($pdo);

  if ($expectsJson) {
    return [
      'success' => true,
      'id' => (int) $linkId,
      'message' => 'Link created successfully',
    ];
  }

  header('Location: /');
  return null;
}
