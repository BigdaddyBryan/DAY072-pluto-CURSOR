<?php

function safeScandirEntries(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $entries = @scandir($directory);
    if ($entries === false) {
        return [];
    }

    return $entries;
}

/**
 * Recursively delete a directory and all its contents.
 * Returns the number of files deleted.
 */
function deleteDirectoryRecursive(string $dir): int
{
    $deleted = 0;
    foreach (safeScandirEntries($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $deleted += deleteDirectoryRecursive($path);
        } elseif (@unlink($path)) {
            $deleted++;
        }
    }
    @rmdir($dir);
    return $deleted;
}

/**
 * Clean up unused or excess media files.
 * - Backgrounds: keep only the 3 newest files
 * - Group images: delete orphaned files not referenced by any group
 * - Profile images: delete orphaned files not referenced by any user
 */
function cleanupMedia()
{
    if (!checkAdmin()) {
        return ['error' => 'Unauthorized'];
    }

    $deleted = 0;
    $kept = 0;
    $errors = [];

    // --- 1. Background / slideshow images: keep 3 newest + remove archive dirs ---
    $slideshowDirs = [
        __DIR__ . '/../../public/custom/images/slideshow',
        __DIR__ . '/../../custom/custom/images/slideshow',
    ];
    foreach ($slideshowDirs as $slideshowDir) {
        if (is_dir($slideshowDir)) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $files = [];

            foreach (safeScandirEntries($slideshowDir) as $file) {
                if ($file === '.' || $file === '..') continue;
                $fullPath = $slideshowDir . '/' . $file;

                // Remove old archive subdirectories (unused by slideshow)
                if (is_dir($fullPath)) {
                    $deleted += deleteDirectoryRecursive($fullPath);
                    continue;
                }

                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) continue;

                $files[] = [
                    'name' => $file,
                    'path' => $fullPath,
                    'mtime' => filemtime($fullPath),
                ];
            }

            // Sort newest first
            usort($files, function ($a, $b) {
                return $b['mtime'] <=> $a['mtime'];
            });

            // Keep 3 newest, delete the rest
            foreach ($files as $i => $file) {
                if ($i < 3) {
                    $kept++;
                    continue;
                }
                if (@unlink($file['path'])) {
                    $deleted++;
                } else {
                    $errors[] = 'Could not delete: ' . $file['name'];
                }
            }
        }
    }

    // --- 2. Group images: delete orphaned files ---
    $groupsDir = __DIR__ . '/../../public/images/groups';
    if (is_dir($groupsDir)) {
        $pdo = connectToDatabase();
        $stmt = $pdo->prepare('SELECT DISTINCT image FROM groups WHERE image IS NOT NULL AND image != \'\'');
        $stmt->execute();
        $referencedImages = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        closeConnection($pdo);

        // Always keep default.png and index.php
        $protectedFiles = array_merge(['default.png', 'index.php'], $referencedImages);
        $protectedSet = array_flip($protectedFiles);

        foreach (safeScandirEntries($groupsDir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $fullPath = $groupsDir . '/' . $file;
            if (is_dir($fullPath)) continue;

            if (isset($protectedSet[$file])) {
                $kept++;
                continue;
            }

            if (@unlink($fullPath)) {
                $deleted++;
            } else {
                $errors[] = 'Could not delete group image: ' . $file;
            }
        }
    }

    // --- 3. Profile / user images: delete orphaned files ---
    // Images can be stored in either /custom/images/users/ (admin edits) or
    // /custom/images/profiles/ (user self-updates via profile settings).
    $userImageDirs = [
        '/custom/images/users/'    => __DIR__ . '/../../public/custom/images/users',
        '/custom/images/profiles/' => __DIR__ . '/../../public/custom/images/profiles',
    ];

    $hasAnyUserDir = false;
    foreach ($userImageDirs as $dir) {
        if (is_dir($dir)) {
            $hasAnyUserDir = true;
            break;
        }
    }

    if ($hasAnyUserDir) {
        $pdo = connectToDatabase();
        $stmt = $pdo->prepare('SELECT DISTINCT picture FROM users WHERE picture IS NOT NULL AND picture != \'\'');
        $stmt->execute();
        $referencedPictures = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        closeConnection($pdo);

        foreach ($userImageDirs as $urlPrefix => $dirPath) {
            if (!is_dir($dirPath)) continue;

            // Only protect files whose URL path starts with this directory's prefix
            $referencedFilenames = [];
            foreach ($referencedPictures as $pic) {
                if (strpos($pic, $urlPrefix) === 0) {
                    $referencedFilenames[] = basename($pic);
                }
            }
            $protectedSet = array_flip($referencedFilenames);

            foreach (safeScandirEntries($dirPath) as $file) {
                if ($file === '.' || $file === '..') continue;
                $fullPath = $dirPath . '/' . $file;
                if (is_dir($fullPath)) continue;

                if (isset($protectedSet[$file])) {
                    $kept++;
                    continue;
                }

                if (@unlink($fullPath)) {
                    $deleted++;
                } else {
                    $errors[] = 'Could not delete user image: ' . $file;
                }
            }
        }
    }

    return [
        'success' => true,
        'deleted' => $deleted,
        'kept' => $kept,
        'errors' => $errors,
    ];
}
