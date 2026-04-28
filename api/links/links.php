<?php

// Get specific tag by link ID
function fetchTags($link_id)
{
  $pdo = connectToDatabase();

  $sql = "SELECT tags.* FROM link_tags LEFT JOIN tags ON link_tags.tag_id = tags.id WHERE link_tags.link_id = :link_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['link_id' => $link_id]);
  $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

  closeConnection($pdo);
  return $tags;
}

// Get specific group by link ID
function fetchGroups($link_id)
{
  $pdo = connectToDatabase();

  $sql = "SELECT groups.* FROM link_groups LEFT JOIN groups ON link_groups.group_id = groups.id WHERE link_groups.link_id = :link_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['link_id' => $link_id]);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  closeConnection($pdo);
  return $groups;
}

function generateRandomString($length = 6)
{
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $charactersLength = strlen($characters);
  $randomString = '';

  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[random_int(0, $charactersLength - 1)];
  }

  return $randomString;
}

// Get all tags
function getAllTags($page)

{
  $pdo = connectToDatabase();
  try {
    if ($page === 'users') {
      $sql = "SELECT DISTINCT tags.* FROM tags INNER JOIN visitors_tags ON tags.id = visitors_tags.tag_id ORDER BY tags.title";
    } else if ($page === 'links') {
      $sql = "SELECT DISTINCT tags.* FROM tags INNER JOIN link_tags ON tags.id = link_tags.tag_id ORDER BY tags.title";
    } else if ($page === 'visitors') {
      $sql = "SELECT DISTINCT tags.* FROM tags INNER JOIN visitors_tags ON tags.id = visitors_tags.tag_id ORDER BY tags.title";
    }
    $stmt = $pdo->query($sql);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the tags data as JSON
  } catch (PDOException $e) {
    // If there is a database error, return an error response
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get all tags: ' . $e->getMessage()]);
  }
  closeConnection($pdo);
  return $tags;
}

// Get all groups
function getAllGroups($page)
{

  checkUser();

  $pdo = connectToDatabase();
  try {
    if ($page === 'users') {
      $sql = "SELECT DISTINCT groups.* FROM groups INNER JOIN visitors_groups ON groups.id = visitors_groups.group_id ORDER BY groups.title";
    } else if ($page === 'links') {
      $sql = "SELECT DISTINCT groups.* FROM groups INNER JOIN link_groups ON groups.id = link_groups.group_id ORDER BY groups.title";
    } else if ($page === 'visitors') {
      $sql = "SELECT DISTINCT groups.* FROM groups INNER JOIN visitors_groups ON groups.id = visitors_groups.group_id ORDER BY groups.title";
    } else if ($page === 'all') {
      $sql = "SELECT groups.*, COUNT(link_groups.link_id) AS link_count
              FROM groups
              LEFT JOIN link_groups ON groups.id = link_groups.group_id
              GROUP BY groups.id
              ORDER BY groups.modified_at ASC
              LIMIT " . (int) $_SESSION['user']['limit'];
    }
    $stmt = $pdo->query($sql);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the groups data as JSON
  } catch (PDOException $e) {
    // If there is a database error, return an error response
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get all groups: ' . $e->getMessage()]);
  }
  closeConnection($pdo);
  return $groups;
}

function getRecentlyDeletedTagsGroups()
{
  if (!checkAdmin()) {
    return ['tags' => [], 'groups' => []];
  }

  $pdo = connectToDatabase();
  $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));

  $sql = "SELECT id, table_id, title, created_at FROM archive WHERE table_name = :table_name AND created_at >= :cutoff ORDER BY created_at DESC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute(['table_name' => 'tags', 'cutoff' => $cutoff]);
  $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare($sql);
  $stmt->execute(['table_name' => 'groups', 'cutoff' => $cutoff]);
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  closeConnection($pdo);
  return ['tags' => $tags, 'groups' => $groups];
}

