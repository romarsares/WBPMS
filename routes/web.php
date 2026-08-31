<?php

declare(strict_types=1);

/**
 * WBPMS explicit route table.
 *
 * Format: $router->add(METHOD, '/path', [ControllerClass::class, 'method'], ['RoleName', ...]);
 * An empty roles array means the route is public (no authentication required).
 *
 * Role names must exactly match the role_name values seeded in the role table:
 *   BusinessOwner | HRHead | Employee
 */

/** @var \Wbpms\Http\Routing\Router $router */

// ---------------------------------------------------------------------------
// Public routes — no authentication required
// ---------------------------------------------------------------------------

// Health check
$router->add('GET', '/health', [\Wbpms\Http\Controllers\HealthController::class, 'index'], []);

// Authentication
$router->add('GET',  '/login',  [\Wbpms\Http\Controllers\AuthController::class, 'showLogin'],  []);
$router->add('POST', '/login',  [\Wbpms\Http\Controllers\AuthController::class, 'login'],      []);

// ---------------------------------------------------------------------------
// Authenticated routes — require valid session
// ---------------------------------------------------------------------------

$router->add('POST', '/logout', [\Wbpms\Http\Controllers\AuthController::class, 'logout'], ['BusinessOwner', 'HRHead', 'Employee']);
