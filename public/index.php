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