function getGroupById($id)
{

  checkUser();

  $pdo = connectToDatabase();
  $sql = "SELECT * FROM groups WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $group = $stmt->fetch(PDO::FETCH_ASSOC);
  closeConnection($pdo);
  return $group;
}

// Get specific Visit by link ID
function fetchVisits($link_id)
{
  $pdo = connectToDatabase();

  $sql = "SELECT * FROM visits WHERE link_id = :link_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['link_id' => $link_id]);
  $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $today = date('Y-m-d');

  $todayVisits = [];
  foreach ($visits as $visit) {
    if (date('Y-m-d', strtotime($visit['date'])) == date('Y-m-d', strtotime($today))) {
      array_push($todayVisits, $visit);
    }
  }

  closeConnection($pdo);
  return ['visits' => $visits, 'todayVisits' => $todayVisits];
}

// Get link by Shortlink
function getLinkByShortlink($shortlink)
{
  $pdo = connectToDatabase();

  ensureLinkAliasesTable($pdo);

  $sql = "SELECT * FROM links WHERE REPLACE(shortlink, '/', '') = REPLACE(:shortlink, '/', '')";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['shortlink' => $shortlink]);
  $link = $stmt->fetch();

  if (!$link) {
    $sql = "SELECT links.*
            FROM link_aliases
            INNER JOIN links ON links.id = link_aliases.link_id
            WHERE REPLACE(link_aliases.alias, '/', '') = REPLACE(:shortlink, '/', '')
            ORDER BY link_aliases.created_at DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['shortlink' => $shortlink]);
    $link = $stmt->fetch();
  }

  closeConnection($pdo);
  return $link;
}

function ensureLinkAliasesTable($pdo)
{
  static $ensured = false;

  if ($ensured) {
    return;
  }

  try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS link_aliases (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      link_id INTEGER NOT NULL,
      alias TEXT NOT NULL UNIQUE,
      created_at TEXT,
      created_by INTEGER
    )');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_aliases_alias ON link_aliases(alias)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_link_aliases_link_id ON link_aliases(link_id)');

    // Cleanup legacy/orphan alias rows that can break overwrite checks.
    $pdo->exec('DELETE FROM link_aliases WHERE link_id NOT IN (SELECT id FROM links)');

    // Aliases should never equal any active primary shortlink.
    $pdo->exec("DELETE FROM link_aliases
               WHERE EXISTS (
                 SELECT 1 FROM links
                 WHERE REPLACE(LOWER(links.shortlink), '/', '') = REPLACE(LOWER(link_aliases.alias), '/', '')
               )");

    // Deduplicate normalized alias values (case/slash-insensitive), keep newest row.
    $stmt = $pdo->query('SELECT id, alias FROM link_aliases ORDER BY id DESC');
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $seen = [];
    foreach ($rows as $row) {
      $aliasValue = trim((string) ($row['alias'] ?? ''));
      $normalizedAlias = str_replace('/', '', strtolower($aliasValue));
      if ($normalizedAlias === '') {
        $deleteStmt = $pdo->prepare('DELETE FROM link_aliases WHERE id = :id');
        $deleteStmt->execute(['id' => (int) ($row['id'] ?? 0)]);
        continue;
      }

      if (isset($seen[$normalizedAlias])) {
        $deleteStmt = $pdo->prepare('DELETE FROM link_aliases WHERE id = :id');
        $deleteStmt->execute(['id' => (int) ($row['id'] ?? 0)]);
        continue;
      }

      $seen[$normalizedAlias] = true;
    }
  } catch (Throwable $e) {
    // Keep shortlink flow functional if alias migration fails.
  }

  $ensured = true;
}

function getLinkAliasHistory($linkId)
{
  $normalizedId = (int) $linkId;
  if ($normalizedId <= 0) {
    return [];
  }

  $pdo = connectToDatabase();
  ensureLinkAliasesTable($pdo);

  $sql = 'SELECT link_aliases.id,
                 link_aliases.alias,
                 link_aliases.created_at,
                 link_aliases.created_by,
                 users.email AS created_by_email
          FROM link_aliases
          LEFT JOIN users ON users.id = link_aliases.created_by
          WHERE link_aliases.link_id = :link_id
          ORDER BY datetime(link_aliases.created_at) DESC, link_aliases.id DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['link_id' => $normalizedId]);
  $aliases = $stmt->fetchAll(PDO::FETCH_ASSOC);

  closeConnection($pdo);
  return is_array($aliases) ? $aliases : [];
}

