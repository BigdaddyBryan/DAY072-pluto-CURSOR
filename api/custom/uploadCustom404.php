<?php

function uploadCustom404($fileData)
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin
        if (!checkAdmin()) {
            include __DIR__ . '/../../pages/errors/404.php';
            exit;
        }

        // Check if the file is uploaded properly
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            // Ensure it's an HTML file
            $fileType = mime_content_type($_FILES['file']['tmp_name']);
            $fileName = strtolower((string) ($_FILES['file']['name'] ?? ''));
            $isHtmlByMime = in_array($fileType, ['text/html', 'application/xhtml+xml', 'text/plain'], true);
            $isHtmlByName = str_ends_with($fileName, '.html') || str_ends_with($fileName, '.htm');
            if (!$isHtmlByMime && !$isHtmlByName) {
                echo "Error: Uploaded file must be an HTML file.";
                exit;
            }

            // Define the path to save the uploaded file
            $targetFile = __DIR__ . '/../../public/custom/404.html';

            // If the file already exists, delete it
            if (file_exists($targetFile)) {
                unlink($targetFile);
            }

            // Move the uploaded file to the target location
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                // Redirect to the admin page upon successful upload
                header('Location: /admin');
                exit;
            } else {
                echo "Error: Could not move uploaded file.";
            }
        } else {
            echo "Error: No file uploaded or upload error.";
        }
    } else {
        echo "Error: Invalid request method.";
    }
}

function deleteCustom404()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin
        if (!checkAdmin()) {
            include __DIR__ . '/../../pages/errors/404.php';
            exit;
        }

        // Define the path to the custom 404 file
        $targetFile = __DIR__ . '/../../public/custom/404.html';

        // If the file exists, delete it
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }

        // Redirect to the admin page upon successful deletion
        header('Location: /admin');
        exit;
    } else {
        echo "Error: Invalid request method.";
    }
}
