<?php
function fetchRelatedTags($postData)
{

  checkUser();

  $tags = $postData['tags'];
  $groups = $postData['groups'];

  $pdo = connectToDatabase();

  $groupPlaceholders = implode(',', array_fill(0, count($groups), '?'));
  $tagPlaceholders = implode(',', array_fill(0, count($tags), '?'));
  $groupCount = count($groups);
  $tagCount = count($tags);

  // Query to fetch corresponding groups based on provided groups
  $sqlGroupsFromGroups = "SELECT g2.title 
                        FROM groups g1
                        INNER JOIN link_groups lg1 ON g1.id = lg1.group_id
                        INNER JOIN link_groups lg2 ON lg1.link_id = lg2.link_id
                        INNER JOIN groups g2 ON lg2.group_id = g2.id
                        WHERE g1.title IN ($groupPlaceholders)
                        GROUP BY g2.title
                        HAVING COUNT(DISTINCT g1.title) = ? ORDER BY g2.title";

  // Query to fetch corresponding groups based on provided tags
  $sqlGroupsFromTags = "SELECT g2.title 
                      FROM tags t1
                      INNER JOIN link_tags lt1 ON t1.id = lt1.tag_id
                      INNER JOIN link_groups lg1 ON lt1.link_id = lg1.link_id
                      INNER JOIN groups g2 ON lg1.group_id = g2.id
                      WHERE t1.title IN ($tagPlaceholders)
                      GROUP BY g2.title
                      HAVING COUNT(DISTINCT t1.title) = ? ORDER BY g2.title";

  // Query to fetch corresponding tags based on provided groups
  $sqlTagsFromGroups = "SELECT t2.title 
                      FROM groups g1
                      INNER JOIN link_groups lg1 ON g1.id = lg1.group_id
                      INNER JOIN link_tags lt1 ON lg1.link_id = lt1.link_id
                      INNER JOIN tags t2 ON lt1.tag_id = t2.id
                      WHERE g1.title IN ($groupPlaceholders)
                      GROUP BY t2.title
                      HAVING COUNT(DISTINCT g1.title) = ? ORDER BY t2.title";

  // Query to fetch corresponding tags based on provided tags
  $sqlTagsFromTags = "SELECT t2.title 
                    FROM tags t1
                    INNER JOIN link_tags lt1 ON t1.id = lt1.tag_id
                    INNER JOIN link_tags lt2 ON lt1.link_id = lt2.link_id
                    INNER JOIN tags t2 ON lt2.tag_id = t2.id
                    WHERE t1.title IN ($tagPlaceholders)
                    GROUP BY t2.title
                    HAVING COUNT(DISTINCT t1.title) = ? ORDER BY t2.title";

  // Prepare and execute the statements
  $stmtGroupsFromGroups = $pdo->prepare($sqlGroupsFromGroups);
  $stmtGroupsFromGroups->execute(array_merge($groups, [$groupCount]));
  $resultGroupsFromGroups = $stmtGroupsFromGroups->fetchAll(PDO::FETCH_ASSOC);

  $stmtGroupsFromTags = $pdo->prepare($sqlGroupsFromTags);
  $stmtGroupsFromTags->execute(array_merge($tags, [$tagCount]));
  $resultGroupsFromTags = $stmtGroupsFromTags->fetchAll(PDO::FETCH_ASSOC);

  $stmtTagsFromGroups = $pdo->prepare($sqlTagsFromGroups);
  $stmtTagsFromGroups->execute(array_merge($groups, [$groupCount]));
  $resultTagsFromGroups = $stmtTagsFromGroups->fetchAll(PDO::FETCH_ASSOC);

  $stmtTagsFromTags = $pdo->prepare($sqlTagsFromTags);
  $stmtTagsFromTags->execute(array_merge($tags, [$tagCount]));
  $resultTagsFromTags = $stmtTagsFromTags->fetchAll(PDO::FETCH_ASSOC);

  // Combine and remove duplicates
  $groups = array_values(array_unique(array_merge(
    array_column($resultGroupsFromGroups, 'title'),
    array_column($resultGroupsFromTags, 'title')
  )));

  $tags = array_values(array_unique(array_merge(
    array_column($resultTagsFromGroups, 'title'),
    array_column($resultTagsFromTags, 'title')
  )));

  // Combine results
  $result = [
    'groups' => $groups,
    'tags' => $tags
  ];

  // Close the database connection
  closeConnection($pdo);

  return $result;
}


