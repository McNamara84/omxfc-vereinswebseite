<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * This file allows the PHP built-in server to correctly serve static files
 * from the public directory while routing all other requests through Laravel.
 *
 * @see https://www.php.net/manual/en/features.commandline.webserver.php
 */

// Change to the project root directory
chdir(__DIR__);

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Remove query string for file existence check
$cleanUri = strtok($uri, '?');

$publicPath = __DIR__.'/public'.$cleanUri;

// Check if the file exists in public directory
if ($cleanUri !== '/' && is_file($publicPath)) {
    // Determine MIME type
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'map' => 'application/json',
        'xml' => 'application/xml',
        'txt' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
        'pdf' => 'application/pdf',
    ];

    $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));
    $mime = $mimeTypes[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($publicPath) : 'application/octet-stream');

    header('Content-Type: '.$mime);
    header('Content-Length: '.filesize($publicPath));

    // Cache static assets for 1 hour in testing
    if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp', 'avif', 'woff', 'woff2', 'ttf', 'eot'])) {
        header('Cache-Control: public, max-age=3600');
    }

    readfile($publicPath);

    return;
}

require_once __DIR__.'/public/index.php';
