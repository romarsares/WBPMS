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

use Wbpms\Http\Controllers\AuthController;
use Wbpms\Http\Controllers\DashboardController;
use Wbpms\Http\Controllers\EmployeeController;
use Wbpms\Http\Controllers\EmployeePortalController;
use Wbpms\Http\Controllers\HealthController;
use Wbpms\Http\Controllers\OwnerController;
use Wbpms\Http\Controllers\ScheduleController;

// ---------------------------------------------------------------------------
// Public routes — no authentication required
// ---------------------------------------------------------------------------

// Health check
$router->add('GET', '/health', [HealthController::class, 'index'], []);

// Authentication
$router->add('GET',  '/login',  [AuthController::class, 'showLogin'],  []);
$router->add('POST', '/login',  [AuthController::class, 'login'],      []);

// ---------------------------------------------------------------------------
// Authenticated routes — require valid session
// ---------------------------------------------------------------------------

$router->add('POST', '/logout', [AuthController::class, 'logout'], ['BusinessOwner', 'HRHead', 'Employee']);

// ---------------------------------------------------------------------------
// HR Head — dashboard
// ---------------------------------------------------------------------------

$router->add('GET', '/hr/dashboard', [DashboardController::class, 'hrDashboard'], ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Employee management  (tasks 4.1–4.3)
// ---------------------------------------------------------------------------

// NOTE: '/hr/employees/new' must be registered before '/hr/employees/{id}'
// so the literal segment "new" is matched first.
$router->add('GET',  '/hr/employees',           [EmployeeController::class, 'index'],    ['HRHead']);
$router->add('GET',  '/hr/employees/new',        [EmployeeController::class, 'create'],   ['HRHead']);
$router->add('POST', '/hr/employees',           [EmployeeController::class, 'store'],    ['HRHead']);
$router->add('GET',  '/hr/employees/{id}',      [EmployeeController::class, 'show'],     ['HRHead']);
$router->add('GET',  '/hr/employees/{id}/edit', [EmployeeController::class, 'editForm'], ['HRHead']);
$router->add('POST', '/hr/employees/{id}',      [EmployeeController::class, 'update'],   ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Work schedule management  (tasks 5.1–5.2)
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/schedules', [ScheduleController::class, 'index'], ['HRHead']);
$router->add('POST', '/hr/schedules', [ScheduleController::class, 'store'], ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Attendance (routes wired; controller implementation is B3/deferred)
// ---------------------------------------------------------------------------

// These routes reference AttendanceController which is not yet implemented.
// They are commented out so the router doesn't fail on a missing class.
// Uncomment once AttendanceController is implemented.
// $router->add('GET',  '/hr/attendance',        [AttendanceController::class, 'index'],  ['HRHead']);
// $router->add('GET',  '/hr/attendance/import', [AttendanceController::class, 'import'], ['HRHead']);
// $router->add('POST', '/hr/attendance/import', [AttendanceController::class, 'upload'], ['HRHead']);

// ---------------------------------------------------------------------------
// Business Owner — dashboard and payroll review
// ---------------------------------------------------------------------------

$router->add('GET', '/owner/dashboard',              [DashboardController::class, 'ownerDashboard'], ['BusinessOwner']);
$router->add('GET', '/owner/payroll',                [OwnerController::class,     'payrollList'],    ['BusinessOwner']);
$router->add('GET', '/owner/payroll/{id}/review',    [OwnerController::class,     'reviewForm'],     ['BusinessOwner']);

// ---------------------------------------------------------------------------
// Employee self-service portal
// ---------------------------------------------------------------------------

$router->add('GET',  '/employee/dashboard',     [DashboardController::class,       'empDashboard'],  ['Employee']);
$router->add('GET',  '/employee/attendance',    [EmployeePortalController::class,  'attendance'],    ['Employee']);
$router->add('GET',  '/employee/requests',      [EmployeePortalController::class,  'requests'],      ['Employee']);
$router->add('GET',  '/employee/requests/new',  [EmployeePortalController::class,  'requestForm'],   ['Employee']);
$router->add('POST', '/employee/requests',      [EmployeePortalController::class,  'storeRequest'],  ['Employee']);
$router->add('GET',  '/employee/payslips',      [EmployeePortalController::class,  'payslips'],      ['Employee']);
$router->add('GET',  '/employee/payslips/{id}', [EmployeePortalController::class,  'payslipDetail'], ['Employee']);