function fetchRelatedVisitorTags($postData)
{

  checkUser();

  $tags = $postData['tags'];
  $groups = $postData['groups'];

  $pdo = connectToDatabase();

  $groupPlaceholders = implode(',', array_fill(0, count($groups), '?'));
  $tagPlaceholders = implode(',', array_fill(0, count($tags), '?'));
  $groupCount = count($groups);
  $tagCount = count($tags);

  // Query to fetch corresponding groups based on provided groups
  $sqlGroupsFromGroups = "SELECT g2.title 
                        FROM groups g1
                        INNER JOIN visitors_groups lg1 ON g1.id = lg1.group_id
                        INNER JOIN visitors_groups lg2 ON lg1.visitor_id = lg2.visitor_id
                        INNER JOIN groups g2 ON lg2.group_id = g2.id
                        WHERE g1.title IN ($groupPlaceholders)
                        GROUP BY g2.title
                        HAVING COUNT(DISTINCT g1.title) = ? ORDER BY g2.title";

  // Query to fetch corresponding groups based on provided tags
  $sqlGroupsFromTags = "SELECT g2.title 
                      FROM tags t1
                      INNER JOIN visitors_tags lt1 ON t1.id = lt1.tag_id
                      INNER JOIN visitors_groups lg1 ON lt1.visitor_id = lg1.visitor_id
                      INNER JOIN groups g2 ON lg1.group_id = g2.id
                      WHERE t1.title IN ($tagPlaceholders)
                      GROUP BY g2.title
                      HAVING COUNT(DISTINCT t1.title) = ? ORDER BY g2.title";

  // Query to fetch corresponding tags based on provided groups
  $sqlTagsFromGroups = "SELECT t2.title 
                      FROM groups g1
                      INNER JOIN visitors_groups lg1 ON g1.id = lg1.group_id
                      INNER JOIN visitors_tags lt1 ON lg1.visitor_id = lt1.visitor_id
                      INNER JOIN tags t2 ON lt1.tag_id = t2.id
                      WHERE g1.title IN ($groupPlaceholders)
                      GROUP BY t2.title
                      HAVING COUNT(DISTINCT g1.title) = ? ORDER BY t2.title";

  // Query to fetch corresponding tags based on provided tags
  $sqlTagsFromTags = "SELECT t2.title 
                    FROM tags t1
                    INNER JOIN visitors_tags lt1 ON t1.id = lt1.tag_id
                    INNER JOIN visitors_tags lt2 ON lt1.visitor_id = lt2.visitor_id
                    INNER JOIN tags t2 ON lt2.tag_id = t2.id
                    WHERE t1.title IN ($tagPlaceholders)
                    GROUP BY t2.title
                    HAVING COUNT(DISTINCT t1.title) = ? ORDER BY t2.title";

  // Prepare and execute the statements
  $stmtGroupsFromGroups = $pdo->prepare($sqlGroupsFromGroups);
  $stmtGroupsFromGroups->execute(array_merge($groups, [$groupCount]));
  $resultGroupsFromGroups = $stmtGroupsFromGroups->fetchAll(PDO::FETCH_ASSOC);

  $stmtGroupsFromTags = $pdo->prepare($sqlGroupsFromTags);
  $stmtGroupsFromTags->execute(array_merge($tags, [$tagCount]));
  $resultGroupsFromTags = $stmtGroupsFromTags->fetchAll(PDO::FETCH_ASSOC);

  $stmtTagsFromGroups = $pdo->prepare($sqlTagsFromGroups);
  $stmtTagsFromGroups->execute(array_merge($groups, [$groupCount]));
  $resultTagsFromGroups = $stmtTagsFromGroups->fetchAll(PDO::FETCH_ASSOC);

  $stmtTagsFromTags = $pdo->prepare($sqlTagsFromTags);
  $stmtTagsFromTags->execute(array_merge($tags, [$tagCount]));
  $resultTagsFromTags = $stmtTagsFromTags->fetchAll(PDO::FETCH_ASSOC);

  // Combine and remove duplicates
  $groups = array_values(array_unique(array_merge(
    array_column($resultGroupsFromGroups, 'title'),
    array_column($resultGroupsFromTags, 'title')
  )));

  $tags = array_values(array_unique(array_merge(
    array_column($resultTagsFromGroups, 'title'),
    array_column($resultTagsFromTags, 'title')
  )));

  // Combine results
  $result = [
    'groups' => $groups,
    'tags' => $tags
  ];

  // Close the database connection
  closeConnection($pdo);

  return $result;
}