function restoreLinkAlias($payload)
{
  if (!checkAdmin()) {
    return ['success' => false, 'message' => 'Unauthorized'];
  }

  $linkId = isset($payload['link_id']) ? (int) $payload['link_id'] : 0;
  $alias = trim((string) ($payload['alias'] ?? ''));

  if ($linkId <= 0 || $alias === '') {
    return ['success' => false, 'message' => 'Invalid alias restore request'];
  }

  $pdo = connectToDatabase();
  ensureLinkAliasesTable($pdo);

  try {
    $pdo->beginTransaction();

    $sql = 'SELECT id, shortlink FROM links WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $linkId]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link) {
      $pdo->rollBack();
      closeConnection($pdo);
      return ['success' => false, 'message' => 'Link not found'];
    }

    $currentShortlink = trim((string) ($link['shortlink'] ?? ''));

    $sql = "SELECT id, alias FROM link_aliases
            WHERE link_id = :link_id
              AND REPLACE(LOWER(alias), '/', '') = REPLACE(LOWER(:alias), '/', '')
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'link_id' => $linkId,
      'alias' => $alias,
    ]);
    $aliasRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aliasRow) {
      $pdo->rollBack();
      closeConnection($pdo);
      return ['success' => false, 'message' => 'Alias not found'];
    }

    $normalizedAlias = trim((string) ($aliasRow['alias'] ?? ''));

    $sql = "SELECT id FROM links
            WHERE REPLACE(LOWER(shortlink), '/', '') = REPLACE(LOWER(:shortlink), '/', '')
              AND id != :id
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'shortlink' => $normalizedAlias,
      'id' => $linkId,
    ]);
    $conflict = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($conflict) {
      $pdo->rollBack();
      closeConnection($pdo);
      return ['success' => false, 'message' => 'Alias is already used by another link'];
    }

    $sql = 'UPDATE links
            SET shortlink = :shortlink,
                modifier = :modifier,
                modified_at = :modified_at
            WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'shortlink' => $normalizedAlias,
      'modifier' => $_SESSION['user']['id'] ?? null,
      'modified_at' => date('Y-m-d H:i:s'),
      'id' => $linkId,
    ]);

    $sql = "DELETE FROM link_aliases
            WHERE link_id = :link_id
              AND REPLACE(LOWER(alias), '/', '') = REPLACE(LOWER(:alias), '/', '')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'link_id' => $linkId,
      'alias' => $normalizedAlias,
    ]);

    if ($currentShortlink !== '' && strcasecmp($currentShortlink, $normalizedAlias) !== 0) {
      $sql = "SELECT id FROM links
              WHERE REPLACE(LOWER(shortlink), '/', '') = REPLACE(LOWER(:shortlink), '/', '')
                AND id != :id
              LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'shortlink' => $currentShortlink,
        'id' => $linkId,
      ]);
      $primaryConflict = $stmt->fetch(PDO::FETCH_ASSOC);

      $sql = "SELECT link_aliases.id, link_aliases.link_id
              FROM link_aliases
              INNER JOIN links ON links.id = link_aliases.link_id
              WHERE REPLACE(LOWER(link_aliases.alias), '/', '') = REPLACE(LOWER(:alias), '/', '')
              LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['alias' => $currentShortlink]);
      $aliasConflict = $stmt->fetch(PDO::FETCH_ASSOC);

      $aliasUsedByOtherLink = $aliasConflict && (int) ($aliasConflict['link_id'] ?? 0) !== $linkId;

      if (!$primaryConflict && !$aliasUsedByOtherLink) {
        $sql = 'INSERT OR IGNORE INTO link_aliases (link_id, alias, created_at, created_by)
                VALUES (:link_id, :alias, :created_at, :created_by)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          'link_id' => $linkId,
          'alias' => $currentShortlink,
          'created_at' => date('Y-m-d H:i:s'),
          'created_by' => $_SESSION['user']['id'] ?? null,
        ]);
      }
    }

    $pdo->commit();
    closeConnection($pdo);
    return [
      'success' => true,
      'message' => 'Alias restored',
      'shortlink' => $normalizedAlias,
    ];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    closeConnection($pdo);
    return ['success' => false, 'message' => 'Failed to restore alias'];
  }
}

