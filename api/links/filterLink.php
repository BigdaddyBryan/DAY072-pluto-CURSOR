<?php
function getFilteredLinks($filter)
{
  $pdo = connectToDatabase();
  $allowedLimits = [10, 20, 50, 100];
  $limit = isset($filter['limit']) ? intval($filter['limit']) : 10;
  if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
  }
  $offset = isset($filter['offset']) ? intval($filter['offset']) : 0;
  $tags = isset($filter['tags']) ? $filter['tags'] : [];
  $groups = isset($filter['groups']) ? $filter['groups'] : [];
  $allowedSorts = [
    'alphabet_asc',
    'alphabet_desc',
    'latest_visit',
    'favorite',
    'latest',
    'oldest',
    'most_visit',
    'least_visit',
    'latest_modified',
    'most_visits_today',
    'archived',
  ];
  $sort = isset($filter['sort']) ? (string) $filter['sort'] : 'latest_modified';
  if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'latest_modified';
  }
  $search = isset($filter['search']) ? $filter['search'] : '';
  $searchType = isset($filter['searchType']) ? $filter['searchType'] : 'title';
  $skipPreferencePersist = !empty($filter['_skipPreferencePersist']);

  // Base SQL query
  $sql = "SELECT DISTINCT links.* FROM links";
  $countSql = "SELECT COUNT(DISTINCT links.id) FROM links";

  // Join with tags and groups if filters are provided
  if (!empty($tags) || $searchType === 'tags' || $searchType === 'all') {
    $sql .= " LEFT JOIN link_tags ON links.id = link_tags.link_id";
    $sql .= " LEFT JOIN tags ON link_tags.tag_id = tags.id";
    $countSql .= " LEFT JOIN link_tags ON links.id = link_tags.link_id";
    $countSql .= " LEFT JOIN tags ON link_tags.tag_id = tags.id";
  }
  if (!empty($groups) || $searchType === 'groups' || $searchType === 'all') {
    $sql .= " LEFT JOIN link_groups ON links.id = link_groups.link_id";
    $sql .= " LEFT JOIN groups ON link_groups.group_id = groups.id";
    $countSql .= " LEFT JOIN link_groups ON links.id = link_groups.link_id";
    $countSql .= " LEFT JOIN groups ON link_groups.group_id = groups.id";
  }

  // Add WHERE conditions for tags, groups, and search
  $conditions = [];
  if (!empty($tags)) {
    $conditions[] = "tags.title IN (" . implode(',', array_fill(0, count($tags), '?')) . ")";
  }
  if (!empty($groups)) {
    $conditions[] = "groups.title IN (" . implode(',', array_fill(0, count($groups), '?')) . ")";
  }
  if (!empty($search)) {
    switch ($searchType) {
      case 'title':
        $conditions[] = "links.title LIKE ?";
        break;
      case 'url':
        $conditions[] = "links.url LIKE ?";
        break;
      case 'tags':
        $conditions[] = "tags.title LIKE ?";
        break;
      case 'groups':
        $conditions[] = "groups.title LIKE ?";
        break;
      case 'shortlink':
        $conditions[] = "links.shortlink LIKE ?";
        break;
      case 'all':
      default:
        $conditions[] = " (links.title LIKE ? OR links.url LIKE ? OR tags.title LIKE ? OR groups.title LIKE ? OR links.shortlink LIKE ?)";
        break;
    }
  }

  if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
    $countSql .= " WHERE " . implode(' AND ', $conditions);
  }

  $countStmt = $pdo->prepare($countSql);

  // Bind tag and group parameters for count query
  $paramIndex = 1;
  foreach ($tags as $tag) {
    $countStmt->bindValue($paramIndex++, $tag, PDO::PARAM_STR);
  }
  foreach ($groups as $group) {
    $countStmt->bindValue($paramIndex++, $group, PDO::PARAM_STR);
  }

  // Bind search parameter for count query
  if (!empty($search)) {
    switch ($searchType) {
      case 'title':
      case 'url':
      case 'tags':
      case 'groups':
      case 'shortlink':
        $countStmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        break;
      case 'all':
      default:
        $countStmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        $countStmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        $countStmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        $countStmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        $countStmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        break;
    }
  }

  $countStmt->execute();
  $totalLinks = $countStmt->fetchColumn();

  // Build a single ORDER BY clause so primary sort is the requested metric.
  $orderBy = 'ORDER BY status DESC, ';
  // If true, we'll perform sorting in PHP after fetching visits (used for
  // 'most_visits_today' because there's no persistent visit_count_today column)
  $doPostSort = false;
  if ($sort === 'archived') {
    $orderBy = "ORDER BY links.status ASC, links.modified_at DESC";
  } else if ($sort === 'favorite') {
    // Favorite needs a WHERE clause to limit to user links
    $sql .= " WHERE links.id IN (SELECT link_id FROM user_links WHERE user_id = :user_id)";
    $orderBy .= "links.created_at DESC";
  } else {
    switch ($sort) {
      case 'oldest':
        $orderBy = "ORDER BY links.created_at ASC";
        break;
      case 'alphabet_asc':
        $orderBy .= "links.title COLLATE NOCASE ASC";
        break;
      case 'alphabet_desc':
        $orderBy .= "links.title COLLATE NOCASE DESC";
        break;
      case 'latest_visit':
        $orderBy = "ORDER BY links.last_visited_at DESC, links.status DESC";
        break;
      case 'most_visit':
        // Primary: most visits (DESC). Secondary: active status so active links appear first when counts tie.
        $orderBy .= "links.visit_count DESC, links.status DESC";
        break;
      case 'least_visit':
        // Primary: least visits (ASC). Secondary: active status.
        $orderBy .= "links.visit_count ASC, links.status DESC";
        break;
      case 'latest_modified':
        $orderBy = "ORDER BY links.modified_at DESC";
        break;
      case 'most_visits_today':
        $doPostSort = true;
        $orderBy = "ORDER BY links.status DESC";
        break;
      case 'latest':
      default:
        $orderBy = "ORDER BY links.created_at DESC";
        break;
    }
  }

  $sql .= " " . $orderBy;


  if (!$doPostSort) {
    $sql .= " LIMIT :limit OFFSET :offset";
  }

  $stmt = $pdo->prepare($sql);

  // Bind tag and group parameters for main query
  $paramIndex = 1;
  foreach ($tags as $tag) {
    $stmt->bindValue($paramIndex++, $tag, PDO::PARAM_STR);
  }
  foreach ($groups as $group) {
    $stmt->bindValue($paramIndex++, $group, PDO::PARAM_STR);
  }

  // Bind search parameter for main query
  if (!empty($search)) {
    switch ($searchType) {
      case 'title':
      case 'url':
      case 'tags':
      case 'groups':
      case 'shortlink':
        $stmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        break;
      case 'all':
      default:
        $stmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
        break;
    }
  }

  // Bind limit and offset only when SQL used LIMIT/OFFSET
  if (!$doPostSort) {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  }
  if ($sort === 'favorite') {
    $stmt->bindValue(':user_id', $_SESSION['user']['id'], PDO::PARAM_INT);
  }
  $stmt->execute();
  $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
  if (!$skipPreferencePersist && $userId > 0) {
    try {
      $sql = "UPDATE users SET `limit` = :limit, sort_preference = :sort_preference WHERE id = :user_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':limit' => $limit,
        ':sort_preference' => $sort,
        ':user_id' => $userId,
      ]);
    } catch (PDOException $e) {
      $sql = "UPDATE users SET `limit` = :limit WHERE id = :user_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':limit' => $limit,
        ':user_id' => $userId,
      ]);
    }

    $_SESSION['user']['limit'] = $limit;
    $_SESSION['user']['sort_preference'] = $sort;
  }

  if ($doPostSort && !empty($links)) {
    $sortLinkIds = array_values(array_map('intval', array_column($links, 'id')));
    $visitCountTodayByLink = [];
    if (!empty($sortLinkIds)) {
      $placeholders = implode(',', array_fill(0, count($sortLinkIds), '?'));
      $sql = "SELECT link_id, COUNT(*) AS visit_count_today FROM visits WHERE date >= datetime('now', 'localtime', 'start of day') AND link_id IN ($placeholders) GROUP BY link_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($sortLinkIds);
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $visitCountTodayByLink[(int) $row['link_id']] = (int) $row['visit_count_today'];
      }
    }

    foreach ($links as &$link) {
      $link['visit_count_today'] = $visitCountTodayByLink[(int) $link['id']] ?? 0;
    }
    unset($link);

    usort($links, function ($linkA, $linkB) {
      $countA = $linkA['visit_count_today'] ?? 0;
      $countB = $linkB['visit_count_today'] ?? 0;

      if ($countA === $countB) {
        return ($linkB['status'] ?? 0) <=> ($linkA['status'] ?? 0);
      }

      return $countB <=> $countA;
    });

    $links = array_slice($links, $offset, $limit);
  }

  $pageLinkIds = array_values(array_map('intval', array_column($links, 'id')));
  $tagsByLink = [];
  $groupsByLink = [];
  $visitsTodayByLink = [];
  $lastVisitedByLink = [];
  $favouritesByLink = [];
  $uniqueVisitorsByLink = [];

  if (!empty($pageLinkIds)) {
    $placeholders = implode(',', array_fill(0, count($pageLinkIds), '?'));

    $sql = "SELECT link_tags.link_id, tags.* FROM link_tags LEFT JOIN tags ON link_tags.tag_id = tags.id WHERE link_tags.link_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($pageLinkIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tag) {
      $linkId = (int) $tag['link_id'];
      if (!isset($tagsByLink[$linkId])) {
        $tagsByLink[$linkId] = [];
      }
      $tagsByLink[$linkId][] = $tag;
    }

    $sql = "SELECT link_groups.link_id, groups.* FROM link_groups LEFT JOIN groups ON link_groups.group_id = groups.id WHERE link_groups.link_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($pageLinkIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $group) {
      $linkId = (int) $group['link_id'];
      if (!isset($groupsByLink[$linkId])) {
        $groupsByLink[$linkId] = [];
      }
      $groupsByLink[$linkId][] = $group;
    }

    $sql = "SELECT * FROM visits WHERE date >= datetime('now', 'localtime', 'start of day') AND link_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($pageLinkIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $visit) {
      $linkId = (int) $visit['link_id'];
      if (!isset($visitsTodayByLink[$linkId])) {
        $visitsTodayByLink[$linkId] = [];
      }
      $visitsTodayByLink[$linkId][] = $visit;
    }

    $sql = "SELECT link_id, MAX(date) as last_visited_at FROM visits WHERE link_id IN ($placeholders) GROUP BY link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($pageLinkIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $lastVisit) {
      $lastVisitedByLink[(int) $lastVisit['link_id']] = $lastVisit['last_visited_at'];
    }

    $favouritesSql = "SELECT * FROM user_links WHERE user_id = ? AND link_id IN ($placeholders)";
    $stmt = $pdo->prepare($favouritesSql);
    $stmt->execute(array_merge([(int) $_SESSION['user']['id']], $pageLinkIds));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fav) {
      $linkId = (int) $fav['link_id'];
      if (!isset($favouritesByLink[$linkId])) {
        $favouritesByLink[$linkId] = [];
      }
      $favouritesByLink[$linkId][] = $fav;
    }

    $sql = "SELECT link_id, COUNT(DISTINCT visitor_id) as unique_visitors FROM visits WHERE link_id IN ($placeholders) GROUP BY link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($pageLinkIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $visitorCount) {
      $uniqueVisitorsByLink[(int) $visitorCount['link_id']] = (int) $visitorCount['unique_visitors'];
    }

    // Fetch alias counts
    $aliasCounts = [];
    try {
      if (function_exists('ensureLinkAliasesTable')) {
        ensureLinkAliasesTable($pdo);
      }
      $sql = "SELECT link_id, COUNT(*) as alias_count FROM link_aliases WHERE link_id IN ($placeholders) GROUP BY link_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($pageLinkIds);
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ac) {
        $aliasCounts[(int) $ac['link_id']] = (int) $ac['alias_count'];
      }
    } catch (Throwable $e) {
      $aliasCounts = [];
    }
  }

  foreach ($links as &$link) {
    $linkId = (int) $link['id'];
    $link['tags'] = $tagsByLink[$linkId] ?? [];
    $link['groups'] = $groupsByLink[$linkId] ?? [];
    $link['visitsToday'] = $visitsTodayByLink[$linkId] ?? [];
    $link['favourite'] = $favouritesByLink[$linkId] ?? [];
    $link['last_visited_at'] = $lastVisitedByLink[$linkId] ?? null;
    $link['unique_visitors'] = $uniqueVisitorsByLink[$linkId] ?? 0;
    $link['alias_count'] = $aliasCounts[$linkId] ?? 0;
  }
  unset($link);

  closeConnection($pdo);
  return ['total' => $totalLinks, 'links' => $links];
}
