<?php

function normalizeUserMultiRoleValue($value)
{
    $role = strtolower(trim((string) $value));
    return in_array($role, ['viewer', 'limited', 'user', 'admin'], true)
        ? $role
        : '';
}

function resolveUserMultiRoleTarget(array $data)
{
    $values = isset($data['values']) && is_array($data['values'])
        ? $data['values']
        : [];

    if (!empty($values)) {
        return normalizeUserMultiRoleValue($values[0]);
    }

    return normalizeUserMultiRoleValue($data['input'] ?? '');
}

function normalizeUserMultiPositiveIntList($values)
{
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('intval', $values), function ($id) {
        return $id > 0;
    })));
}

function buildUserSuperAllFilter(array $filter)
{
    $normalizeStringList = function ($values) {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $values), function ($value) {
            return $value !== '';
        }), SORT_STRING));
    };

    return [
        'tags' => $normalizeStringList($filter['tags'] ?? []),
        'groups' => $normalizeStringList($filter['groups'] ?? []),
        'roles' => $normalizeStringList($filter['roles'] ?? []),
        'sort' => isset($filter['sort']) ? (string) $filter['sort'] : 'latest_modified',
        'limit' => 100,
        'offset' => 0,
        'search' => isset($filter['search']) ? trim((string) $filter['search']) : '',
        'searchType' => isset($filter['searchType']) ? (string) $filter['searchType'] : 'all',
        '_skipPreferencePersist' => true,
    ];
}

function collectFilteredUserIdsForSuperAll(array $filter, $hardCap = 5000)
{
    require_once __DIR__ . '/filterUsers.php';

    if (!function_exists('getFilteredUsers')) {
        throw new RuntimeException('User filter service unavailable');
    }

    $queryFilter = buildUserSuperAllFilter($filter);
    $result = getFilteredUsers($queryFilter);
    if (!is_array($result)) {
        throw new RuntimeException('Invalid user filter response');
    }

    $total = isset($result['total']) ? (int) $result['total'] : 0;
    if ($total <= 0) {
        return [];
    }

    if ($total > $hardCap) {
        throw new LengthException('Too many filtered users selected');
    }

    $ids = [];
    $seen = [];
    $limit = max(1, (int) ($queryFilter['limit'] ?? 100));
    $maxPasses = (int) ceil($total / $limit) + 2;
    $pass = 0;

    while ($pass < $maxPasses) {
        $pass++;
        $rows = isset($result['users']) && is_array($result['users'])
            ? $result['users']
            : [];

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $ids[] = $id;

            if (count($ids) > $hardCap) {
                throw new LengthException('Too many filtered users selected');
            }
        }

        if (empty($rows) || count($ids) >= $total) {
            break;
        }

        $queryFilter['offset'] = ((int) $queryFilter['offset']) + $limit;
        $result = getFilteredUsers($queryFilter);
        if (!is_array($result)) {
            break;
        }
    }

    return $ids;
}

function resolveUserSelectionIdsForMultiSelect(array $data, array &$meta)
{
    $meta = [
        'superallApplied' => false,
        'excludedCount' => 0,
        'filteredCount' => 0,
    ];

    $ids = normalizeUserMultiPositiveIntList($data['ids'] ?? []);

    $superAll = isset($data['superall']) && is_array($data['superall'])
        ? $data['superall']
        : null;

    $superAllEnabled = $superAll && !empty($superAll['enabled']);
    if (!$superAllEnabled) {
        if (empty($ids)) {
            throw new InvalidArgumentException('No valid ids provided');
        }

        return $ids;
    }

    $meta['superallApplied'] = true;

    $filter = isset($superAll['filter']) && is_array($superAll['filter'])
        ? $superAll['filter']
        : [];

    $excludedIds = normalizeUserMultiPositiveIntList($superAll['excludedIds'] ?? []);
    $meta['excludedCount'] = count($excludedIds);

    $ids = collectFilteredUserIdsForSuperAll($filter, 5000);
    $meta['filteredCount'] = count($ids);

    if (!empty($excludedIds)) {
        $excludedMap = array_fill_keys($excludedIds, true);
        $ids = array_values(array_filter($ids, function ($id) use ($excludedMap) {
            return !isset($excludedMap[$id]);
        }));
    }

    return $ids;
}