function deleteLinkAlias($payload)
{
  if (!checkAdmin()) {
    return ['success' => false, 'message' => 'Unauthorized'];
  }

  $linkId = isset($payload['link_id']) ? (int) $payload['link_id'] : 0;
  $alias = trim((string) ($payload['alias'] ?? ''));

  if ($linkId <= 0 || $alias === '') {
    return ['success' => false, 'message' => 'Invalid alias delete request'];
  }

  $pdo = connectToDatabase();
  ensureLinkAliasesTable($pdo);

  try {
    $sql = "SELECT id
            FROM link_aliases
            WHERE link_id = :link_id
              AND REPLACE(LOWER(alias), '/', '') = REPLACE(LOWER(:alias), '/', '')
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'link_id' => $linkId,
      'alias' => $alias,
    ]);
    $aliasRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aliasRow) {
      closeConnection($pdo);
      return ['success' => false, 'message' => 'Alias not found'];
    }

    $sql = "DELETE FROM link_aliases
            WHERE link_id = :link_id
              AND REPLACE(LOWER(alias), '/', '') = REPLACE(LOWER(:alias), '/', '')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'link_id' => $linkId,
      'alias' => $alias,
    ]);

    closeConnection($pdo);
    return ['success' => true, 'message' => 'Alias deleted'];
  } catch (Throwable $e) {
    closeConnection($pdo);
    return ['success' => false, 'message' => 'Failed to delete alias'];
  }
}

function ensureVisitsTrackingColumns($pdo)
{
  static $ensured = false;

  if ($ensured) {
    return;
  }

  try {
    if (function_exists('columnExists') && !columnExists($pdo, 'visits', 'shortlink_used')) {
      $pdo->exec('ALTER TABLE visits ADD COLUMN shortlink_used TEXT');
    }

    if (function_exists('columnExists') && !columnExists($pdo, 'visits', 'referer')) {
      $pdo->exec('ALTER TABLE visits ADD COLUMN referer TEXT');
    }

    if (function_exists('columnExists') && !columnExists($pdo, 'visits', 'user_agent')) {
      $pdo->exec('ALTER TABLE visits ADD COLUMN user_agent TEXT');
    }
  } catch (Throwable $e) {
    // Keep redirects working even if this migration cannot run.
  }

  $ensured = true;
}


