<?php

function dropdownNormalizePositiveIntList($values)
{
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('intval', $values), function ($id) {
        return $id > 0;
    })));
}

function dropdownResolveEntityIds(array $data, $comp, $type)
{
    $checked = isset($data['checked']) && is_array($data['checked'])
        ? dropdownNormalizePositiveIntList($data['checked'])
        : [];

    $superAll = isset($data['superall']) && is_array($data['superall'])
        ? $data['superall']
        : null;

    $superAllEnabled = $superAll && !empty($superAll['enabled']);
    if (!$superAllEnabled) {
        return $checked;
    }

    $selectionData = [
        'type' => $type,
        'ids' => $checked,
        'superall' => $superAll,
    ];

    if ($comp === 'links') {
        require_once __DIR__ . '/multiSelect.php';

        if (!function_exists('resolveSelectionIds')) {
            throw new RuntimeException('SuperAll selection service unavailable');
        }

        $selectionMeta = [];
        $resolved = resolveSelectionIds($selectionData, $selectionMeta);
        return dropdownNormalizePositiveIntList($resolved);
    }

    if ($comp === 'users') {
        require_once __DIR__ . '/../users/multiSelect.php';

        if (!function_exists('resolveUserSelectionIdsForMultiSelect')) {
            throw new RuntimeException('Users SuperAll selection service unavailable');
        }

        $selectionMeta = [];
        $resolved = resolveUserSelectionIdsForMultiSelect($selectionData, $selectionMeta);
        return dropdownNormalizePositiveIntList($resolved);
    }

    return $checked;
}

