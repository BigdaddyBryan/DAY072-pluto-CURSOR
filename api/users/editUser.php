<?php

function saveUploadedUserPicture($file, &$errorMessage = null)
{
    $errorMessage = null;

    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $errorMessage = 'Profile photo upload failed. Please try again.';
        }
        return null;
    }

    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        $errorMessage = 'Profile photo upload failed. Please try again.';
        return null;
    }

    $maxSizeBytes = 5 * 1024 * 1024;
    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize <= 0 || $fileSize > $maxSizeBytes) {
        $errorMessage = 'Profile photo must be 5MB or smaller.';
        return null;
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    $originalName = (string) ($file['name'] ?? '');
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true) || $tmpName === '') {
        $errorMessage = 'Only JPG, PNG, GIF or WEBP images are allowed.';
        return null;
    }

    $detectedMime = function_exists('mime_content_type') ? (string) mime_content_type($tmpName) : '';
    if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimeTypes, true)) {
        $errorMessage = 'Only JPG, PNG, GIF or WEBP images are allowed.';
        return null;
    }

    $directory = __DIR__ . '/../../public/custom/images/users';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return null;
    }

    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $baseName = preg_replace('/[^a-zA-Z0-9-_]+/', '-', $baseName);
    $baseName = trim((string) $baseName, '-');
    if ($baseName === '') {
        $baseName = 'user';
    }

    $fileName = date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '-' . $baseName . '.' . $extension;
    $targetPath = $directory . '/' . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        $errorMessage = 'Profile photo upload failed. Please try again.';
        return null;
    }

    return '/custom/images/users/' . $fileName;
}

function editUser($postData, $files = [])
{
    if (!checkAdmin()) {
        ob_start();
        header('Location: /');
        ob_end_flush();
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        include __DIR__ . '/../pages/errors/404.php';
        return;
    }

    if (!isset($postData['id']) || !isset($postData['name']) || !isset($postData['family_name']) || !isset($postData['email']) || !isset($postData['role'])) {
        include __DIR__ . '/../pages/errors/400.php';
        return;
    }

    $id = (int) $postData['id'];
    $name = trim((string) $postData['name']);
    $family_name = trim((string) $postData['family_name']);
    $email = strtolower(trim((string) $postData['email']));
    $role = trim((string) $postData['role']);
    $tags = json_decode($postData['tags'], true);
    $groups = json_decode($postData['groups'], true);

    $pdo = connectToDatabase();

    $sql = 'SELECT name, family_name, email, role, picture FROM users WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($existingUser) || empty($existingUser)) {
        closeConnection($pdo);
        include __DIR__ . '/../pages/errors/400.php';
        return;
    }

    // Keep profile identity stable when role changes by preserving existing values
    // whenever empty values are posted from the edit modal.
    if ($name === '') {
        $name = (string) ($existingUser['name'] ?? '');
    }
    if ($family_name === '') {
        $family_name = (string) ($existingUser['family_name'] ?? '');
    }
    if ($email === '') {
        $email = (string) ($existingUser['email'] ?? '');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        closeConnection($pdo);
        $_SESSION['ui_notice'] = 'Please provide a valid email address.';
        $_SESSION['ui_notice_type'] = 'error';
        header('Location: /users');
        return;
    }

    $sql = 'SELECT id FROM users WHERE LOWER(email) = LOWER(:email) AND id <> :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'email' => $email,
        'id' => $id,
    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        closeConnection($pdo);
        $_SESSION['ui_notice'] = 'Email is already in use by another account.';
        $_SESSION['ui_notice_type'] = 'error';
        header('Location: /users');
        return;
    }

    if (!in_array($role, ['user', 'admin', 'viewer', 'limited', 'superadmin'], true)) {
        $role = (string) ($existingUser['role'] ?? 'user');
    }

    $profilePictureUrl = (string) ($existingUser['picture'] ?? '');
    $uploadError = null;
    $uploadedPicture = saveUploadedUserPicture($files['picture_file'] ?? null, $uploadError);
    if (is_string($uploadedPicture) && $uploadedPicture !== '') {
        $profilePictureUrl = $uploadedPicture;
    }

    if (isset($postData['password']) && $postData['password'] !== '') {
        $postData['password'] = password_hash($postData['password'], PASSWORD_DEFAULT);

        $sql = 'UPDATE users SET name = :name, family_name = :family_name, email = :email, role = :role, password = :password, picture = :picture, modifier = :modifier, modified_at = :modified_at WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'family_name' => $family_name,
            'email' => $email,
            'role' => $role,
            'password' => $postData['password'],
            'picture' => $profilePictureUrl,
            'modifier' => $_SESSION['user']['id'],
            'modified_at' => date('Y-m-d H:i:s'),
            'id' => $id
        ]);
    } else {
        $sql = 'UPDATE users SET name = :name, family_name = :family_name, email = :email, role = :role, picture = :picture, modifier = :modifier, modified_at = :modified_at WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'family_name' => $family_name,
            'email' => $email,
            'role' => $role,
            'picture' => $profilePictureUrl,
            'modifier' => $_SESSION['user']['id'],
            'modified_at' => date('Y-m-d H:i:s'),
            'id' => $id
        ]);
    }

    // Delete existing tags for the user
    $sql = "DELETE FROM users_tags WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['userId' => $id]);

    // Insert new tags
    foreach ($tags as $tag) {
        // Check if the tag already exists in the tags table
        $sql = "SELECT id FROM tags WHERE LOWER(title) = LOWER(:tag)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tag' => $tag]);
        $tagId = $stmt->fetchColumn();

        if (!$tagId) {
            // Insert the new tag into the tags table
            $sql = "INSERT INTO tags (title) VALUES (:tag)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['tag' => ucfirst($tag)]);
            $tagId = $pdo->lastInsertId();
        }

        // Insert the tag into the user_tags table
        $sql = "INSERT INTO users_tags (user_id, tag_id) VALUES (:userId, :tagId)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['userId' => $id, 'tagId' => $tagId]);
    }

    // Delete existing groups for the user
    $sql = "DELETE FROM users_groups WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['userId' => $id]);

    // Insert new groups
    foreach ($groups as $group) {
        // Check if the group already exists in the groups table
        $sql = "SELECT id FROM groups WHERE LOWER(title) = LOWER(:group)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['group' => $group]);
        $groupId = $stmt->fetchColumn();

        if (!$groupId) {
            // Insert the new group into the groups table
            $sql = "INSERT INTO groups (title) VALUES (:group)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['group' => ucfirst($group)]);
            $groupId = $pdo->lastInsertId();
        }

        // Insert the group into the user_groups table
        $sql = "INSERT INTO users_groups (user_id, group_id) VALUES (:userId, :groupId)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['userId' => $id, 'groupId' => $groupId]);
    }

    closeConnection($pdo);

    if (isset($_SESSION['user']['id']) && (string) $_SESSION['user']['id'] === (string) $id) {
        $pdo = connectToDatabase();
        $sql = 'SELECT * FROM users WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        closeConnection($pdo);

        if (is_array($currentUser) && !empty($currentUser)) {
            $_SESSION['user'] = $currentUser;
        }
    }

    if (is_string($uploadError) && trim($uploadError) !== '') {
        $_SESSION['ui_notice'] = $uploadError . ' Fallback profile image was used.';
        $_SESSION['ui_notice_type'] = 'warning';
    }

    header('Location: /users');
}
