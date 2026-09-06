<?php

declare(strict_types=1);

/**
 * WBPMS Front Controller
 *
 * The only web-accessible entry point. All HTTP requests are routed through
 * this file. The public/ directory is the sole document root.
 */

// Define the application root (one level up from public/).
define('APP_ROOT', dirname(__DIR__));

// Autoloader
require_once APP_ROOT . '/vendor/autoload.php';

// Register a last-resort error handler before bootstrapping
set_exception_handler(static function (\Throwable $e): void {
    $env = $_ENV['APP_ENV'] ?? 'production';
    http_response_code(500);

    // Detect whether the client expects HTML (browser) or JSON (API/AJAX).
    $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $wantsHtml    = str_contains($acceptHeader, 'text/html')
        || (!str_contains($acceptHeader, 'application/json') && $acceptHeader !== '');

    if ($wantsHtml) {
        // Render the 500 HTML view so browser users see a readable error page.
        header('Content-Type: text/html; charset=utf-8');
        $message = $env === 'development'
            ? htmlspecialchars($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8')
            : 'An unexpected error occurred. Please try again later.';
        $trace = $env === 'development'
            ? '<pre style="font-size:12px;overflow:auto">' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>'
            : '';
        if (defined('APP_ROOT') && file_exists(APP_ROOT . '/resources/views/errors/500.php')) {
            require APP_ROOT . '/resources/views/errors/500.php';
        } else {
            // Absolute fallback if the view itself cannot be loaded
            echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>500 Internal Server Error</title></head>'
               . '<body><h1>500 — Internal Server Error</h1><p>' . $message . '</p>' . $trace . '</body></html>';
        }
        return;
    }

    // JSON response for API / AJAX clients
    header('Content-Type: application/json; charset=utf-8');
    if ($env === 'development') {
        echo json_encode([
            'error' => [
                'code'    => 'INTERNAL_SERVER_ERROR',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ],
        ]);
    } else {
        echo json_encode([
            'error' => [
                'code'    => 'INTERNAL_SERVER_ERROR',
                'message' => 'An unexpected error occurred. Please try again later.',
            ],
        ]);
    }
});

// Bootstrap application and get the router back
/** @var \Wbpms\Http\Routing\Router $router */
$router = require_once APP_ROOT . '/bootstrap/app.php';

// Dispatch the current request
$router->handle();
