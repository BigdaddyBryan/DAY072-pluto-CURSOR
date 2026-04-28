<?php

function _normalizeUserListLimit($value)
{
    $allowedLimits = [10, 20, 50, 100];
    $limit = (int) $value;
    return in_array($limit, $allowedLimits, true) ? $limit : 10;
}

function _normalizeUserListSort($value)
{
    $allowedSorts = [
        'alphabet_asc',
        'alphabet_desc',
        'latest_visit',
        'latest',
        'oldest',
        'latest_modified',
    ];

    $sort = (string) $value;
    return in_array($sort, $allowedSorts, true) ? $sort : 'latest_modified';
}

function _userTimestamp($value)
{
    if (!is_string($value) || trim($value) === '' || $value === '0000-00-00 00:00:00') {
        return 0;
    }
    $ts = strtotime($value);
    return $ts === false ? 0 : (int) $ts;
}

function _userFullName($user)
{
    $name = trim((string) ($user['name'] ?? ''));
    $familyName = trim((string) ($user['family_name'] ?? ''));
    return trim($name . ' ' . $familyName);
}

function _normalizeUserRoleFilters($values)
{
    $allowedRoles = ['viewer', 'limited', 'user', 'admin', 'superadmin'];

    return array_values(array_unique(array_filter(array_map(function ($value) use ($allowedRoles) {
        $role = strtolower(trim((string) $value));
        return in_array($role, $allowedRoles, true) ? $role : '';
    }, is_array($values) ? $values : []), function ($value) {
        return $value !== '';
    }), SORT_STRING));
}

function _userMatchesSearch($user, $search, $searchType)
{
    if ($search === '') {
        return true;
    }

    $needle = strtolower($search);
    $fullName = strtolower(_userFullName($user));
    $email = strtolower((string) ($user['email'] ?? ''));

    $tagTitles = array_map(function ($tag) {
        return strtolower((string) ($tag['title'] ?? ''));
    }, $user['tags'] ?? []);

    $groupTitles = array_map(function ($group) {
        return strtolower((string) ($group['title'] ?? ''));
    }, $user['groups'] ?? []);

    $matchName = strpos($fullName, $needle) !== false;
    $matchEmail = strpos($email, $needle) !== false;
    $matchTags = false;
    foreach ($tagTitles as $title) {
        if ($title !== '' && strpos($title, $needle) !== false) {
            $matchTags = true;
            break;
        }
    }

    $matchGroups = false;
    foreach ($groupTitles as $title) {
        if ($title !== '' && strpos($title, $needle) !== false) {
            $matchGroups = true;
            break;
        }
    }

    switch ($searchType) {
        case 'name':
            return $matchName;
        case 'tags':
            return $matchTags;
        case 'groups':
            return $matchGroups;
        case 'email':
            return $matchEmail;
        case 'all':
        default:
            return $matchName || $matchEmail || $matchTags || $matchGroups;
    }
}

function _sanitizeUserForList($user)
{
    $safe = [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'family_name' => (string) ($user['family_name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'picture' => (string) ($user['picture'] ?? ''),
        'last_login' => (string) ($user['last_login'] ?? ''),
        'modified_at' => (string) ($user['modified_at'] ?? ''),
        'created_at' => (string) ($user['created_at'] ?? ''),
        'modifier' => (string) ($user['modifier'] ?? ''),
        'tags' => is_array($user['tags'] ?? null) ? $user['tags'] : [],
        'groups' => is_array($user['groups'] ?? null) ? $user['groups'] : [],
    ];

    return $safe;
}

function getFilteredUsers($filter)
{
    checkUser();
    if (!checkAdmin()) {
        return ['total' => 0, 'users' => []];
    }

    $limit = _normalizeUserListLimit($filter['limit'] ?? 10);
    $offset = max(0, (int) ($filter['offset'] ?? 0));
    $sort = _normalizeUserListSort($filter['sort'] ?? 'latest_modified');
    $search = trim((string) ($filter['search'] ?? ''));
    $searchType = (string) ($filter['searchType'] ?? 'all');
    $skipPreferencePersist = !empty($filter['_skipPreferencePersist']);

    $filterTags = array_values(array_unique(array_filter(array_map(function ($value) {
        return trim((string) $value);
    }, is_array($filter['tags'] ?? null) ? $filter['tags'] : []), function ($value) {
        return $value !== '';
    }), SORT_STRING));

    $filterGroups = array_values(array_unique(array_filter(array_map(function ($value) {
        return trim((string) $value);
    }, is_array($filter['groups'] ?? null) ? $filter['groups'] : []), function ($value) {
        return $value !== '';
    }), SORT_STRING));

    $filterRoles = _normalizeUserRoleFilters($filter['roles'] ?? []);

    $users = getAllUsers();

    $filtered = array_values(array_filter($users, function ($user) use ($filterTags, $filterGroups, $filterRoles, $search, $searchType) {
        $userTags = array_map(function ($tag) {
            return (string) ($tag['title'] ?? '');
        }, $user['tags'] ?? []);

        $userGroups = array_map(function ($group) {
            return (string) ($group['title'] ?? '');
        }, $user['groups'] ?? []);

        $userRole = strtolower(trim((string) ($user['role'] ?? '')));

        if (!empty($filterTags) && count(array_intersect($filterTags, $userTags)) === 0) {
            return false;
        }

        if (!empty($filterGroups) && count(array_intersect($filterGroups, $userGroups)) === 0) {
            return false;
        }

        if (!empty($filterRoles) && !in_array($userRole, $filterRoles, true)) {
            return false;
        }

        return _userMatchesSearch($user, $search, $searchType);
    }));

    usort($filtered, function ($a, $b) use ($sort) {
        switch ($sort) {
            case 'alphabet_asc':
                return strcasecmp(_userFullName($a), _userFullName($b));
            case 'alphabet_desc':
                return strcasecmp(_userFullName($b), _userFullName($a));
            case 'latest_visit':
                return _userTimestamp($b['last_login'] ?? '') <=> _userTimestamp($a['last_login'] ?? '');
            case 'latest':
                return _userTimestamp($b['created_at'] ?? '') <=> _userTimestamp($a['created_at'] ?? '');
            case 'oldest':
                return _userTimestamp($a['created_at'] ?? '') <=> _userTimestamp($b['created_at'] ?? '');
            case 'latest_modified':
            default:
                return _userTimestamp($b['modified_at'] ?? '') <=> _userTimestamp($a['modified_at'] ?? '');
        }
    });

    $total = count($filtered);
    if ($total > 0 && $offset >= $total) {
        $lastPageStart = (int) (floor(($total - 1) / $limit) * $limit);
        $offset = max(0, $lastPageStart);
    }

    $paged = array_slice($filtered, $offset, $limit);

    $safeUsers = array_map('_sanitizeUserForList', $paged);

    $pdo = connectToDatabase();
    $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
    if (!$skipPreferencePersist && $userId > 0) {
        try {
            $sql = "UPDATE users SET `limit` = :limit, sort_preference = :sort_preference WHERE id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':limit' => $limit,
                ':sort_preference' => $sort,
                ':user_id' => $userId,
            ]);
        } catch (PDOException $e) {
            $sql = "UPDATE users SET `limit` = :limit WHERE id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':limit' => $limit,
                ':user_id' => $userId,
            ]);
        }

        $_SESSION['user']['limit'] = $limit;
        $_SESSION['user']['sort_preference'] = $sort;
    }
    closeConnection($pdo);

    return [
        'total' => $total,
        'users' => $safeUsers,
    ];
}