function dropdownFetchEntityTitles($pdo, $entityTable, $search)
{
    $sql = "SELECT {$entityTable}.title FROM {$entityTable} WHERE {$entityTable}.title LIKE :search ORDER BY {$entityTable}.title ASC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['search' => $search]);

    return array_map(function ($row) {
        return trim((string) ($row['title'] ?? ''));
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function dropdownFetchSelectedCountsByTitle(
    $pdo,
    $entityTable,
    $relationTable,
    $relationEntityColumn,
    $idColumn,
    array $entityIds,
    $search
) {
    $counts = [];
    if (empty($entityIds)) {
        return $counts;
    }

    foreach (array_chunk($entityIds, 400) as $chunk) {
        if (empty($chunk)) {
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = "SELECT {$entityTable}.title AS title, COUNT(DISTINCT {$relationTable}.{$idColumn}) AS selected_count
            FROM {$relationTable}
            INNER JOIN {$entityTable} ON {$entityTable}.id = {$relationTable}.{$relationEntityColumn}
            WHERE {$relationTable}.{$idColumn} IN ({$placeholders})
              AND {$entityTable}.title LIKE ?
            GROUP BY {$entityTable}.id, {$entityTable}.title";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($chunk, [$search]));

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $counts[$title] = (int) ($counts[$title] ?? 0) + (int) ($row['selected_count'] ?? 0);
        }
    }

    return $counts;
}

function multiSelectDropdown($data)
{
    if (!checkAdmin()) {
        http_response_code(401);
        return ['status' => 'error', 'message' => 'Unauthorized'];
    }

    $type = isset($data['type']) ? (string) $data['type'] : '';
    $value = isset($data['value']) ? trim((string) $data['value']) : '';
    $comp = isset($data['comp']) ? (string) $data['comp'] : 'links';
    $scope = isset($data['scope']) ? (string) $data['scope'] : 'union';
    $scope = $scope === 'intersection' ? 'intersection' : 'union';

    if ($type === '') {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Missing type'];
    }

    try {
        $entityIds = dropdownResolveEntityIds((array) $data, $comp, $type);
    } catch (LengthException $e) {
        http_response_code(422);
        return [
            'status' => 'error',
            'message' => 'Too many filtered results selected. Refine filters before continuing.',
        ];
    } catch (Throwable $e) {
        http_response_code(500);
        return ['status' => 'error', 'message' => 'Failed to resolve selection context'];
    }

    if (($type === 'tagDel' || $type === 'groupDel') && empty($entityIds)) {
        return [];
    }

    $pdo = connectToDatabase();
    $response = [];
    $search = '%' . $value . '%';
    $isVisitorsComp = $comp === 'visitors';
    $isUsersComp = $comp === 'users';
    $selectedTotal = count($entityIds);

    switch ($type) {
        case 'roleSet':
            if (!$isUsersComp) {
                http_response_code(400);
                $response = ['status' => 'error', 'message' => 'Unsupported component for roleSet'];
                break;
            }

            $actorRole = strtolower((string) ($_SESSION['user']['role'] ?? ''));
            $isSuperAdminActor = $actorRole === 'superadmin';
            $allowedRoles = $isSuperAdminActor
                ? ['viewer', 'limited', 'user', 'admin']
                : ['viewer', 'limited', 'user'];

            $countsByRole = [];
            if (!empty($entityIds)) {
                foreach (array_chunk($entityIds, 400) as $chunk) {
                    if (empty($chunk)) {
                        continue;
                    }

                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    $sql = "SELECT lower(trim(role)) AS role_key, COUNT(*) AS selected_count
                        FROM users
                        WHERE id IN ({$placeholders})
                        GROUP BY lower(trim(role))";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($chunk);

                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $roleKey = trim((string) ($row['role_key'] ?? ''));
                        if ($roleKey === '') {
                            continue;
                        }

                        $countsByRole[$roleKey] = (int) ($countsByRole[$roleKey] ?? 0) + (int) ($row['selected_count'] ?? 0);
                    }
                }
            }

            $normalizedValue = strtolower(trim($value));
            foreach ($allowedRoles as $roleKey) {
                if ($normalizedValue !== '' && strpos($roleKey, $normalizedValue) === false) {
                    continue;
                }

                $response[] = [
                    'title' => $roleKey,
                    'selected_count' => (int) ($countsByRole[$roleKey] ?? 0),
                ];
            }
            break;

        case 'tagSync':
            $relationTable = $isVisitorsComp ? 'visitors_tags' : 'link_tags';
            $idColumn = $isVisitorsComp ? 'visitor_id' : 'link_id';
            $titles = dropdownFetchEntityTitles($pdo, 'tags', $search);
            $countsByTitle = dropdownFetchSelectedCountsByTitle(
                $pdo,
                'tags',
                $relationTable,
                'tag_id',
                $idColumn,
                $entityIds,
                $search,
            );

            foreach ($titles as $title) {
                $response[] = [
                    'title' => $title,
                    'selected_count' => (int) ($countsByTitle[$title] ?? 0),
                ];
            }

            usort($response, function ($a, $b) {
                $countDiff = (int) ($b['selected_count'] ?? 0) - (int) ($a['selected_count'] ?? 0);
                if ($countDiff !== 0) {
                    return $countDiff;
                }

                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });
            break;

        case 'tagDel':
            $relationTable = $isVisitorsComp ? 'visitors_tags' : 'link_tags';
            $idColumn = $isVisitorsComp ? 'visitor_id' : 'link_id';
            $countsByTitle = dropdownFetchSelectedCountsByTitle(
                $pdo,
                'tags',
                $relationTable,
                'tag_id',
                $idColumn,
                $entityIds,
                $search,
            );

            foreach ($countsByTitle as $title => $selectedCount) {
                if ($selectedCount <= 0) {
                    continue;
                }

                if ($scope === 'intersection' && $selectedCount !== $selectedTotal) {
                    continue;
                }

                $response[] = [
                    'title' => $title,
                    'selected_count' => (int) $selectedCount,
                ];
            }

            usort($response, function ($a, $b) {
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });
            break;

        case 'tagAdd':
            $titles = dropdownFetchEntityTitles($pdo, 'tags', $search);
            $response = array_map(function ($title) {
                return ['title' => $title];
            }, $titles);
            break;

        case 'groupSync':
            $relationTable = $isVisitorsComp ? 'visitors_groups' : 'link_groups';
            $idColumn = $isVisitorsComp ? 'visitor_id' : 'link_id';
            $titles = dropdownFetchEntityTitles($pdo, 'groups', $search);
            $countsByTitle = dropdownFetchSelectedCountsByTitle(
                $pdo,
                'groups',
                $relationTable,
                'group_id',
                $idColumn,
                $entityIds,
                $search,
            );

            foreach ($titles as $title) {
                $response[] = [
                    'title' => $title,
                    'selected_count' => (int) ($countsByTitle[$title] ?? 0),
                ];
            }

            usort($response, function ($a, $b) {
                $countDiff = (int) ($b['selected_count'] ?? 0) - (int) ($a['selected_count'] ?? 0);
                if ($countDiff !== 0) {
                    return $countDiff;
                }

                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });
            break;

        case 'groupDel':
            $relationTable = $isVisitorsComp ? 'visitors_groups' : 'link_groups';
            $idColumn = $isVisitorsComp ? 'visitor_id' : 'link_id';
            $countsByTitle = dropdownFetchSelectedCountsByTitle(
                $pdo,
                'groups',
                $relationTable,
                'group_id',
                $idColumn,
                $entityIds,
                $search,
            );

            foreach ($countsByTitle as $title => $selectedCount) {
                if ($selectedCount <= 0) {
                    continue;
                }

                if ($scope === 'intersection' && $selectedCount !== $selectedTotal) {
                    continue;
                }

                $response[] = [
                    'title' => $title,
                    'selected_count' => (int) $selectedCount,
                ];
            }

            usort($response, function ($a, $b) {
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });
            break;

        case 'groupAdd':
            $titles = dropdownFetchEntityTitles($pdo, 'groups', $search);
            $response = array_map(function ($title) {
                return ['title' => $title];
            }, $titles);
            break;

        default:
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Unsupported type'];
            break;
    }

    closeConnection($pdo);
    return $response;
}
