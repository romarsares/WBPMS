<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Wbpms\Http\Middleware\AuthMiddleware;
use Wbpms\Http\Routing\Router;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Session\DatabaseSessionHandler;

// Load environment variables from .env file if present.
// In production (e.g. Railway), variables are injected by the container into
// the process environment (accessible via getenv()) rather than $_ENV.
// Whether PHP populates $_ENV from the process environment depends on the
// `variables_order` INI setting; on many production stacks it is "GPCS"
// (no "E"), so $_ENV stays empty for container-injected variables.
//
// Strategy:
//   1. If a .env file exists, load it via phpdotenv (writes to both $_ENV and
//      putenv/getenv via createUnsafeMutable).
//   2. After loading, back-fill $_ENV from getenv() for any variable that is
//      not already present in $_ENV.  This makes Railway-injected variables
//      visible to all application code that reads $_ENV directly, without
//      overwriting values that were explicitly set by the .env file.
$dotenv = Dotenv::createUnsafeMutable(APP_ROOT);
if (file_exists(APP_ROOT . '/.env')) {
    $dotenv->load();
}

// Back-fill $_ENV from the process environment for variables present in
// getenv() but absent from $_ENV.  This handles production hosts (Railway,
// Heroku, Docker) that inject variables through the process environment when
// no .env file is deployed and PHP's variables_order does not include "E".
// Existing $_ENV entries (written by phpdotenv above) are never overwritten.
$_wbpmsProcessEnv = getenv();
if (is_array($_wbpmsProcessEnv)) {
    foreach ($_wbpmsProcessEnv as $_wbpmsKey => $_wbpmsVal) {
        if (!array_key_exists($_wbpmsKey, $_ENV)) {
            $_ENV[$_wbpmsKey] = $_wbpmsVal;
        }
    }
    unset($_wbpmsProcessEnv, $_wbpmsKey, $_wbpmsVal);
}

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
