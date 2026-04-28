<?php
function getRolePriorityScore($role)
{
    $role = strtolower(trim((string) $role));
    $priorities = [
        'viewer' => 1,
        'limited' => 2,
        'user' => 3,
        'admin' => 4,
        'superadmin' => 5,
    ];

    return $priorities[$role] ?? 0;
}

function wantsJsonResponse()
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($acceptHeader, 'application/json') !== false;
}

function respondLoginError($message, $statusCode = 401)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (wantsJsonResponse()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit();
    }

    $_SESSION['error'] = (string) $message;
    $_SESSION['errorType'] = 'login';
    header('Location: /home');
    exit();
}

function respondLoginSuccess($redirect = '/')
{
    if (wantsJsonResponse()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => $redirect]);
        exit();
    }

    header('Location: ' . $redirect);
    exit();
}

function login($postData)
{
    $pdo = connectToDatabase(); // Import the PDO connection
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // TODO: cant login if user is archived?
    // Get input data
    $email = trim((string) ($postData['email'] ?? ''));
    $password = (string) ($postData['password'] ?? '');

    if (empty($email) || empty($password)) {
        respondLoginError('Email and password are required', 400);
    }

    $email = strtolower($email); // Convert email to lowercase

    // Prepare and execute the SQL query
    $sql = "SELECT * FROM users WHERE lower(trim(email)) = lower(trim(?)) ORDER BY id ASC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no user is found, redirect back to home with an error message
        if (!$users) {
            respondLoginError('Invalid email or password');
        }

        $user = null;
        $selectedRoleScore = -1;
        $hasPasswordlessAccount = false;

        foreach ($users as $candidate) {
            $candidatePassword = (string) ($candidate['password'] ?? '');
            if ($candidatePassword === '') {
                $hasPasswordlessAccount = true;
                continue;
            }

            if (!password_verify($password, $candidatePassword)) {
                continue;
            }

            $candidateRoleScore = getRolePriorityScore($candidate['role'] ?? '');
            if ($user === null || $candidateRoleScore > $selectedRoleScore) {
                $user = $candidate;
                $selectedRoleScore = $candidateRoleScore;
            }
        }

        if ($user === null) {
            if ($hasPasswordlessAccount) {
                respondLoginError('Please login with Google and reset your password.');
            }
            respondLoginError('Invalid email or password');
        }

        // Persist a fallback avatar if the account has no stored picture yet.
        if (!isset($user['picture']) || trim((string) $user['picture']) === '') {
            $user['picture'] = fetchGravatarProfilePicture($email);

            $updatePictureSql = 'UPDATE users SET picture = :picture WHERE id = :id';
            $updatePictureStmt = $pdo->prepare($updatePictureSql);
            $updatePictureStmt->execute([
                'picture' => $user['picture'],
                'id' => $user['id']
            ]);
        }

        $sql = 'SELECT groups.id, groups.title FROM users_groups 
                INNER JOIN groups ON users_groups.group_id = groups.id 
                WHERE users_groups.user_id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $user['id']]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Set session variables
        $_SESSION['user'] = $user;

        if ($groups) {
            $_SESSION['groups'] = $groups;
        }

        if (isset($_COOKIE['token'])) {
            setcookie('token', '', time() - 3600, '/', '', false, true);
        }

        issueUserDeviceSession($user);

        respondLoginSuccess('/');
    } catch (PDOException $e) {
        respondLoginError('Login failed. Please try again.', 500);
    }
}

function fetchGravatarProfilePicture($email)
{
    $emailHash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/$emailHash";
}