// get all links for initial load
function getLinks()
{
  $pdo = connectToDatabase();

  $limit = $_SESSION['user']['limit'] ?? 10; // Default limit
  $sql = "SELECT * FROM links ORDER BY status DESC, modified_at DESC LIMIT :limit";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch all tags
  $sql = "SELECT link_tags.link_id, tags.* FROM link_tags LEFT JOIN tags ON link_tags.tag_id = tags.id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch all groups
  $sql = "SELECT link_groups.link_id, groups.* FROM link_groups LEFT JOIN groups ON link_groups.group_id = groups.id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch all visits
  $sql = "SELECT * FROM visits WHERE date >= datetime('now', 'start of day')";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $sql = "SELECT link_id, MAX(date) as last_visited_at FROM visits GROUP BY link_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $last_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $sql = "SELECT * FROM user_links WHERE user_id = :user_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['user_id' => $_SESSION['user']['id']]);
  $favourites = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $sql = "SELECT link_id, COUNT(DISTINCT visitor_id) as unique_visitors FROM visits GROUP BY link_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $unique_visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch alias counts
  $alias_counts = [];
  try {
    ensureLinkAliasesTable($pdo);
    $sql = "SELECT link_id, COUNT(*) as alias_count FROM link_aliases GROUP BY link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $alias_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $alias_counts = [];
  }

  // Map tags, groups, visits, and last visit to their corresponding links
  $links = array_map(function ($link) use ($tags, $groups, $visits, $favourites, $last_visits, $unique_visitors, $alias_counts) {
    $link['tags'] = array_filter($tags, function ($tag) use ($link) {
      return $tag['link_id'] == $link['id'];
    });
    $link['groups'] = array_filter($groups, function ($group) use ($link) {
      return $group['link_id'] == $link['id'];
    });
    $link['visitsToday'] = array_filter($visits, function ($visit) use ($link) {
      return $visit['link_id'] == $link['id'];
    });
    $link['favourite'] = array_filter($favourites, function ($favourite) use ($link) {
      return $favourite['link_id'] == $link['id'];
    });
    $link['last_visited_at'] = null;
    $last_visit = array_filter($last_visits, function ($last_visit) use ($link) {
      return $last_visit['link_id'] == $link['id'];
    });
    if (!empty($last_visit)) {
      $last_visit = reset($last_visit);
      $link['last_visited_at'] = $last_visit['last_visited_at'];
    }
    $link['unique_visitors'] = 0;
    $unique_visitor = array_filter($unique_visitors, function ($visitor) use ($link) {
      return $visitor['link_id'] == $link['id'];
    });
    if (!empty($unique_visitor)) {
      $unique_visitor = reset($unique_visitor);
      $link['unique_visitors'] = $unique_visitor['unique_visitors'];
    }
    $link['alias_count'] = 0;
    $alias_count_row = array_filter($alias_counts, function ($ac) use ($link) {
      return $ac['link_id'] == $link['id'];
    });
    if (!empty($alias_count_row)) {
      $alias_count_row = reset($alias_count_row);
      $link['alias_count'] = (int) $alias_count_row['alias_count'];
    }
    return $link;
  }, $links);

  closeConnection($pdo);
  return $links;
}

function getLinksByGroup($group)
{
  checkUser();

  $pdo = connectToDatabase();
  try {
    // Fetch links by group
    $sql = 'SELECT links.* FROM links 
            INNER JOIN link_groups ON links.id = link_groups.link_id 
            INNER JOIN groups ON link_groups.group_id = groups.id 
            WHERE groups.title = :group';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['group' => $group]);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all tags
    $sql = "SELECT link_tags.link_id, tags.* FROM link_tags LEFT JOIN tags ON link_tags.tag_id = tags.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all groups
    $sql = "SELECT link_groups.link_id, groups.* FROM link_groups LEFT JOIN groups ON link_groups.group_id = groups.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all visits
    $sql = "SELECT * FROM visits WHERE date >= datetime('now', 'start of day')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT link_id, MAX(date) as last_visited_at FROM visits GROUP BY link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $last_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all favourites
    $sql = "SELECT * FROM user_links WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user']['id']]);
    $favourites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = 'SELECT link_id, COUNT(DISTINCT visitor_id) as unique_visitors FROM visits GROUP BY link_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $unique_visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch alias counts
    $alias_counts = [];
    try {
      ensureLinkAliasesTable($pdo);
      $sql = "SELECT link_id, COUNT(*) as alias_count FROM link_aliases GROUP BY link_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute();
      $alias_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $alias_counts = [];
    }

    // Map tags, groups, visits, favourites, last visits, and unique visitors to their corresponding links
    $links = array_map(function ($link) use ($tags, $groups, $visits, $favourites, $last_visits, $unique_visitors, $alias_counts) {
      $link['tags'] = array_filter($tags, function ($tag) use ($link) {
        return $tag['link_id'] == $link['id'];
      });
      $link['groups'] = array_filter($groups, function ($group) use ($link) {
        return $group['link_id'] == $link['id'];
      });
      $link['visitsToday'] = array_filter($visits, function ($visit) use ($link) {
        return $visit['link_id'] == $link['id'];
      });
      $link['favourite'] = array_filter($favourites, function ($favourite) use ($link) {
        return $favourite['link_id'] == $link['id'];
      });
      $link['last_visited_at'] = null;
      $last_visit = array_filter($last_visits, function ($last_visit) use ($link) {
        return $last_visit['link_id'] == $link['id'];
      });
      if (!empty($last_visit)) {
        $last_visit = reset($last_visit);
        $link['last_visited_at'] = $last_visit['last_visited_at'];
      }
      $link['unique_visitors'] = 0;
      $unique_visitor = array_filter($unique_visitors, function ($unique_visitor) use ($link) {
        return $unique_visitor['link_id'] == $link['id'];
      });
      if (!empty($unique_visitor)) {
        $unique_visitor = reset($unique_visitor);
        $link['unique_visitors'] = $unique_visitor['unique_visitors'];
      }
      $link['alias_count'] = 0;
      $alias_count_row = array_filter($alias_counts, function ($ac) use ($link) {
        return $ac['link_id'] == $link['id'];
      });
      if (!empty($alias_count_row)) {
        $alias_count_row = reset($alias_count_row);
        $link['alias_count'] = (int) $alias_count_row['alias_count'];
      }
      return $link;
    }, $links);
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '' . $e->getMessage()]);
  }

  closeConnection($pdo);
  return $links;
}

