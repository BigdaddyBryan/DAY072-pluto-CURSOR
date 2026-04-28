<?php

/**
 * Router for PHP built-in web server.
 * Usage: php -S localhost:8000 -t public public/router.php
 *
 * Adds Cache-Control headers and gzip compression for static assets,
 * then falls through to index.php for dynamic routes.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// Serve static files with cache + compression
if ($uri !== '/' && is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'svg'   => 'image/svg+xml',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'webmanifest' => 'application/manifest+json',
    ];

    // Compressible text types
    $compressible = ['css', 'js', 'json', 'svg', 'webmanifest'];

    // Long cache for versioned/fingerprinted files (?v=...), short cache for others
    $hasVersionParam = isset($_GET['v']) || isset($_GET['ver']);
    if (in_array($ext, ['woff', 'woff2', 'ttf', 'eot'], true)) {
        // Fonts rarely change – cache for 1 year
        header('Cache-Control: public, max-age=31536000, immutable');
    } elseif ($hasVersionParam) {
        // Cache-busted assets – cache for 1 year
        header('Cache-Control: public, max-age=31536000, immutable');
    } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'svg'], true)) {
        // Images – cache for 7 days
        header('Cache-Control: public, max-age=604800');
    } else {
        // CSS/JS without version param – cache for 1 hour, revalidate
        header('Cache-Control: public, max-age=3600, must-revalidate');
    }

    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }

    // Gzip compress text-based assets
    if (in_array($ext, $compressible, true) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
        $compressed = gzencode(file_get_contents($filePath), 6);
        header('Content-Encoding: gzip');
        header('Content-Length: ' . strlen($compressed));
        header('Vary: Accept-Encoding');
        echo $compressed;
        return true;
    }

    // Let the built-in server handle binary files directly
    return false;
}

// Dynamic routes → index.php
chdir(__DIR__);
include __DIR__ . '/index.php';
