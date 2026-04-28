<?php
function getFilteredVisitors($filter)
{
  $pdo = connectToDatabase();
  $limit = isset($filter['limit']) ? intval($filter['limit']) : 10;
  $offset = isset($filter['offset']) ? intval($filter['offset']) : 0;
  $tags = isset($filter['tags']) ? $filter['tags'] : [];
  $groups = isset($filter['groups']) ? $filter['groups'] : [];
  $sort = isset($filter['sort']) ? $filter['sort'] : 'latest';
  $search = isset($filter['search']) ? $filter['search'] : '';
  $searchType = isset($filter['searchType']) ? $filter['searchType'] : 'all';

  // Base SQL query
  $sql = "SELECT DISTINCT visitors.* FROM visitors";
  $countSql = "SELECT COUNT(DISTINCT visitors.id) FROM visitors";

  // Join with tags, groups, and visits if filters are provided
  if (!empty($tags) || $searchType === 'tags' || $searchType === 'all') {
    $sql .= " LEFT JOIN visitors_tags ON visitors.id = visitors_tags.visitor_id";
    $sql .= " LEFT JOIN tags ON visitors_tags.tag_id = tags.id";
    $countSql .= " LEFT JOIN visitors_tags ON visitors.id = visitors_tags.visitor_id";
    $countSql .= " LEFT JOIN tags ON visitors_tags.tag_id = tags.id";
  }
  if (!empty($groups) || $searchType === 'groups' || $searchType === 'all') {
    $sql .= " LEFT JOIN visitors_groups ON visitors.id = visitors_groups.visitor_id";
    $sql .= " LEFT JOIN groups ON visitors_groups.group_id = groups.id";
    $countSql .= " LEFT JOIN visitors_groups ON visitors.id = visitors_groups.visitor_id";
    $countSql .= " LEFT JOIN groups ON visitors_groups.group_id = groups.id";
  }
  if ($searchType === 'title' || $searchType === 'all') {
    $sql .= " LEFT JOIN (SELECT * FROM visits WHERE id IN (SELECT MAX(id) FROM visits GROUP BY visitor_id)) AS last_visits ON visitors.id = last_visits.visitor_id";
    $sql .= " LEFT JOIN links ON last_visits.link_id = links.id";
    $countSql .= " LEFT JOIN (SELECT * FROM visits WHERE id IN (SELECT MAX(id) FROM visits GROUP BY visitor_id)) AS last_visits ON visitors.id = last_visits.visitor_id";
    $countSql .= " LEFT JOIN links ON last_visits.link_id = links.id";
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
      case 'name':
        $conditions[] = "visitors.name LIKE ?";
        break;
      case 'ip':
        $conditions[] = "visitors.ip LIKE ?";
        break;
      case 'tags':
        $conditions[] = "tags.title LIKE ?";
        break;
      case 'groups':
        $conditions[] = "groups.title LIKE ?";
        break;
      case 'title':
        $conditions[] = "links.title LIKE ?";
        break;
      case 'all':
      default:
        $conditions[] = "(visitors.name LIKE ? OR visitors.ip LIKE ? OR tags.title LIKE ? OR groups.title LIKE ? OR links.title LIKE ?)";
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
      case 'name':
      case 'ip':
      case 'tags':
      case 'groups':
      case 'title':
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
  $totalVisitors = $countStmt->fetchColumn();

  // Build a single ORDER BY clause so sorting is explicit and deterministic
  $orderBy = '';
  switch ($sort) {
    case 'oldest':
      $orderBy = "ORDER BY visitors.created_at ASC";
      break;
    case 'alphabet_asc':
      $orderBy = "ORDER BY visitors.name COLLATE NOCASE ASC";
      break;
    case 'alphabet_desc':
      $orderBy = "ORDER BY visitors.name COLLATE NOCASE DESC";
      break;
    case 'latest_visit':
      $orderBy = "ORDER BY visitors.last_visit_date DESC, visitors.visit_count DESC";
      break;
    case 'most_visit':
      // Most visited: highest visit_count first
      $orderBy = "ORDER BY visitors.visit_count DESC, visitors.last_visit_date DESC";
      break;
    case 'least_visit':
      // Least visited: lowest visit_count first
      $orderBy = "ORDER BY visitors.visit_count ASC, visitors.last_visit_date DESC";
      break;
    case 'latest_modified':
      $orderBy = "ORDER BY visitors.modified_at DESC";
      break;
    case 'most_visits_today':
      $orderBy = "ORDER BY visitors.visit_count_today DESC, visitors.last_visit_date DESC";
      break;
    case 'most_visits_week':
      $orderBy = "ORDER BY visits.visit_count_week DESC, visitors.last_visit_date DESC";
      break;
    case 'latest':
    default:
      $orderBy = "ORDER BY visitors.last_visit_date DESC";
      break;
  }

  $sql .= " " . $orderBy;

  // Add LIMIT and OFFSET
  $sql .= " LIMIT :limit OFFSET :offset";
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
      case 'name':
      case 'ip':
      case 'tags':
      case 'groups':
      case 'title':
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

  // Bind limit and offset
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($_SESSION['user']['id']) {
    $sql = "UPDATE users SET `limit` = :limit WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ":limit" => $limit,
      ":user_id" => $_SESSION["user"]["id"]
    ]);
  }

  $visitorIds = array_values(array_map('intval', array_column($visitors, 'id')));
  $tagsByVisitor = [];
  $groupsByVisitor = [];

  if (!empty($visitorIds)) {
    $placeholders = implode(',', array_fill(0, count($visitorIds), '?'));

    $sql = "SELECT visitors_tags.visitor_id, tags.* FROM visitors_tags LEFT JOIN tags ON visitors_tags.tag_id = tags.id WHERE visitors_tags.visitor_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($visitorIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tag) {
      $visitorId = (int) $tag['visitor_id'];
      if (!isset($tagsByVisitor[$visitorId])) {
        $tagsByVisitor[$visitorId] = [];
      }
      $tagsByVisitor[$visitorId][] = $tag;
    }

    $sql = "SELECT visitors_groups.visitor_id, groups.* FROM visitors_groups LEFT JOIN groups ON visitors_groups.group_id = groups.id WHERE visitors_groups.visitor_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($visitorIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $group) {
      $visitorId = (int) $group['visitor_id'];
      if (!isset($groupsByVisitor[$visitorId])) {
        $groupsByVisitor[$visitorId] = [];
      }
      $groupsByVisitor[$visitorId][] = $group;
    }
  }

  foreach ($visitors as &$visitor) {
    $visitorId = (int) $visitor['id'];
    $visitor['tags'] = $tagsByVisitor[$visitorId] ?? [];
    $visitor['groups'] = $groupsByVisitor[$visitorId] ?? [];
  }
  unset($visitor);

  closeConnection($pdo);
  return ['total' => $totalVisitors, 'visitors' => $visitors];
}
