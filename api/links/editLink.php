<?php
function editLink($postData)
{
  if (!checkAdmin()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  $id = $postData['id'];
  $title = $postData['title'];
  $url = $postData['url'];

  // Server-side URL validation: must be valid URL and not internal/embedded links
  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    return ['success' => false, 'message' => 'Invalid URL format'];
  }

  // Check for internal/embedded links
  $parsed_url = parse_url($url);
  if (!isset($parsed_url['host']) || empty($parsed_url['host'])) {
    return ['success' => false, 'message' => 'Invalid URL: missing host'];
  }

  $scheme = strtolower((string) ($parsed_url['scheme'] ?? ''));
  if ($scheme !== 'http' && $scheme !== 'https') {
    return ['success' => false, 'message' => 'Invalid URL: only http/https links are allowed'];
  }

  $shortlink = strlen($postData['shortlink']) > 0 ? $postData['shortlink'] : generateRandomString(6);
  $tagsRaw = $postData['tags'] ?? null;
  $tags = is_string($tagsRaw) ? json_decode($tagsRaw, true) : null;
  $shouldUpdateTags = is_array($tags);

  $groupsRaw = $postData['groups'] ?? null;
  $groups = is_string($groupsRaw) ? json_decode($groupsRaw, true) : null;
  $shouldUpdateGroups = is_array($groups);
  $status = $postData['status'] === 'on' ? 1 : 0;

  $shortlink = trim((string) $shortlink);
  if (str_contains($shortlink, ' ')) {
    $shortlink = str_replace(' ', '-', $shortlink);
  }

  // Handle optional expires_at
  $expires_at = null;
  if (!empty($postData['expires_at'])) {
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
    // ignore
  }

  $sql = "SELECT * FROM links WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $existingLink = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$existingLink) {
    closeConnection($pdo);
    return ['success' => false, 'message' => 'Link not found'];
  }

  $oldShortlink = (string) ($existingLink['shortlink'] ?? '');

  $sql = "SELECT id FROM links WHERE REPLACE(LOWER(shortlink), '/', '') = REPLACE(LOWER(:shortlink), '/', '') LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['shortlink' => $shortlink]);
  $link = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($link && (int) $link['id'] !== (int) $id) {
    closeConnection($pdo);
    return ['success' => false, 'message' => 'Shortlink already exists'];
  }

  $sql = "SELECT link_aliases.id, link_aliases.link_id
      FROM link_aliases
      INNER JOIN links ON links.id = link_aliases.link_id
      WHERE REPLACE(LOWER(link_aliases.alias), '/', '') = REPLACE(LOWER(:alias), '/', '')
      LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['alias' => $shortlink]);
  $reservedAlias = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($reservedAlias && (int) $reservedAlias['link_id'] !== (int) $id) {
    closeConnection($pdo);
    return ['success' => false, 'message' => 'Shortlink already exists'];
  }

  // If the desired shortlink already exists as an alias for this same link,
  // remove that alias so it can be used again as primary shortlink.
  if ($reservedAlias && (int) $reservedAlias['link_id'] === (int) $id) {
    $sql = 'DELETE FROM link_aliases WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $reservedAlias['id']]);
  }

  $sql = "SELECT * FROM links WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $title]);
  $link = $stmt->fetch();

  if ($link && intval($link['id']) !== intval($id) || empty($title) && intval($link['id']) !== intval($id)) {
    closeConnection($pdo);
    if (empty($title)) {
      return ['success' => false, 'message' => 'Title cannot be empty'];
    } else {
      return ['success' => false, 'message' => 'Title already exists'];
    }
  }

  // Update the link details including expires_at
  $sql = "UPDATE links SET title = :title, url = :url, shortlink = :shortlink, modifier = :modifier, modified_at = :modified_at, status = :status, expires_at = :expires_at WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'title' => $title,
    'url' => $url,
    'shortlink' => $shortlink,
    'id' => $id,
    'modifier' => $_SESSION['user']['id'],
    'modified_at' => date('Y-m-d H:i:s'),
    'status' => $status,
    'expires_at' => $expires_at
  ]);

  $aliasCreated = false;
  $oldShortlinkNormalized = str_replace('/', '', strtolower(trim((string) $oldShortlink)));
  $newShortlinkNormalized = str_replace('/', '', strtolower(trim((string) $shortlink)));

  if ($oldShortlinkNormalized !== '' && $oldShortlinkNormalized !== $newShortlinkNormalized) {
    $sql = "SELECT id
            FROM links
            WHERE REPLACE(LOWER(shortlink), '/', '') = REPLACE(LOWER(:shortlink), '/', '')
              AND id != :id
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'shortlink' => $oldShortlink,
      'id' => $id
    ]);
    $conflictingPrimary = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "SELECT link_aliases.id, link_aliases.link_id
          FROM link_aliases
          INNER JOIN links ON links.id = link_aliases.link_id
          WHERE REPLACE(LOWER(link_aliases.alias), '/', '') = REPLACE(LOWER(:alias), '/', '')
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['alias' => $oldShortlink]);
    $conflictingAlias = $stmt->fetch(PDO::FETCH_ASSOC);

    $aliasUsedByOtherLink = $conflictingAlias && (int) ($conflictingAlias['link_id'] ?? 0) !== (int) $id;

    if (!$conflictingPrimary && !$aliasUsedByOtherLink) {
      $sql = 'INSERT OR IGNORE INTO link_aliases (link_id, alias, created_at, created_by) VALUES (:link_id, :alias, :created_at, :created_by)';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'link_id' => $id,
        'alias' => $oldShortlink,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $_SESSION['user']['id'] ?? null
      ]);
      $aliasCreated = $stmt->rowCount() > 0;
    }
  }

  if ($shouldUpdateTags) {
    // Delete existing tags for the link
    $sql = "DELETE FROM link_tags WHERE link_id = :linkId";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['linkId' => $id]);

    // Insert new tags
    foreach ($tags as $tag) {
      // Check if the tag already exists in the tags table
      $sql = "SELECT id FROM tags WHERE LOWER(title) = LOWER(:tag)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['tag' => $tag]);
      $tagId = $stmt->fetchColumn();

      if (!$tagId) {
        // Insert the new tag into the tags table
        $sql = "INSERT INTO tags (title) VALUES (:tag)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tag' => ucfirst($tag)]);
        $tagId = $pdo->lastInsertId();
      }

      $sql = 'SELECT * FROM link_tags WHERE link_id = :linkId AND tag_id = :tagId';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'linkId' => $id,
        'tagId' => $tagId
      ]);
      $tagLink = $stmt->fetch();

      if ($tagLink) {
        continue;
      }

      // Insert the tag into the link_tags table
      $sql = "INSERT INTO link_tags (link_id, tag_id) VALUES (:linkId, :tagId)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['linkId' => $id, 'tagId' => $tagId]);
    }
  }

  if ($shouldUpdateGroups) {
    // Delete existing groups for the link
    $sql = "DELETE FROM link_groups WHERE link_id = :linkId";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['linkId' => $id]);

    // Insert new groups
    foreach ($groups as $group) {
      // Check if the group already exists in the groups table
      $sql = "SELECT id FROM groups WHERE LOWER(title) = LOWER(:group)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['group' => $group]);
      $groupId = $stmt->fetchColumn();

      if (!$groupId) {
        // Insert the new group into the groups table
        $sql = "INSERT INTO groups (title) VALUES (:group)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['group' => ucfirst($group)]);
        $groupId = $pdo->lastInsertId();
      }

      $sql = 'SELECT * FROM link_groups WHERE link_id = :linkId AND group_id = :groupId';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'linkId' => $id,
        'groupId' => $groupId
      ]);
      $linkGroup = $stmt->fetch();

      if ($linkGroup) {
        continue;
      }

      // Insert the group into the link_groups table
      $sql = "INSERT INTO link_groups (link_id, group_id) VALUES (:linkId, :groupId)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['linkId' => $id, 'groupId' => $groupId]);
    }
  }

  closeConnection($pdo);
  return [
    'success' => true,
    'shortlink_changed' => $oldShortlink !== $shortlink,
    'alias_created' => $aliasCreated,
  ];
}
