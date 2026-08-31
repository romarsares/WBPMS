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

$router->add('POST', '/logout',    [\Wbpms\Http\Controllers\AuthController::class,     'logout'],  ['BusinessOwner', 'HRHead', 'Employee']);
$router->add('GET',  '/dashboard', [\Wbpms\Http\Controllers\DashboardController::class, 'index'],  ['BusinessOwner', 'HRHead', 'Employee']);

// ---- Business Owner ----
$router->add('GET', '/users',    [\Wbpms\Http\Controllers\StubController::class, 'users'],    ['BusinessOwner']);
$router->add('GET', '/requests', [\Wbpms\Http\Controllers\StubController::class, 'requests'], ['BusinessOwner', 'HRHead']);
$router->add('GET', '/payroll',  [\Wbpms\Http\Controllers\StubController::class, 'payroll'],  ['BusinessOwner', 'HRHead']);
$router->add('GET', '/reports',  [\Wbpms\Http\Controllers\StubController::class, 'reports'],  ['BusinessOwner', 'HRHead']);

// ---- HR Head ----
$router->add('GET', '/employees', [\Wbpms\Http\Controllers\StubController::class, 'employees'], ['HRHead']);
$router->add('GET', '/attendance',[\Wbpms\Http\Controllers\StubController::class, 'attendance'],['HRHead']);
$router->add('GET', '/schedule',  [\Wbpms\Http\Controllers\StubController::class, 'schedule'],  ['HRHead']);
$router->add('GET', '/salary',    [\Wbpms\Http\Controllers\StubController::class, 'salary'],    ['HRHead']);
$router->add('GET', '/benefits',  [\Wbpms\Http\Controllers\StubController::class, 'benefits'],  ['HRHead']);

// ---- Employee ----
$router->add('GET', '/my-attendance', [\Wbpms\Http\Controllers\StubController::class, 'myAttendance'], ['Employee']);
$router->add('GET', '/my-requests',   [\Wbpms\Http\Controllers\StubController::class, 'myRequests'],   ['Employee']);
$router->add('GET', '/my-payslips',   [\Wbpms\Http\Controllers\StubController::class, 'myPayslips'],   ['Employee']);