function getLinkById($id)
{
  $pdo = connectToDatabase();

  // Fetch the link by ID
  $sql = "SELECT * FROM links WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $link = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($link) {
    // Fetch related tags
    $sql = "SELECT tags.* FROM link_tags LEFT JOIN tags ON link_tags.tag_id = tags.id WHERE link_tags.link_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $link['tags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch related groups
    $sql = "SELECT groups.* FROM link_groups LEFT JOIN groups ON link_groups.group_id = groups.id WHERE link_groups.link_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $link['groups'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch related visits
    $sql = "SELECT * FROM visits WHERE link_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $link['visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT * FROM visits WHERE link_id = :id AND date >= datetime('now', 'start of day')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $link['visitsToday'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = 'SELECT link_id, COUNT(DISTINCT visitor_id) as unique_visitors FROM visits WHERE link_id = :id GROUP BY link_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $link['unique_visitors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  closeConnection($pdo);
  return $link;
}

function getData($id, $shortlinkUsed = '')
{

  $visitor = getVisitor();

  $pdo = connectToDatabase();

  $sql = "SELECT * FROM links WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['id' => $id]);
  $link = $stmt->fetch();

  $ip = $_SERVER['REMOTE_ADDR'];
  $date = date('Y-m-d H:i:s');

  ensureVisitsTrackingColumns($pdo);

  $visitColumns = ['ip', 'link_id', 'date', 'visitor_id'];
  $visitParams = [
    'ip' => $ip,
    'linkId' => $link['id'],
    'date' => $date,
    'visitorId' => $visitor['id']
  ];

  if (function_exists('columnExists') && columnExists($pdo, 'visits', 'shortlink_used')) {
    $visitColumns[] = 'shortlink_used';
    $visitParams['shortlink_used'] = (string) $shortlinkUsed;
  }

  if (function_exists('columnExists') && columnExists($pdo, 'visits', 'referer')) {
    $visitColumns[] = 'referer';
    $visitParams['referer'] = (string) ($_SERVER['HTTP_REFERER'] ?? '');
  }

  if (function_exists('columnExists') && columnExists($pdo, 'visits', 'user_agent')) {
    $visitColumns[] = 'user_agent';
    $visitParams['user_agent'] = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
  }

  $visitPlaceholders = array_map(function ($column) {
    if ($column === 'link_id') {
      return ':linkId';
    }
    if ($column === 'visitor_id') {
      return ':visitorId';
    }

    return ':' . $column;
  }, $visitColumns);

  $sql = 'INSERT INTO visits (' . implode(', ', $visitColumns) . ') VALUES (' . implode(', ', $visitPlaceholders) . ')';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($visitParams);

  $sql = "UPDATE visitors SET visit_count = :visit_count, last_visit = :last_visit, last_visit_date = :date WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':visit_count' => intval($visitor['visit_count'] ?? 0) + 1, ':last_visit' => $link['id'], ':date' => $date, 'id' => $visitor['id']]);

  $sql = "UPDATE links SET visit_count = :visit_count, last_visited_at = :date WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':visit_count' => (int) ($link['visit_count'] ?? 0) + 1, ':date' => $date, 'id' => $id]);

  closeConnection($pdo);
}

