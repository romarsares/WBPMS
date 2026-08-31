<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Routing\Router;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Session\DatabaseSessionHandler;

// Load environment variables from .env file if present.
// In production, variables are injected by the server/container environment.
if (file_exists(APP_ROOT . '/.env')) {
    $dotenv = Dotenv::createUnsafeMutable(APP_ROOT);
    $dotenv->load();
}

$dotenv = Dotenv::createUnsafeMutable(APP_ROOT);
$dotenv->required([
    'APP_ENV',
    'APP_BASE_URL',
    'APP_KEY',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
])->notEmpty();

// Configure PHP error display based on environment.
$appEnv = $_ENV['APP_ENV'] ?? 'production';
if ($appEnv === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// Asia/Manila is the business boundary timezone.
// Stored instants are UTC; conversion happens here and in repositories.
date_default_timezone_set('Asia/Manila');

// Wire the MySQL-backed session handler (ADR-0001 / ADR-0003).
// The handler reads DB config from the current environment.
if (PHP_SAPI !== 'cli') {
    $dbConfig    = require APP_ROOT . '/config/database.php';
    $connection  = new Connection($dbConfig);
    $sessionHandler = new DatabaseSessionHandler(
        $connection->pdo(),
        1800,  // 30-minute idle TTL
        43200  // 12-hour absolute TTL
    );
    session_set_save_handler($sessionHandler, true);
    AuthMiddleware::startSession();
}

// Build the router and load the explicit route table.
$router = new Router();
require APP_ROOT . '/routes/web.php';

return $router;
