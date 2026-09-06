<?php

/**
 * PHP built-in server router for Railway deployment.
 *
 * Usage: php -S 0.0.0.0:$PORT -t public/ router.php
 *
 * Serves real files (CSS, JS, images) directly.
 * Routes everything else through public/index.php.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = __DIR__ . '/public' . $uri;

// Serve existing static files directly (assets, images, etc.)
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Everything else goes through the front controller
require __DIR__ . '/public/index.php';