function getArchive()
{
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM archive ORDER BY modified_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
  closeConnection($pdo);
  return $data;
}

function handleShortlink($shortlink)
{
  try {
    $link = getLinkByShortlink($shortlink);
    if (!$link) {
      include __DIR__ . '/../../pages/errors/404.php';
      return;
    }

    // Check if link is archived
    if ($link['status'] === 0 || $link['status'] === '0') {
      include __DIR__ . '/../../pages/errors/404.php';
      return;
    }

    // Expired links should still send users to the original destination URL.
    if (!empty($link['expires_at'])) {
      $expiresTs = strtotime($link['expires_at']);
      if ($expiresTs !== false && $expiresTs < time()) {
        if (!empty($link['url']) && filter_var($link['url'], FILTER_VALIDATE_URL)) {
          header('Location: ' . filter_var($link['url'], FILTER_SANITIZE_URL));
          exit();
        }
        include __DIR__ . '/../../pages/errors/404.php';
        return;
      }
    }

    // Validate URL before redirect
    if (empty($link['url']) || !filter_var($link['url'], FILTER_VALIDATE_URL)) {
      include __DIR__ . '/../../pages/errors/404.php';
      return;
    }

    getData($link['id'], $shortlink);
    header('Location: ' . filter_var($link['url'], FILTER_SANITIZE_URL));
    exit();
  } catch (Exception $e) {
    http_response_code(500);
    include __DIR__ . '/../../pages/errors/error.php';
    return;
  }
}

function linkCount()
{
  $pdo = connectToDatabase();
  $sql = "SELECT count(*) FROM links";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $linkCount = $stmt->fetch(PDO::FETCH_ASSOC);
  closeConnection($pdo);
  return json_encode($linkCount['count(*)']);
}

function switchStatus($data)
{
  $pdo = connectToDatabase();
  $sql = "UPDATE links SET status = :status, modifier = :modifier, modified_at = :modified_at WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'status' => intval($data['status']) === 0 ? 1 : 0,
    'modifier' => $_SESSION['user']['id'],
    'modified_at' => date('Y-m-d H:i:s'),
    'id' => $data['id']
  ]);
  closeConnection($pdo);
  return json_encode(['status' => intval($data['status']) === 0 ? 'active' : 'archived', 'id' => $data['id']]);
}

function addToGroup($id, $group)
{
  $orgininalGroup = $group;
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM groups WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $group]);
  $group = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$group) {
    $sql = "INSERT INTO groups (title) VALUES (:title)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['title' => ucfirst($orgininalGroup)]);
    $group['id'] = $pdo->lastInsertId();
  }

  $sql = 'SELECT * FROM link_groups WHERE link_id = :link_id AND group_id = :group_id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'link_id' => $id,
    'group_id' => $group['id']
  ]);
  $linkGroup = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($linkGroup) {
    closeConnection($pdo);
    return;
  }


  $sql = "INSERT INTO link_groups (link_id, group_id) VALUES (:link_id, :group_id)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['link_id' => $id, 'group_id' => $group['id']]);
  closeConnection($pdo);
}

