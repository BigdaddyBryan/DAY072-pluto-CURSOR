<?php
function getGoogleLoginRolePriorityScore($role)
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

function fetchRemoteJsonWithStatus($url)
{
  if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlError) {
      throw new RuntimeException('Google token verification request failed.');
    }

    return ['body' => (string) $response, 'status' => (int) $httpStatus];
  }

  if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
    throw new RuntimeException('Missing curl extension and allow_url_fopen is disabled.');
  }

  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 10,
      'ignore_errors' => true,
      'header' => "Accept: application/json\r\n",
    ],
  ]);

  $response = @file_get_contents($url, false, $context);
  if ($response === false) {
    throw new RuntimeException('Google token verification request failed.');
  }

  $httpStatus = 0;
  if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $headerLine) {
      if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $headerLine, $matches)) {
        $httpStatus = (int) $matches[1];
        break;
      }
    }
  }

  return ['body' => (string) $response, 'status' => $httpStatus];
}

function ensureGoogleSubColumn(PDO $pdo)
{
  $stmt = $pdo->query("PRAGMA table_info(users)");
  $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

  foreach ($columns as $column) {
    if (isset($column['name']) && $column['name'] === 'google_sub') {
      return;
    }
  }

  $pdo->exec('ALTER TABLE users ADD COLUMN google_sub TEXT');
}

function isCustomStoredProfilePicture($picture)
{
  $value = trim((string) $picture);
  if ($value === '') {
    return false;
  }

  return strpos($value, '/custom/images/profiles/') === 0 || strpos($value, '/custom/images/users/') === 0;
}

function buildGoogleProfileFallbackPicture($email, $googlePicture)
{
  $googlePicture = trim((string) $googlePicture);
  if ($googlePicture !== '') {
    return $googlePicture;
  }

  $normalizedEmail = strtolower(trim((string) $email));
  if ($normalizedEmail === '') {
    return '/images/user.jpg';
  }

  $emailHash = md5($normalizedEmail);
  return 'https://www.gravatar.com/avatar/' . $emailHash . '?d=mp&s=256';
}

