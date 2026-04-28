<?php

function getFilteredGroups($filter)
{
  $pdo = connectToDatabase();
  $allowedLimits = [10, 20, 50, 100];
  $limit = isset($filter['limit']) ? intval($filter['limit']) : 10;
  if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
  }
  $offset = isset($filter['offset']) ? intval($filter['offset']) : 0;
  $allowedSorts = ['alphabet_asc', 'alphabet_desc', 'latest', 'oldest', 'latest_modified', 'most_links', 'least_links'];
  $sort = isset($filter['sort']) ? (string) $filter['sort'] : 'latest_modified';
  if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'latest_modified';
  }
  $search = isset($filter['search']) ? $filter['search'] : '';

  // Base SQL query
  $sql = "SELECT groups.*, COUNT(link_groups.link_id) AS link_count FROM groups LEFT JOIN link_groups ON groups.id = link_groups.group_id";

  // Add WHERE conditions for search
  $conditions = [];
  if (!empty($search)) {
    $conditions[] = "groups.title LIKE ?";
  }
  if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
  }

  $sql .= " GROUP BY groups.id";

  // Count total number of groups
  $countSql = "SELECT COUNT(*) FROM groups";
  if (!empty($conditions)) {
    $countSql .= " WHERE " . implode(' AND ', $conditions);
  }

  $countStmt = $pdo->prepare($countSql);

  // Bind search parameter for count query
  $paramIndex = 1;
  if (!empty($search)) {
    $countStmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
  }

  $countStmt->execute();
  $totalGroups = $countStmt->fetchColumn();

  // Add ORDER BY clause based on the sort parameter
  switch ($sort) {
    case 'oldest':
      $sql .= " ORDER BY groups.created_at ASC";
      break;
    case 'alphabet_asc':
      $sql .= " ORDER BY groups.title COLLATE NOCASE ASC";
      break;
    case 'alphabet_desc':
      $sql .= " ORDER BY groups.title COLLATE NOCASE DESC";
      break;
    case 'latest_modified':
      $sql .= " ORDER BY groups.modified_at DESC";
      break;
    case 'most_links':
      $sql .= " ORDER BY link_count DESC, groups.modified_at DESC";
      break;
    case 'least_links':
      $sql .= " ORDER BY link_count ASC, groups.modified_at DESC";
      break;
    case 'latest':
    default:
      $sql .= " ORDER BY groups.modified_at DESC";
      break;
  }

  // Add LIMIT and OFFSET
  $sql .= " LIMIT :limit OFFSET :offset";
  $stmt = $pdo->prepare($sql);

  // Bind search parameter for main query
  $paramIndex = 1;
  if (!empty($search)) {
    $stmt->bindValue($paramIndex++, '%' . $search . '%', PDO::PARAM_STR);
  }

  // Bind limit and offset
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

  $stmt->execute();
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
  if ($userId > 0) {
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

  closeConnection($pdo);
  return ['total' => $totalGroups, 'groups' => $groups];
}