function addToTag($id, $tag)
{
  $orgininalTag = $tag;
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM tags WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $tag]);
  $tag = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$tag) {
    $sql = "INSERT INTO tags (title) VALUES (:title)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['title' => ucfirst($orgininalTag)]);
    $tag['id'] = $pdo->lastInsertId();
  }

  $sql = 'SELECT * FROM link_tags WHERE link_id = :link_id AND tag_id = :tag_id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'link_id' => $id,
    'tag_id' => $tag['id']
  ]);
  $linkTag = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($linkTag) {
    closeConnection($pdo);
    return;
  }

  $sql = "INSERT INTO link_tags (link_id, tag_id) VALUES (:link_id, :tag_id)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['link_id' => $id, 'tag_id' => $tag['id']]);
  closeConnection($pdo);
}

function removeGroup($id, $group)
{
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM groups WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $group]);
  $group = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$group || !isset($group['id'])) {
    closeConnection($pdo);
    return;
  }

  $sql = "DELETE FROM link_groups WHERE link_id = :link_id AND group_id = :group_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['link_id' => $id, 'group_id' => $group['id']]);

  $sql = "SELECT * FROM link_groups WHERE group_id = :group_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['group_id' => $group['id']]);
  $groupLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($groupLinks) === 0) {
    $sql = "DELETE FROM groups WHERE id = :group_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['group_id' => $group['id']]);
  }

  closeConnection($pdo);
}

function removeTag($id, $tag)
{
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM tags WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $tag]);
  $tag = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$tag || !isset($tag['id'])) {
    closeConnection($pdo);
    return;
  }

  $sql = "DELETE FROM link_tags WHERE link_id = :link_id AND tag_id = :tag_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['link_id' => $id, 'tag_id' => $tag['id']]);

  $sql = "SELECT * FROM link_tags WHERE tag_id = :tag_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['tag_id' => $tag['id']]);
  $tagLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($tagLinks) === 0) {
    $sql = "DELETE FROM tags WHERE id = :tag_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['tag_id' => $tag['id']]);
  }

  closeConnection($pdo);
}


function checkCustomFonts()
{
  $directory = __DIR__ . '/../../public/custom/fonts';
  if (!is_dir($directory)) {
    $fonts = [];
  } else {
    $fonts = scandir($directory);
    $fonts = $fonts === false ? [] : array_values(array_diff($fonts, ['.', '..']));
  }

  if (count($fonts) > 0) {
    $cssContent = "
  @font-face {
    font-family: 'custom-font';
    src: url(/custom/fonts/{$fonts[0]}) format('truetype');
  }
";
  } else {
    $cssContent = '';
  }

  file_put_contents(__DIR__ . '/../../public/css/custom.css', $cssContent);
}

function checkBackup($domain)
{
  if (php_sapi_name() === 'cli-server') {
    return null;
  }

  if (!checkAdmin()) {
    return null;
  }

  require_once __DIR__ . '/../../config/backupService.php';

  $requestedBy = isset($_SESSION['user']['email'])
    ? 'web-auto:' . (string) $_SESSION['user']['email']
    : 'web-auto:admin';

  return backupRunScheduledBackupIfDue($requestedBy);
}

function removeDuplicateTags()
{
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM tags";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $tagTitles = [];
  foreach ($tags as $tag) {
    if (in_array($tag['title'], $tagTitles)) {

      $sql = "DELETE FROM tags WHERE id = :id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['id' => $tag['id']]);

      $sql = "DELETE FROM link_tags WHERE tag_id = :tag_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['tag_id' => $tag['id']]);
    } else {
      array_push($tagTitles, $tag['title']);
    }
  }

  $sql = "SELECT * FROM groups";
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $groupTitles = [];
  foreach ($groups as $group) {
    if (in_array($group['title'], $groupTitles)) {

      $sql = "DELETE FROM groups WHERE id = :id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['id' => $group['id']]);

      $sql = "DELETE FROM link_groups WHERE group_id = :group_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['group_id' => $group['id']]);
    } else {
      array_push($groupTitles, $group['title']);
    }
  }

  closeConnection($pdo);
}