function googleLogin()
{
  if (session_status() == PHP_SESSION_NONE) {
    session_start();
  }

  $failLogin = function ($message) {
    $_SESSION['error'] = $message;
    $_SESSION['errorType'] = 'login';
    header('Location: /home');
    exit;
  };

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $failLogin('Invalid login request. Please try again.');
  }

  if (!isset($_POST['credential']) || trim((string) $_POST['credential']) === '') {
    $failLogin('Google sign-in failed: missing credential.');
  }

  $pdo = null;

  try {
    $pdo = connectToDatabase();
    ensureGoogleSubColumn($pdo);

    $idToken = trim((string) $_POST['credential']);

    // Google OAuth 2.0 token verification endpoint
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $idToken;
    $tokenInfoResponse = fetchRemoteJsonWithStatus($url);
    $response = $tokenInfoResponse['body'];
    $httpStatus = $tokenInfoResponse['status'];

    // Decode the JSON response
    $data = json_decode($response, true);
    if (!is_array($data) || $httpStatus !== 200 || isset($data['error_description'])) {
      $failLogin('Google sign-in failed. Please try again.');
    }

    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      $failLogin('Google sign-in failed: invalid account data.');
    }

    if (isset($data['email_verified']) && $data['email_verified'] !== 'true' && $data['email_verified'] !== true) {
      $failLogin('Google sign-in failed: email not verified.');
    }

    // Check if the token is valid
    $googleSsoConfigPath = __DIR__ . '/../../../custom/googleSSO.json';
    $googleSsoConfig = json_decode((string) @file_get_contents($googleSsoConfigPath), true);
    $clientId = is_array($googleSsoConfig) ? ($googleSsoConfig['clientId'] ?? null) : null;

    $validIssuer = isset($data['iss']) && in_array($data['iss'], ['accounts.google.com', 'https://accounts.google.com'], true);
    $validAudience = !empty($clientId) && isset($data['aud']) && hash_equals((string) $clientId, (string) $data['aud']);

    if (!$validIssuer || !$validAudience) {
      $failLogin('Google sign-in failed. Please try again.');
    }

    // Token is valid, proceed with login
    $rawEmail = trim((string) $data['email']);
    $normalizedEmail = strtolower($rawEmail);
    $googleSub = trim((string) ($data['sub'] ?? ''));

    $user = false;
    if ($googleSub !== '') {
      $sql = 'SELECT * FROM users WHERE google_sub = :google_sub LIMIT 1';
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['google_sub' => $googleSub]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
      $sql = 'SELECT * FROM users WHERE lower(trim(email)) = lower(trim(:email)) ORDER BY id ASC';
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['email' => $normalizedEmail]);
      $emailMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (count($emailMatches) === 1) {
        $user = $emailMatches[0];
      } elseif (count($emailMatches) > 1) {
        $bestMatch = null;
        $bestRoleScore = -1;

        foreach ($emailMatches as $candidate) {
          $candidateRoleScore = getGoogleLoginRolePriorityScore($candidate['role'] ?? '');
          if ($bestMatch === null || $candidateRoleScore > $bestRoleScore) {
            $bestMatch = $candidate;
            $bestRoleScore = $candidateRoleScore;
          }
        }

        $user = $bestMatch;
      }
    }

    $sql = 'SELECT * FROM users';
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll();

    $givenName = trim((string) ($data['given_name'] ?? ($data['name'] ?? '')));
    $familyName = trim((string) ($data['family_name'] ?? ''));
    $googlePicture = trim((string) ($data['picture'] ?? ''));
    $emailFallbackName = trim((string) strstr($normalizedEmail, '@', true));
    if ($emailFallbackName === '') {
      $emailFallbackName = 'User';
    }
    $emailFallbackName = ucfirst($emailFallbackName);

    if ($givenName === '') {
      $givenName = $emailFallbackName;
    }

    if (!$user) {
      // User does not exist, create a new user
      $initialPicture = buildGoogleProfileFallbackPicture($normalizedEmail, $googlePicture);
      $sql = 'INSERT INTO users (email, google_sub, name, family_name, picture, last_login, role, mode, view, `limit`) VALUES (:email, :google_sub, :name, :familyName, :picture, :last_login, :role, :mode, :view, :limit)';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'email' => $normalizedEmail,
        'google_sub' => $googleSub !== '' ? $googleSub : null,
        'name' => $givenName,
        'familyName' => $familyName,
        'picture' => $initialPicture,
        'last_login' => date('Y-m-d H:i:s'),
        'role' => count($users) === 0 ? 'superadmin' : 'user',
        'mode' => 'dark',
        'view' => 'list',
        'limit' => 10
      ]);
      $userId = (int) $pdo->lastInsertId();
    } else {
      // Existing users keep edited values, but missing profile fields are backfilled once.
      $existingPicture = trim((string) ($user['picture'] ?? ''));
      $effectivePicture = $existingPicture;
      if ($effectivePicture === '') {
        $effectivePicture = buildGoogleProfileFallbackPicture($normalizedEmail, $googlePicture);
      }

      $existingName = trim((string) ($user['name'] ?? ''));
      $effectiveName = $existingName !== '' ? $existingName : $givenName;

      $existingFamilyName = trim((string) ($user['family_name'] ?? ''));
      $effectiveFamilyName = $existingFamilyName !== '' ? $existingFamilyName : $familyName;

      if (isCustomStoredProfilePicture($existingPicture)) {
        $effectivePicture = $existingPicture;
      }

      $sql = 'UPDATE users
              SET last_login = :last_login,
                  email = :email,
                  google_sub = COALESCE(NULLIF(google_sub, \'\'), :google_sub),
                  name = :name,
                  family_name = :family_name,
                  picture = :picture
              WHERE id = :id';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'last_login' => date('Y-m-d H:i:s'),
        'email' => $normalizedEmail,
        'google_sub' => $googleSub !== '' ? $googleSub : null,
        'name' => $effectiveName,
        'family_name' => $effectiveFamilyName,
        'picture' => $effectivePicture,
        'id' => $user['id']
      ]);
      $userId = (int) $user['id'];
    }

    $sql = 'SELECT * FROM users WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $userId]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dbUser) {
      throw new RuntimeException('Could not load user after Google login.');
    }

    $sql = 'SELECT groups.id, groups.title FROM users_groups 
                INNER JOIN groups ON users_groups.group_id = groups.id 
                WHERE users_groups.user_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $dbUser['id']]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    closeConnection($pdo);
    $_SESSION['user'] = $dbUser;

    if ($groups) {
      $_SESSION['groups'] = $groups;
    }

    issueUserDeviceSession([
      'id' => $dbUser['id'],
      'email' => $dbUser['email'] ?? ''
    ]);

    header('Location: /');
    exit;
  } catch (Throwable $e) {
    if ($pdo !== null) {
      closeConnection($pdo);
    }

    @file_put_contents(
      __DIR__ . '/../../../public/error_log.txt',
      '[' . date('Y-m-d H:i:s') . '] Google login error: ' . $e->getMessage() . "\n",
      FILE_APPEND
    );

    $failLogin('Google sign-in failed. Please try again.');
  }
}
