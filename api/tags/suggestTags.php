<?php

function suggestTags($postData)
{
  if (!checkUser()) {
    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }

  $query = trim((string)($postData['query'] ?? ''));
  $titleContext = trim((string)($postData['title'] ?? ''));
  $urlContext = trim((string)($postData['url'] ?? ''));

  if ($query === '' && $titleContext !== '') {
    $query = $titleContext;
  }

  if ($query === '' && $urlContext !== '') {
    $parsedHost = '';
    $parsedPath = '';
    $parsed = @parse_url($urlContext);
    if (is_array($parsed)) {
      $parsedHost = strtolower(trim((string)($parsed['host'] ?? '')));
      $parsedPath = strtolower(trim((string)($parsed['path'] ?? '')));
    }

    $query = trim($parsedHost . ' ' . $parsedPath);
    if ($query === '') {
      $query = $urlContext;
    }
  }
  $selectedTags = $postData['selectedTags'] ?? [];
  if (!is_array($selectedTags)) {
    $selectedTags = [];
  }

  $selectedTags = array_values(array_filter(array_map(function ($tag) {
    return trim((string)$tag);
  }, $selectedTags), function ($tag) {
    return $tag !== '';
  }));

  $pdo = connectToDatabase();

  $results = [];
  $seen = [];

  $addTitle = function ($title) use (&$results, &$seen, $selectedTags) {
    $title = trim((string)$title);
    if ($title === '') {
      return;
    }

    foreach ($selectedTags as $selectedTag) {
      if (strcasecmp($selectedTag, $title) === 0) {
        return;
      }
    }

    $key = strtolower($title);
    if (isset($seen[$key])) {
      return;
    }

    $seen[$key] = true;
    $results[] = $title;
  };

  if ($query !== '') {
    $stmt = $pdo->prepare(
      'SELECT title FROM tags WHERE LOWER(title) LIKE LOWER(:query) ORDER BY title LIMIT 8'
    );
    $stmt->execute(['query' => '%' . $query . '%']);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $addTitle($row['title'] ?? '');
    }

    // Also infer suggestions from links that match the typed context
    // (title/url/shortlink), then surface their associated tags.
    $stmt = $pdo->prepare(
      'SELECT id FROM links
       WHERE LOWER(title) LIKE LOWER(:query)
          OR LOWER(url) LIKE LOWER(:query)
          OR LOWER(shortlink) LIKE LOWER(:query)
       ORDER BY id DESC
       LIMIT 60'
    );
    $stmt->execute(['query' => '%' . $query . '%']);
    $matchingLinkIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));

    if (count($matchingLinkIds) > 0) {
      $placeholders = implode(',', array_fill(0, count($matchingLinkIds), '?'));
      $sql = 'SELECT t.title, COUNT(*) AS weight
              FROM link_tags lt
              INNER JOIN tags t ON t.id = lt.tag_id
              WHERE lt.link_id IN (' . $placeholders . ')
              GROUP BY t.id, t.title
              ORDER BY weight DESC, t.title ASC
              LIMIT 12';
      $stmt = $pdo->prepare($sql);
      $stmt->execute($matchingLinkIds);

      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $addTitle($row['title'] ?? '');
      }
    }
  }

  $seedIds = [];

  if ($query !== '') {
    $stmt = $pdo->prepare(
      'SELECT id FROM tags WHERE LOWER(title) LIKE LOWER(:query) LIMIT 30'
    );
    $stmt->execute(['query' => '%' . $query . '%']);
    $seedIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
  }

  if (count($seedIds) === 0 && count($selectedTags) > 0) {
    $placeholders = implode(',', array_fill(0, count($selectedTags), '?'));
    $sql = 'SELECT id FROM tags WHERE LOWER(title) IN (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_map('strtolower', $selectedTags));
    $seedIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
  }

  if (count($seedIds) > 0) {
    $seedPlaceholder = implode(',', array_fill(0, count($seedIds), '?'));
    $sql = 'SELECT t2.title, COUNT(*) AS weight
            FROM link_tags lt1
            INNER JOIN link_tags lt2 ON lt1.link_id = lt2.link_id
            INNER JOIN tags t2 ON t2.id = lt2.tag_id
            WHERE lt1.tag_id IN (' . $seedPlaceholder . ')
              AND lt2.tag_id NOT IN (' . $seedPlaceholder . ')
            GROUP BY t2.id, t2.title
            ORDER BY weight DESC, t2.title ASC
            LIMIT 16';

    $params = array_merge($seedIds, $seedIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $addTitle($row['title'] ?? '');
    }
  }

  if (count($results) < 10 && $query === '' && count($selectedTags) === 0) {
    $stmt = $pdo->query(
      'SELECT t.title, COUNT(lt.tag_id) AS usage_count
       FROM tags t
       LEFT JOIN link_tags lt ON lt.tag_id = t.id
       GROUP BY t.id, t.title
       ORDER BY usage_count DESC, t.title ASC
       LIMIT 10'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $addTitle($row['title'] ?? '');
    }
  }

  closeConnection($pdo);

  return ['tags' => array_slice($results, 0, 12)];
}
