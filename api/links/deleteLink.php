<?php

function deleteLink($id)
{
    if (!checkAdmin()) {
        ob_start();
        header('Location: /');
        ob_end_flush();
        exit;
    }
    $pdo = connectToDatabase();

    $columnsSql = "PRAGMA table_info(archive)";
    $columnsStmt = $pdo->query($columnsSql);
    $archiveColumns = [];
    foreach ($columnsStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $archiveColumns[$column['name']] = true;
    }

    $ensureArchiveColumn = function ($name, $type = 'TEXT') use ($pdo, &$archiveColumns) {
        if (isset($archiveColumns[$name])) {
            return;
        }
        $pdo->exec("ALTER TABLE archive ADD COLUMN {$name} {$type}");
        $archiveColumns[$name] = true;
    };

    $ensureArchiveColumn('second_table_title', 'TEXT');
    $ensureArchiveColumn('link_status', 'INTEGER');
    $ensureArchiveColumn('link_visit_count', 'INTEGER');
    $ensureArchiveColumn('link_expires_at', 'TEXT');
    $ensureArchiveColumn('link_last_visited_at', 'TEXT');
    $ensureArchiveColumn('link_modifier', 'TEXT');
    $ensureArchiveColumn('link_modified_at_original', 'TEXT');

    $pdo->beginTransaction();

    try {

        $sql = "SELECT * FROM links WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$link) {
            $pdo->rollBack();
            closeConnection($pdo);
            return;
        }

        if (function_exists('ensureLinkAliasesTable')) {
            ensureLinkAliasesTable($pdo);
        }

        $sql = "SELECT id, alias, created_at, created_by FROM link_aliases WHERE link_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $aliases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($aliases as $aliasRow) {
            $sql = 'INSERT INTO archive (table_name, table_id, second_table_id, second_table_title, creator, created_at, modifier, modified_at)
                    VALUES (:table_name, :table_id, :second_table_id, :second_table_title, :creator, :created_at, :modifier, :modified_at)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'table_name' => 'link_aliases',
                'table_id' => $id,
                'second_table_id' => (int) ($aliasRow['id'] ?? 0),
                'second_table_title' => (string) ($aliasRow['alias'] ?? ''),
                'creator' => $aliasRow['created_by'] ?? ($link['creator'] ?? null),
                'created_at' => $aliasRow['created_at'] ?? ($link['created_at'] ?? date('Y-m-d H:i:s')),
                'modifier' => $_SESSION['user']['id'] ?? null,
                'modified_at' => date('Y-m-d H:i:s')
            ]);
        }

        $sql = "DELETE FROM link_aliases WHERE link_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $sql = "DELETE FROM links WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $sql = "SELECT * FROM link_tags WHERE link_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tags as $tag) {
            $tagTitle = null;
            $sql = "SELECT title FROM tags WHERE id = :tag_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['tag_id' => $tag['tag_id']]);
            $tagData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tagData) {
                $tagTitle = $tagData['title'];
            }

            $sql = "SELECT * FROM link_tags WHERE tag_id = :tag_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['tag_id' => $tag['tag_id']]);
            $tagLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql = 'INSERT INTO archive (table_name, table_id, second_table_id, second_table_title) VALUES (:table_name, :table_id, :second_table_id, :second_table_title)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'table_name' => 'link_tags',
                'table_id' => $id,
                'second_table_id' => $tag['tag_id'],
                'second_table_title' => $tagTitle
            ]);

            if (count($tagLinks) === 1) {
                // Fetch the tag details
                $sql = "SELECT * FROM tags WHERE id = :tag_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['tag_id' => $tag['tag_id']]);
                $archiveTag = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($archiveTag) {
                    // Delete the tag
                    $sql = "DELETE FROM tags WHERE id = :tag_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['tag_id' => $tag['tag_id']]);

                    // Insert into archive
                    $sql = "INSERT INTO archive (table_name, table_id, title) VALUES (:table_name, :table_id, :title)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'table_name' => 'tags',
                        'table_id' => $tag['tag_id'],
                        'title' => $archiveTag['title']
                    ]);
                }
            }
        }

        $sql = "DELETE FROM link_tags WHERE link_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $sql = "SELECT * FROM link_groups WHERE link_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($groups as $group) {
            $groupTitle = null;
            $sql = "SELECT title FROM groups WHERE id = :group_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['group_id' => $group['group_id']]);
            $groupData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($groupData) {
                $groupTitle = $groupData['title'];
            }

            $sql = "SELECT * FROM link_groups WHERE group_id = :group_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['group_id' => $group['group_id']]);
            $groupLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql = 'INSERT INTO archive (table_name, table_id, second_table_id, second_table_title) VALUES (:table_name, :table_id, :second_table_id, :second_table_title)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'table_name' => 'link_groups',
                'table_id' => $id,
                'second_table_id' => $group['group_id'],
                'second_table_title' => $groupTitle
            ]);

            if (count($groupLinks) === 1) {
                // Fetch the group details
                $sql = "SELECT * FROM groups WHERE id = :group_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['group_id' => $group['group_id']]);
                $archiveGroup = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($archiveGroup) {
                    // Delete the group
                    $sql = "DELETE FROM groups WHERE id = :group_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['group_id' => $group['group_id']]);

                    // Insert into archive
                    $sql = "INSERT INTO archive (table_name, table_id, title) VALUES (:table_name, :table_id, :title)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'table_name' => 'groups',
                        'table_id' => $group['group_id'],
                        'title' => $archiveGroup['title']
                    ]);
                }
            }
        }

        $sql = "DELETE FROM link_groups WHERE link_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $sql = "INSERT INTO archive (table_name, table_id, creator, created_at, modifier, modified_at, shortlink, url, title, link_status, link_visit_count, link_expires_at, link_last_visited_at, link_modifier, link_modified_at_original) VALUES (:table_name, :table_id, :creator, :created_at, :modifier, :modified_at, :shortlink, :url, :title, :link_status, :link_visit_count, :link_expires_at, :link_last_visited_at, :link_modifier, :link_modified_at_original)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'table_name' => 'links',
            'table_id' => $id,
            'creator' => $link['creator'],
            'created_at' => $link['created_at'],
            'modifier' => $_SESSION['user']['id'],
            'modified_at' => date('Y-m-d H:i:s'),
            'shortlink' => $link['shortlink'],
            'url' => $link['url'],
            'title' => $link['title'],
            'link_status' => isset($link['status']) ? (int) $link['status'] : 1,
            'link_visit_count' => isset($link['visit_count']) ? (int) $link['visit_count'] : 0,
            'link_expires_at' => $link['expires_at'] ?? null,
            'link_last_visited_at' => $link['last_visited_at'] ?? null,
            'link_modifier' => $link['modifier'] ?? null,
            'link_modified_at_original' => $link['modified_at'] ?? null
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        closeConnection($pdo);
        throw $e;
    }

    closeConnection($pdo);
}
