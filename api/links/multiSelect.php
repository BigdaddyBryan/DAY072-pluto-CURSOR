<?php

function normalizeStringList($values)
{
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map(function ($value) {
        return trim((string) $value);
    }, $values), function ($value) {
        return $value !== '';
    }), SORT_STRING));
}

function normalizePositiveIntList($values)
{
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('intval', $values), function ($id) {
        return $id > 0;
    })));
}

function storeMultiSelectUndoToken(array $payload)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['multiSelectUndoTokens']) || !is_array($_SESSION['multiSelectUndoTokens'])) {
        $_SESSION['multiSelectUndoTokens'] = [];
    }

    $tokens = $_SESSION['multiSelectUndoTokens'];
    $now = time();
    $ttlSeconds = 7200;

    foreach ($tokens as $token => $entry) {
        $createdAt = isset($entry['created_at']) ? (int) $entry['created_at'] : 0;
        if ($createdAt <= 0 || ($createdAt + $ttlSeconds) < $now) {
            unset($tokens[$token]);
        }
    }

    if (count($tokens) > 30) {
        uasort($tokens, function ($a, $b) {
            $aTs = isset($a['created_at']) ? (int) $a['created_at'] : 0;
            $bTs = isset($b['created_at']) ? (int) $b['created_at'] : 0;
            return $aTs <=> $bTs;
        });

        while (count($tokens) > 30) {
            array_shift($tokens);
        }
    }

    try {
        $token = bin2hex(random_bytes(18));
    } catch (Throwable $e) {
        $token = sha1(uniqid('undo_', true) . mt_rand());
    }

    $tokens[$token] = [
        'created_at' => $now,
        'payload' => $payload,
    ];

    $_SESSION['multiSelectUndoTokens'] = $tokens;

    return $token;
}

function buildSuperAllFilter(array $filter)
{
    return [
        'tags' => normalizeStringList($filter['tags'] ?? []),
        'groups' => normalizeStringList($filter['groups'] ?? []),
        'sort' => isset($filter['sort']) ? (string) $filter['sort'] : 'latest_modified',
        'limit' => 100,
        'offset' => 0,
        'search' => isset($filter['search']) ? trim((string) $filter['search']) : '',
        'searchType' => isset($filter['searchType']) ? (string) $filter['searchType'] : 'all',
        '_skipPreferencePersist' => true,
    ];
}

function collectFilteredLinkIdsForSuperAll(array $filter, $hardCap = 5000)
{
    require_once __DIR__ . '/filterLink.php';

    if (!function_exists('getFilteredLinks')) {
        throw new RuntimeException('Filter service unavailable');
    }

    $queryFilter = buildSuperAllFilter($filter);
    $result = getFilteredLinks($queryFilter);
    if (!is_array($result)) {
        throw new RuntimeException('Invalid filter response');
    }

    $total = isset($result['total']) ? (int) $result['total'] : 0;
    if ($total <= 0) {
        return [];
    }

    if ($total > $hardCap) {
        throw new LengthException('Too many filtered results for SuperAll');
    }

    $ids = [];
    $seen = [];
    $limit = max(1, (int) ($queryFilter['limit'] ?? 100));
    $maxPasses = (int) ceil($total / $limit) + 2;
    $pass = 0;

    while ($pass < $maxPasses) {
        $pass++;
        $rows = isset($result['links']) && is_array($result['links'])
            ? $result['links']
            : [];

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ids[] = $id;

            if (count($ids) > $hardCap) {
                throw new LengthException('Too many filtered results for SuperAll');
            }
        }

        if (empty($rows) || count($ids) >= $total) {
            break;
        }

        $queryFilter['offset'] = ((int) $queryFilter['offset']) + $limit;
        $result = getFilteredLinks($queryFilter);
        if (!is_array($result)) {
            break;
        }
    }

    return $ids;
}

