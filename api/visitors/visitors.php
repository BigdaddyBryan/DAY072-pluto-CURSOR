<?php


function getVisitsByVisitor($id)
{

  if (!checkAdmin()) {
    return;
  }

  $pdo = connectToDatabase();

  $stmt = $pdo->prepare('SELECT * FROM visits WHERE visitor_id = :id ORDER BY date DESC');
  $stmt->execute(['id' => $id]);
  $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

  closeConnection($pdo);

  return $visits;
}

function getVisitors()
{
  $pdo = connectToDatabase();
  checkUser();

  // Fetch visitors
  $sql = 'SELECT * FROM visitors ORDER BY last_visit_date DESC LIMIT ' . (int) $_SESSION['user']['limit'];
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch tags for visitors
  $sql = 'SELECT visitors_tags.visitor_id, tags.* FROM visitors_tags LEFT JOIN tags ON visitors_tags.tag_id = tags.id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch groups for visitors
  $sql = 'SELECT visitors_groups.visitor_id, groups.* FROM visitors_groups LEFT JOIN groups ON visitors_groups.group_id = groups.id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch all visits
  $sql = 'SELECT * FROM visits';
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Map tags, groups, and visits to their corresponding visitors
  $visitors = array_map(function ($visitor) use ($tags, $groups, $visits) {
    $visitor['tags'] = array_filter($tags, function ($tag) use ($visitor) {
      return $tag['visitor_id'] == $visitor['id'];
    });
    $visitor['groups'] = array_filter($groups, function ($group) use ($visitor) {
      return $group['visitor_id'] == $visitor['id'];
    });
    $visitor['visits'] = array_filter($visits, function ($visit) use ($visitor) {
      return $visit['visitor_id'] == $visitor['id'];
    });
    return $visitor;
  }, $visitors);

  closeConnection($pdo);
  return $visitors;
}

function addToVisitorGroup($id, $group)
{
  $originalGroup = $group;
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM groups WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $group]);
  $group = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$group) {
    $sql = "INSERT INTO groups (title) VALUES (:title)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['title' => ucfirst($originalGroup)]);
    $group['id'] = $pdo->lastInsertId();
  }

  $sql = 'SELECT * FROM visitors_groups WHERE visitor_id = :visitor_id AND group_id = :group_id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'visitor_id' => $id,
    'group_id' => $group['id']
  ]);
  $visitorGroup = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($visitorGroup) {
    closeConnection($pdo);
    return;
  }

  $sql = "INSERT INTO visitors_groups (visitor_id, group_id) VALUES (:visitor_id, :group_id)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['visitor_id' => $id, 'group_id' => $group['id']]);
  closeConnection($pdo);
}

function addToVisitorTag($id, $tag)
{
  $originalTag = $tag;
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM tags WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $tag]);
  $tag = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$tag) {
    $sql = "INSERT INTO tags (title) VALUES (:title)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['title' => ucfirst($originalTag)]);
    $tag['id'] = $pdo->lastInsertId();
  }

  $sql = 'SELECT * FROM visitors_tags WHERE visitor_id = :visitor_id AND tag_id = :tag_id';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    'visitor_id' => $id,
    'tag_id' => $tag['id']
  ]);
  $visitorTag = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($visitorTag) {
    closeConnection($pdo);
    return;
  }

  $sql = "INSERT INTO visitors_tags (visitor_id, tag_id) VALUES (:visitor_id, :tag_id)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['visitor_id' => $id, 'tag_id' => $tag['id']]);
  closeConnection($pdo);
}

function removeVisitorGroup($id, $group)
{
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM groups WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $group]);
  $groupRow = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$groupRow || !isset($groupRow['id'])) {
    closeConnection($pdo);
    return;
  }

  $sql = "DELETE FROM visitors_groups WHERE visitor_id = :visitor_id AND group_id = :group_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['visitor_id' => $id, 'group_id' => $groupRow['id']]);

  $sql = "SELECT * FROM visitors_groups WHERE group_id = :group_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['group_id' => $groupRow['id']]);
  $groupLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($groupLinks)) {
    $sql = "DELETE FROM groups WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $groupRow['id']]);
  }

  closeConnection($pdo);
}

function removeVisitorTag($id, $tag)
{
  $pdo = connectToDatabase();
  $sql = "SELECT * FROM tags WHERE LOWER(title) = LOWER(:title)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['title' => $tag]);
  $tagRow = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$tagRow || !isset($tagRow['id'])) {
    closeConnection($pdo);
    return;
  }

  $sql = "DELETE FROM visitors_tags WHERE visitor_id = :visitor_id AND tag_id = :tag_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['visitor_id' => $id, 'tag_id' => $tagRow['id']]);

  $sql = "SELECT * FROM visitors_tags WHERE tag_id = :tag_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['tag_id' => $tagRow['id']]);
  $tagLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($tagLinks)) {
    $sql = "DELETE FROM tags WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $tagRow['id']]);
  }

  closeConnection($pdo);
}

function formatVisitTime($dateTime)
{
  $visitTime = new DateTime($dateTime);
  $now = new DateTime();
  $interval = $now->diff($visitTime);

  if ($interval->y > 0) {
    return [
      'text' => $interval->y === 1 ? '1 year ago' : $interval->y . ' years ago',
      'color' => 'var(--text-color)'
    ];
  } elseif ($interval->m > 0 || $interval->d > 7) {
    return [
      'text' => $visitTime->format('d M H:i'),
      'color' => 'var(--button-primary-hover)'
    ];
  } elseif ($interval->d > 1) {
    return [
      'text' => $visitTime->format('D H:i'),
      'color' => 'var(--text-color)'
    ];
  } elseif ($interval->d === 1) {
    return [
      'text' => 'Yesterday, ' . $visitTime->format('H:i'),
      'color' => 'var(--text-color)'
    ];
  } elseif ($interval->h > 0) {
    return [
      'text' => 'Today, ' . $visitTime->format('H:i'),
      'color' => 'var(--primary-color)'
    ];
  } elseif ($interval->i > 10) {
    return [
      'text' => $interval->i . ' minutes ago',
      'color' => 'var(--primary-color)'
    ];
  } elseif ($interval->i > 0) {
    return [
      'text' => 'Last 10 minutes',
      'color' => 'var(--primary-color)'
    ];
  } else {
    return [
      'text' => 'Just now',
      'color' => '#00cd87'
    ];
  }
}
