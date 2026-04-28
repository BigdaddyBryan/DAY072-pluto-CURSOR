<?php
function getShortlink()
{
  $data = json_decode(file_get_contents("php://input"), true);
  if (!is_array($data)) {
    $data = [];
  }

  $shortlink = trim((string) ($data['shortlink'] ?? ''));
  $currentLinkId = isset($data['currentLinkId']) ? (int) $data['currentLinkId'] : 0;

  if ($shortlink === '') {
    return false;
  }

  if (!checkAdmin()) {
    echo json_encode(array('error' => 'You do not have permission to do this'));
    return;
  }

  $pdo = connectToDatabase();
  ensureLinkAliasesTable($pdo);

  $sql = 'SELECT id, shortlink FROM links WHERE REPLACE(LOWER(shortlink), "/", "") = REPLACE(LOWER(:shortlink), "/", "") LIMIT 1';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array(':shortlink' => $shortlink));
  $link = $stmt->fetch();

  if ($link && $currentLinkId > 0 && (int) ($link['id'] ?? 0) === $currentLinkId) {
    closeConnection($pdo);
    return false;
  }

  if ($link) {
    closeConnection($pdo);
    return [
      'conflict' => true,
      'conflict_type' => 'primary',
      'link_id' => (int) ($link['id'] ?? 0),
      'shortlink' => (string) ($link['shortlink'] ?? $shortlink),
    ];
  }

  if (!$link) {
    $sql = 'SELECT link_aliases.alias AS shortlink, link_aliases.link_id
          FROM link_aliases
          INNER JOIN links ON links.id = link_aliases.link_id
          WHERE REPLACE(LOWER(link_aliases.alias), "/", "") = REPLACE(LOWER(:shortlink), "/", "")
          LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':shortlink' => $shortlink));
    $link = $stmt->fetch();

    if ($link && $currentLinkId > 0 && (int) ($link['link_id'] ?? 0) === $currentLinkId) {
      closeConnection($pdo);
      return false;
    }

    if ($link) {
      closeConnection($pdo);
      return [
        'conflict' => true,
        'conflict_type' => 'alias',
        'link_id' => (int) ($link['link_id'] ?? 0),
        'shortlink' => (string) ($link['shortlink'] ?? $shortlink),
      ];
    }
  }

  closeConnection($pdo);
  return false;
}