function resolveSelectionIds(array $data, array &$meta)
{
    $meta = [
        'superallApplied' => false,
        'excludedCount' => 0,
        'filteredCount' => 0,
    ];

    $superAll = isset($data['superall']) && is_array($data['superall'])
        ? $data['superall']
        : null;

    $superAllEnabled = $superAll && !empty($superAll['enabled']);
    if (!$superAllEnabled) {
        $ids = isset($data['ids']) && is_array($data['ids'])
            ? normalizePositiveIntList($data['ids'])
            : [];

        if (empty($ids)) {
            throw new InvalidArgumentException('No valid ids provided');
        }

        return $ids;
    }

    $meta['superallApplied'] = true;

    $filter = isset($superAll['filter']) && is_array($superAll['filter'])
        ? $superAll['filter']
        : [];

    $excludedIds = normalizePositiveIntList($superAll['excludedIds'] ?? []);
    $meta['excludedCount'] = count($excludedIds);

    $ids = collectFilteredLinkIdsForSuperAll($filter, 5000);
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

    if (!is_array($data) || !isset($data['type'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    $type = (string) $data['type'];

    $input = isset($data['input']) ? trim((string) $data['input']) : '';
    $values = normalizeStringList($data['values'] ?? []);
    if ($input !== '') {
        $values[] = $input;
    }
    $values = array_values(array_unique($values, SORT_STRING));

    $groups = normalizeStringList($data['groups'] ?? []);
    if (empty($groups) && $type === 'groupAdd' && !empty($values)) {
        $groups = $values;
    }

    $addValues = normalizeStringList($data['add'] ?? []);
    $removeValues = normalizeStringList($data['remove'] ?? []);

    if (in_array($type, ['groupDel', 'tagAdd', 'tagDel'], true) && empty($values)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Input is required for this action']);
        return;
    }

    if ($type === 'groupAdd' && empty($groups)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Input is required for this action']);
        return;
    }

    if (in_array($type, ['tagSync', 'groupSync'], true) && empty($addValues) && empty($removeValues)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No changes provided for sync action']);
        return;
    }

    try {
        $selectionMeta = [];
        $ids = resolveSelectionIds($data, $selectionMeta);
    } catch (LengthException $e) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Too many filtered results selected. Refine filters before SuperAll action.',
        ]);
        return;
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        return;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to resolve selected ids']);
        return;
    }

    $requestedCount = count($ids);

    // SuperAll can validly result in zero selected ids after exclusions.
    if ($requestedCount === 0 && empty($selectionMeta['superallApplied'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No valid ids provided']);
        return;
    }

    $pdo = connectToDatabase();

    try {
        $pdo->beginTransaction();

        $responsePayload = [
            'status' => 'success',
            'requestedCount' => $requestedCount,
            'affectedCount' => 0,
            'superallApplied' => !empty($selectionMeta['superallApplied']),
            'superallExcludedCount' => (int) ($selectionMeta['excludedCount'] ?? 0),
            'superallFilteredCount' => (int) ($selectionMeta['filteredCount'] ?? 0),
        ];

        switch ($type) {
            case 'delete':
                include __DIR__ . '/deleteLink.php';
                $deletedIds = [];
                foreach ($ids as $id) {
                    deleteLink($id);
                    $deletedIds[] = (int) $id;
                }

                // Verify which links were actually removed from the links table
                if (!empty($deletedIds)) {
                    $placeholders = implode(',', array_fill(0, count($deletedIds), '?'));
                    $verifyStmt = $pdo->prepare("SELECT id FROM links WHERE id IN ($placeholders)");
                    $verifyStmt->execute($deletedIds);
                    $stillExist = array_map('intval', $verifyStmt->fetchAll(PDO::FETCH_COLUMN));
                    $deletedIds = array_values(array_diff($deletedIds, $stillExist));
                }

                $responsePayload['affectedCount'] = count($deletedIds);

                if (count($deletedIds) > 0) {
                    $undoToken = storeMultiSelectUndoToken([
                        'type' => 'delete',
                        'ids' => $deletedIds,
                    ]);
                    $responsePayload['undoUrl'] = '?comp=linksUndo&token=' . rawurlencode($undoToken);
                    $responsePayload['undoEligible'] = true;
                } else {
                    $responsePayload['undoEligible'] = false;
                }
                break;

            case 'archive':
                $sql = "UPDATE links SET status = 0, modifier = :modifier, modified_at = :modified_at WHERE id = :id AND status != 0";
                $stmt = $pdo->prepare($sql);
                $archivedIds = [];
                $affectedCount = 0;

                foreach ($ids as $id) {
                    $stmt->execute([
                        'modifier' => $_SESSION['user']['id'],
                        'modified_at' => date('Y-m-d H:i:s'),
                        'id' => $id,
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $affectedCount++;
                        $archivedIds[] = (int) $id;
                    }
                }

                $responsePayload['affectedCount'] = $affectedCount;
                $responsePayload['archivedIds'] = $archivedIds;

                if ($affectedCount > 0) {
                    $undoToken = storeMultiSelectUndoToken([
                        'type' => 'archive',
                        'ids' => $archivedIds,
                    ]);
                    $responsePayload['undoUrl'] = '?comp=linksUndo&token=' . rawurlencode($undoToken);
                    $responsePayload['undoEligible'] = true;
                } else {
                    $responsePayload['undoEligible'] = false;
                }
                break;

            case 'groupAdd':
                foreach ($ids as $id) {
                    foreach ($groups as $groupTitle) {
                        addToGroup($id, $groupTitle);
                    }
                }
                $responsePayload['affectedCount'] = $requestedCount;
                $undoToken = storeMultiSelectUndoToken([
                    'type' => 'groupDel',
                    'ids' => array_values(array_map('intval', $ids)),
                    'values' => $groups,
                ]);
                $responsePayload['undoUrl'] = '?comp=linksUndo&token=' . rawurlencode($undoToken);
                $responsePayload['undoEligible'] = true;
                break;

            case 'groupSync':
                foreach ($ids as $id) {
                    foreach ($addValues as $value) {
                        addToGroup($id, $value);
                    }
                    foreach ($removeValues as $value) {
                        removeGroup($id, $value);
                    }
                }
                $responsePayload['affectedCount'] = $requestedCount;
                $undoToken = storeMultiSelectUndoToken([
                    'type' => 'groupSync',
                    'ids' => array_values(array_map('intval', $ids)),
                    'add' => $removeValues,
                    'remove' => $addValues,
                ]);
                $responsePayload['undoUrl'] = '?comp=linksUndo&token=' . rawurlencode($undoToken);
                $responsePayload['undoEligible'] = true;
                break;

            case 'tagAdd':
                foreach ($ids as $id) {
                    foreach ($values as $value) {
                        addToTag($id, $value);
                    }
                }
                $responsePayload['affectedCount'] = $requestedCount;
                $undoToken = storeMultiSelectUndoToken([
                    'type' => 'tagDel',
                    'ids' => array_values(array_map('intval', $ids)),
                    'values' => $values,
                ]);
                $responsePayload['undoUrl'] = '?comp=linksUndo&token=' . rawurlencode($undoToken);
                $responsePayload['undoEligible'] = true;
                break;

            case 'tagSync':
                foreach ($ids as $id) {
                    foreach ($addValues as $value) {
                        addToTag($id, $value);
                    }
                    foreach ($removeValues as $value) {
                        removeTag($id, $value);
                    }
                }
                $responsePayload['affectedCount'] = $requestedCount;
                $undoToken = storeMultiSelectUndoToken([
                    'type' => 'tagSync',
                    'ids' => array_values(array_map('intval', $ids)),
                    'add' => $removeValues,
                    'remove' => $addValues,
                ]);
                $responsePayload['undoUrl'] = '?comp=linksUndo&token=' . rawurlencode($undoToken);
                $responsePayload['undoEligible'] = true;
                break;

            case 'groupDel':
                foreach ($ids as $id) {
                    foreach ($values as $value) {
                        removeGroup($id, $value);
                    }
                }
                $responsePayload['affectedCount'] = $requestedCount;
                $undoToken = storeMultiSelectUndoToken([
                    'type' => 'groupAdd',
                    'ids' => array_values(array_map('intval', $ids)),
                    'values' => $values,
                ]);
                $responsePayload['undoUrl'] = '?comp=linksUndo&token=' . rawurlencode($undoToken);
                $responsePayload['undoEligible'] = true;
                break;

            case 'tagDel':
                foreach ($ids as $id) {
                    foreach ($values as $value) {
                        removeTag($id, $value);
                    }
                }
                $responsePayload['affectedCount'] = $requestedCount;
                $undoToken = storeMultiSelectUndoToken([
                    'type' => 'tagAdd',
                    'ids' => array_values(array_map('intval', $ids)),
                    'values' => $values,
                ]);
                $responsePayload['undoUrl'] = '?comp=linksUndo&token=' . rawurlencode($undoToken);
                $responsePayload['undoEligible'] = true;
                break;

            default:
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Unsupported action']);
                closeConnection($pdo);
                return;
        }

        $pdo->commit();
        closeConnection($pdo);
        session_write_close();
        echo json_encode($responsePayload);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Multi-select action failed']);
    }

    closeConnection($pdo);
}
