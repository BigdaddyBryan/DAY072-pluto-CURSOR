<?php

function restoreLink($id, array $options = [])
{

  $suppressRedirect = !empty($options['suppressRedirect']);

  $redirectWithNotice = function ($message, $type = 'info', $location = '/') use ($suppressRedirect) {
    if ($suppressRedirect) {
      return [
        'success' => $type !== 'error',
        'message' => (string) $message,
        'type' => (string) $type,
        'location' => (string) $location,
      ];
    }

    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $_SESSION['ui_notice'] = $message;
    $_SESSION['ui_notice_type'] = $type;
    header('Location: ' . $location);
    exit;
  };

  if (!checkAdmin()) {
    if ($suppressRedirect) {
      return [
        'success' => false,
        'message' => 'Unauthorized',
        'type' => 'error',
        'location' => '/',
      ];
    }

    ob_start();
    header('Location: /');
    ob_end_flush();
    exit;
  }


  $pdo = connectToDatabase();

  $archiveColumnsStmt = $pdo->query("PRAGMA table_info(archive)");
  $archiveColumns = [];
  foreach ($archiveColumnsStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $archiveColumns[$column['name']] = true;
  }
  $hasArchiveSecondTitle = isset($archiveColumns['second_table_title']);
  $hasArchiveLinkStatus = isset($archiveColumns['link_status']);
  $hasArchiveLinkVisitCount = isset($archiveColumns['link_visit_count']);
  $hasArchiveLinkExpiresAt = isset($archiveColumns['link_expires_at']);
  $hasArchiveLinkLastVisitedAt = isset($archiveColumns['link_last_visited_at']);
  $hasArchiveLinkModifier = isset($archiveColumns['link_modifier']);
  $hasArchiveLinkModifiedOriginal = isset($archiveColumns['link_modified_at_original']);

  $linksColumnsStmt = $pdo->query("PRAGMA table_info(links)");
  $linksColumns = [];
  foreach ($linksColumnsStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $linksColumns[$column['name']] = true;
  }

  $pdo->beginTransaction();

  try {

    // Fetch the link from the archive
    $sql = "SELECT * FROM archive WHERE table_id = :id AND table_name = 'links'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link) {
      $pdo->rollBack();
      closeConnection($pdo);
      return $redirectWithNotice('Restore failed: archive entry not found.', 'error');
    }

    $sql = "SELECT * FROM links WHERE shortlink = :shortlink";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['shortlink' => $link['shortlink']]);
    $existingLink = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingLink) {
      $pdo->rollBack();
      closeConnection($pdo);
      return $redirectWithNotice('Restore skipped: a link with this shortlink already exists.', 'error');
    }

    $sql = "SELECT count(*) as count FROM visits WHERE link_id = :link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['link_id' => $id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "SELECT MAX(date) as last_visited_at FROM visits WHERE link_id = :link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['link_id' => $id]);
    $lastVisit = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastVisitedAt = $lastVisit ? ($lastVisit['last_visited_at'] ?? null) : null;

    $insertColumns = ['title', 'url', 'shortlink', 'creator', 'created_at', 'modifier', 'modified_at', 'visit_count', 'status'];
    $insertParams = [
      'title' => $link['title'],
      'url' => $link['url'],
      'shortlink' => $link['shortlink'],
      'creator' => $link['creator'],
      'created_at' => $link['created_at'],
      'modifier' => $hasArchiveLinkModifier ? ($link['link_modifier'] ?? $_SESSION['user']['id']) : $_SESSION['user']['id'],
      'modified_at' => $hasArchiveLinkModifiedOriginal ? ($link['link_modified_at_original'] ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s'),
      'visit_count' => $hasArchiveLinkVisitCount ? ((int) ($link['link_visit_count'] ?? 0)) : (int) ($count['count'] ?? 0),
      'status' => $hasArchiveLinkStatus ? ((int) ($link['link_status'] ?? 1)) : 1
    ];

    if (isset($linksColumns['expires_at'])) {
      $insertColumns[] = 'expires_at';
      $insertParams['expires_at'] = $hasArchiveLinkExpiresAt ? ($link['link_expires_at'] ?? null) : null;
    }

    if (isset($linksColumns['last_visited_at'])) {
      $insertColumns[] = 'last_visited_at';
      $insertParams['last_visited_at'] = $hasArchiveLinkLastVisitedAt ? ($link['link_last_visited_at'] ?? $lastVisitedAt) : $lastVisitedAt;
    }

    $placeholders = array_map(function ($column) {
      return ':' . $column;
    }, $insertColumns);

    // Restore the link
    $sql = "INSERT INTO links (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($insertParams);

    $linkId = $pdo->lastInsertId();

    $sql = "SELECT id FROM visits WHERE link_id = :link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['link_id' => $link['id']]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($visits as $visit) {
      $sql = "UPDATE visits SET link_id = :link_id WHERE id = :id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'link_id' => $linkId,
        'id' => $visit['id']
      ]);
    }

    if (function_exists('ensureLinkAliasesTable')) {
      ensureLinkAliasesTable($pdo);
    }

    $sql = "SELECT * FROM archive WHERE table_name = 'link_aliases' AND table_id = :link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['link_id' => $link['table_id']]);
    $archivedAliases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($archivedAliases as $archivedAlias) {
      $aliasValue = trim((string) ($archivedAlias['second_table_title'] ?? ''));
      if ($aliasValue === '') {
        continue;
      }

      $sql = "SELECT id
              FROM links
              WHERE REPLACE(LOWER(shortlink), '/', '') = REPLACE(LOWER(:shortlink), '/', '')
                AND id != :id
              LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'shortlink' => $aliasValue,
        'id' => $linkId
      ]);
      $primaryConflict = $stmt->fetch(PDO::FETCH_ASSOC);

      $sql = "SELECT link_aliases.id, link_aliases.link_id
              FROM link_aliases
              INNER JOIN links ON links.id = link_aliases.link_id
              WHERE REPLACE(LOWER(link_aliases.alias), '/', '') = REPLACE(LOWER(:alias), '/', '')
              LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['alias' => $aliasValue]);
      $aliasConflict = $stmt->fetch(PDO::FETCH_ASSOC);

      $aliasUsedByOtherLink = $aliasConflict && (int) ($aliasConflict['link_id'] ?? 0) !== (int) $linkId;

      if (!$primaryConflict && !$aliasUsedByOtherLink) {
        $createdBy = isset($archivedAlias['creator']) && is_numeric((string) $archivedAlias['creator'])
          ? (int) $archivedAlias['creator']
          : null;

        $sql = "INSERT OR IGNORE INTO link_aliases (link_id, alias, created_at, created_by)
                VALUES (:link_id, :alias, :created_at, :created_by)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          'link_id' => $linkId,
          'alias' => $aliasValue,
          'created_at' => $archivedAlias['created_at'] ?? date('Y-m-d H:i:s'),
          'created_by' => $createdBy
        ]);
      }
    }

    // Restore associated groups
    $sql = "SELECT * FROM archive WHERE table_name = 'link_groups' AND table_id = :link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['link_id' => $link['table_id']]);
    $linkGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($linkGroups as $linkGroup) {
      $groupId = (int) $linkGroup['second_table_id'];
      $groupTitle = '';
      if ($hasArchiveSecondTitle) {
        $groupTitle = trim((string) ($linkGroup['second_table_title'] ?? ''));
      }

      if ($groupTitle === '') {
        $sql = "SELECT title FROM archive WHERE table_name = 'groups' AND table_id = :id ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $groupId]);
        $groupArchiveEntry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($groupArchiveEntry) {
          $groupTitle = trim((string) ($groupArchiveEntry['title'] ?? ''));
        }
      }

      // Check if the group exists
      $sql = "SELECT * FROM groups WHERE id = :id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['id' => $groupId]);
      $group = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$group && $groupTitle !== '') {
        // Insert the group if it doesn't exist
        $sql = "INSERT INTO groups (id, title) VALUES (:id, :title)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          'id' => $groupId,
          'title' => $groupTitle
        ]);
      }

      // Link the group to the link
      $sql = "SELECT 1 FROM link_groups WHERE link_id = :link_id AND group_id = :group_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'link_id' => $linkId,
        'group_id' => $groupId
      ]);
      $existingLinkGroup = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$existingLinkGroup) {
        $sql = "INSERT INTO link_groups (link_id, group_id) VALUES (:link_id, :group_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          'link_id' => $linkId,
          'group_id' => $groupId
        ]);
      }

      if ($groupId > 0) {
        $sql = "DELETE FROM archive WHERE table_name = 'groups' AND table_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $groupId]);
      }
    }

    // Restore associated tags
    $sql = "SELECT * FROM archive WHERE table_name = 'link_tags' AND table_id = :link_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['link_id' => $link['table_id']]);
    $linkTags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($linkTags as $linkTag) {
      $tagId = (int) $linkTag['second_table_id'];
      $tagTitle = '';
      if ($hasArchiveSecondTitle) {
        $tagTitle = trim((string) ($linkTag['second_table_title'] ?? ''));
      }

      if ($tagTitle === '') {
        $sql = "SELECT title FROM archive WHERE table_name = 'tags' AND table_id = :id ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $tagId]);
        $tagArchiveEntry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tagArchiveEntry) {
          $tagTitle = trim((string) ($tagArchiveEntry['title'] ?? ''));
        }
      }

      // Check if the tag exists
      $sql = "SELECT * FROM tags WHERE id = :id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['id' => $tagId]);
      $tag = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$tag && $tagTitle !== '') {
        // Insert the tag if it doesn't exist
        $sql = "INSERT INTO tags (id, title) VALUES (:id, :title)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          'id' => $tagId,
          'title' => $tagTitle
        ]);
      }

      // Link the tag to the link
      $sql = "SELECT 1 FROM link_tags WHERE link_id = :link_id AND tag_id = :tag_id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'link_id' => $linkId,
        'tag_id' => $tagId
      ]);
      $existingLinkTag = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$existingLinkTag) {
        $sql = "INSERT INTO link_tags (link_id, tag_id) VALUES (:link_id, :tag_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          'link_id' => $linkId,
          'tag_id' => $tagId
        ]);
      }

      if ($tagId > 0) {
        $sql = "DELETE FROM archive WHERE table_name = 'tags' AND table_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $tagId]);
      }
    }

    // Remove the link from the archive
    $sql = "SELECT * FROM archive WHERE table_id = :table_id AND table_name IN ('links', 'link_tags', 'link_groups', 'link_aliases')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':table_id' => $id]);
    $archive = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($archive as $entry) {
      $sql = "DELETE FROM archive WHERE id = :id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['id' => $entry['id']]);
    }

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log('restoreLink failed for id ' . $id . ': ' . $e->getMessage());
    closeConnection($pdo);
    return $redirectWithNotice('Restore failed due to a server error.', 'error');
  }

  closeConnection($pdo);
  return $redirectWithNotice('Link restored successfully.', 'success');
}