function handleMultiSelect()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        include __DIR__ . '/../../pages/errors/404.php';
        return;
    }

    if (!checkAdmin()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $data = $payload['data'] ?? null;

    if (!$data || !isset($data['type'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    try {
        $selectionMeta = [];
        $ids = resolveUserSelectionIdsForMultiSelect((array) $data, $selectionMeta);
    } catch (LengthException $e) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Too many filtered results selected. Refine filters before continuing.',
        ]);
        return;
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        return;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to resolve selected users']);
        return;
    }

    $type = (string) $data['type'];
    $actorId = (int) ($_SESSION['user']['id'] ?? 0);
    $actorRole = (string) ($_SESSION['user']['role'] ?? '');
    $isSuperAdmin = $actorRole === 'superadmin';
    $responseMeta = [
        'superallApplied' => !empty($selectionMeta['superallApplied']),
        'superallExcludedCount' => (int) ($selectionMeta['excludedCount'] ?? 0),
        'superallFilteredCount' => (int) ($selectionMeta['filteredCount'] ?? 0),
    ];

    if (empty($ids) && !$responseMeta['superallApplied']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No valid ids provided']);
        return;
    }

    // Remove own account from destructive operations
    $safeIds = array_values(array_filter($ids, function ($id) use ($actorId) {
        return $id !== $actorId;
    }));

    try {
        switch ($type) {
            case 'delete':
                if (empty($safeIds)) {
                    echo json_encode(array_merge(['status' => 'success', 'affectedCount' => 0], $responseMeta));
                    return;
                }

                include_once __DIR__ . '/deleteUser.php';

                $pdo = connectToDatabase();
                $deletedCount = 0;

                // Admins may only delete regular users; superadmin may delete admins too
                foreach ($safeIds as $id) {
                    // Fetch role of target user to enforce permission boundary
                    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => $id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        continue;
                    }
                    $targetRole = (string) ($row['role'] ?? 'user');

                    // Admins cannot delete other admins or superadmins
                    if (!$isSuperAdmin && in_array($targetRole, ['admin', 'superadmin'], true)) {
                        continue;
                    }

                    deleteUser($id);
                    $deletedCount++;
                }

                closeConnection($pdo);
                echo json_encode(array_merge(['status' => 'success', 'affectedCount' => $deletedCount], $responseMeta));
                break;

            case 'logout':
                if (!$isSuperAdmin) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
                    return;
                }

                $total = 0;
                foreach ($safeIds as $id) {
                    $total += revokeAllUserDeviceSessions((string) $id);
                }

                echo json_encode(array_merge(['status' => 'success', 'affectedCount' => $total], $responseMeta));
                break;

            case 'roleSet':
                $targetRole = resolveUserMultiRoleTarget((array) $data);
                if ($targetRole === '') {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid role target']);
                    return;
                }

                // Only superadmin may set admin role in bulk.
                if ($targetRole === 'admin' && !$isSuperAdmin) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
                    return;
                }

                if (empty($safeIds)) {
                    echo json_encode(array_merge(['status' => 'success', 'affectedCount' => 0], $responseMeta));
                    return;
                }

                $pdo = connectToDatabase();
                $fetchStmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
                $updateStmt = $pdo->prepare('UPDATE users SET role = :role, modifier = :modifier, modified_at = :modified_at WHERE id = :id');

                $affectedCount = 0;
                foreach ($safeIds as $id) {
                    $fetchStmt->execute([':id' => $id]);
                    $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        continue;
                    }

                    $currentRole = strtolower(trim((string) ($row['role'] ?? 'user')));

                    // Non-superadmins may not change admin/superadmin accounts.
                    if (!$isSuperAdmin && in_array($currentRole, ['admin', 'superadmin'], true)) {
                        continue;
                    }

                    // Do not allow role mutation for superadmin accounts via bulk actions.
                    if ($currentRole === 'superadmin') {
                        continue;
                    }

                    if ($currentRole === $targetRole) {
                        continue;
                    }

                    $updateStmt->execute([
                        ':role' => $targetRole,
                        ':modifier' => $actorId,
                        ':modified_at' => date('Y-m-d H:i:s'),
                        ':id' => $id,
                    ]);

                    if ($updateStmt->rowCount() > 0) {
                        $affectedCount++;
                    }
                }

                closeConnection($pdo);
                echo json_encode(array_merge(['status' => 'success', 'affectedCount' => $affectedCount], $responseMeta));
                break;

            default:
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Unsupported action']);
                break;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Multi-select action failed']);
    }
}
