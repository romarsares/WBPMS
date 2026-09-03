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

use Wbpms\Http\Controllers\AttendanceController;
use Wbpms\Http\Controllers\AuthController;
use Wbpms\Http\Controllers\BenefitsController;
use Wbpms\Http\Controllers\DashboardController;
use Wbpms\Http\Controllers\EmployeeController;
use Wbpms\Http\Controllers\EmployeePortalController;
use Wbpms\Http\Controllers\HealthController;
use Wbpms\Http\Controllers\PositionController;
use Wbpms\Http\Controllers\OwnerController;
use Wbpms\Http\Controllers\PayrollController;
use Wbpms\Http\Controllers\ReportsController;
use Wbpms\Http\Controllers\RequestsController;
use Wbpms\Http\Controllers\SalaryController;
use Wbpms\Http\Controllers\ScheduleController;
use Wbpms\Http\Controllers\UserController;

// ---------------------------------------------------------------------------
// Public routes — no authentication required
// ---------------------------------------------------------------------------

// Health check
$router->add('GET', '/health', [HealthController::class, 'index'], []);

// Authentication
$router->add('GET',  '/login',         [AuthController::class, 'showLogin'],    []);
$router->add('POST', '/login',         [AuthController::class, 'login'],        []);
$router->add('GET',  '/forgot',        [AuthController::class, 'showForgot'],   []);
$router->add('POST', '/forgot',        [AuthController::class, 'sendOtp'],      []);
$router->add('GET',  '/reset-otp',     [AuthController::class, 'showOtpForm'],  []);
$router->add('POST', '/reset-otp',     [AuthController::class, 'verifyOtp'],    []);
$router->add('GET',  '/reset-password',[AuthController::class, 'showResetForm'],[]);
$router->add('POST', '/reset-password',[AuthController::class, 'resetPassword'],[]);

// ---------------------------------------------------------------------------
// Shared authenticated routes
// ---------------------------------------------------------------------------

$router->add('POST', '/logout', [AuthController::class, 'logout'], ['BusinessOwner', 'HRHead', 'Employee']);

// Requirement 13: mandatory first-login password change (any authenticated role)
$router->add('GET',  '/change-password', [AuthController::class, 'showChangePassword'], ['BusinessOwner', 'HRHead', 'Employee']);
$router->add('POST', '/change-password', [AuthController::class, 'changePassword'],     ['BusinessOwner', 'HRHead', 'Employee']);

// ---------------------------------------------------------------------------
// Business Owner — dashboard
// ---------------------------------------------------------------------------

$router->add('GET', '/owner/dashboard', [DashboardController::class, 'ownerDashboard'], ['BusinessOwner']);

// ---------------------------------------------------------------------------
// Business Owner — User Management CRUD  (REQ004–REQ008)
// NOTE: /users/create must be registered before /users/{id} to avoid literal
//       "create" being captured as an {id} segment.
// ---------------------------------------------------------------------------

$router->add('GET',  '/users',              [UserController::class, 'index'],         ['BusinessOwner', 'HRHead']);
$router->add('GET',  '/users/create',       [UserController::class, 'create'],        ['BusinessOwner']);
$router->add('POST', '/users',              [UserController::class, 'store'],         ['BusinessOwner']);
$router->add('GET',  '/users/{id}/edit',    [UserController::class, 'edit'],          ['BusinessOwner']);
$router->add('POST', '/users/{id}',         [UserController::class, 'update'],        ['BusinessOwner']);
$router->add('POST', '/users/{id}/toggle',  [UserController::class, 'toggle'],        ['BusinessOwner']);
$router->add('POST', '/users/{id}/archive', [UserController::class, 'archive'],       ['BusinessOwner']);
$router->add('POST', '/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['BusinessOwner', 'HRHead']);

// ---------------------------------------------------------------------------
// Business Owner — Payroll review  (REQ050, REQ051)
// ---------------------------------------------------------------------------

$router->add('GET',  '/owner/payroll',               [OwnerController::class, 'payrollList'], ['BusinessOwner']);
$router->add('GET',  '/owner/payroll/{id}/review',   [OwnerController::class, 'reviewForm'],  ['BusinessOwner']);
$router->add('POST', '/owner/payroll/{id}/approve',  [OwnerController::class, 'approve'],     ['BusinessOwner']);
$router->add('POST', '/owner/payroll/{id}/return',   [OwnerController::class, 'returnRun'],   ['BusinessOwner']);

// ---------------------------------------------------------------------------
// HR Head — dashboard
// ---------------------------------------------------------------------------

$router->add('GET', '/hr/dashboard', [DashboardController::class, 'hrDashboard'], ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Employee management  (REQ009–REQ017)
// NOTE: /hr/employees/new must be before /hr/employees/{id}
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/employees',                [EmployeeController::class, 'index'],        ['HRHead']);
$router->add('GET',  '/hr/employees/new',             [EmployeeController::class, 'create'],       ['HRHead']);
$router->add('POST', '/hr/employees',                [EmployeeController::class, 'store'],         ['HRHead']);
$router->add('GET',  '/hr/employees/{id}/transfer',  [EmployeeController::class, 'transferForm'],  ['HRHead']);
$router->add('POST', '/hr/employees/{id}/transfer',  [EmployeeController::class, 'transfer'],      ['HRHead']);
$router->add('GET',  '/hr/employees/{id}/archive',   [EmployeeController::class, 'archiveForm'],   ['HRHead']);
$router->add('POST', '/hr/employees/{id}/archive',   [EmployeeController::class, 'archive'],       ['HRHead']);
$router->add('GET',  '/hr/employees/{id}/rehire',    [EmployeeController::class, 'rehireForm'],    ['HRHead']);
$router->add('POST', '/hr/employees/{id}/rehire',    [EmployeeController::class, 'rehire'],        ['HRHead']);
$router->add('GET',  '/hr/employees/{id}',           [EmployeeController::class, 'show'],          ['HRHead']);
$router->add('GET',  '/hr/employees/{id}/edit',      [EmployeeController::class, 'editForm'],      ['HRHead']);
$router->add('POST', '/hr/employees/{id}',           [EmployeeController::class, 'update'],        ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Work schedule management  (REQ025–REQ031)
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/schedules',               [ScheduleController::class, 'index'],       ['HRHead']);
$router->add('POST', '/hr/schedules',               [ScheduleController::class, 'store'],       ['HRHead']);
$router->add('GET',  '/hr/schedules/assign',        [ScheduleController::class, 'assignForm'],  ['HRHead']);
$router->add('POST', '/hr/schedules/assign',        [ScheduleController::class, 'assign'],      ['HRHead']);
$router->add('GET',  '/hr/schedules/holidays',      [ScheduleController::class, 'holidayIndex'],['HRHead']);
$router->add('POST', '/hr/schedules/holidays',      [ScheduleController::class, 'storeHoliday'],['HRHead']);
$router->add('GET',  '/hr/schedules/holidays/{id}/edit',   [ScheduleController::class, 'editHoliday'],  ['HRHead']);
$router->add('POST', '/hr/schedules/holidays/{id}',        [ScheduleController::class, 'updateHoliday'],['HRHead']);
$router->add('POST', '/hr/schedules/holidays/{id}/delete', [ScheduleController::class, 'deleteHoliday'],['HRHead']);
$router->add('GET',  '/hr/schedules/{id}/edit',     [ScheduleController::class, 'editForm'],    ['HRHead']);
$router->add('POST', '/hr/schedules/{id}',          [ScheduleController::class, 'update'],      ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Attendance import  (REQ018–REQ024)
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/attendance',        [AttendanceController::class, 'index'],  ['HRHead']);
$router->add('GET',  '/hr/attendance/import', [AttendanceController::class, 'import'], ['HRHead']);
$router->add('POST', '/hr/attendance/import', [AttendanceController::class, 'upload'], ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Request Management  (REQ032–REQ046)
// NOTE: /hr/requests/create before /hr/requests/{id}
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/requests',              [RequestsController::class, 'index'],   ['HRHead']);
$router->add('GET',  '/hr/requests/{id}',         [RequestsController::class, 'show'],    ['HRHead']);
$router->add('POST', '/hr/requests/{id}/approve', [RequestsController::class, 'approve'], ['HRHead']);
$router->add('POST', '/hr/requests/{id}/reject',  [RequestsController::class, 'reject'],  ['HRHead']);
$router->add('POST', '/hr/requests/{id}/archive', [RequestsController::class, 'archive'], ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Salary Management  (REQ053–REQ057)
// NOTE: /hr/salary/create before /hr/salary/{id}
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/salary',              [SalaryController::class, 'index'],  ['HRHead']);
$router->add('GET',  '/hr/salary/create',        [SalaryController::class, 'create'], ['HRHead']);
$router->add('POST', '/hr/salary',              [SalaryController::class, 'store'],  ['HRHead']);
$router->add('GET',  '/hr/salary/{id}/edit',    [SalaryController::class, 'edit'],   ['HRHead']);
$router->add('POST', '/hr/salary/{id}',         [SalaryController::class, 'update'], ['HRHead']);
$router->add('POST', '/hr/salary/{id}/archive', [SalaryController::class, 'archive'],['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Benefits & Deductions  (REQ058–REQ062)
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/benefits',                              [BenefitsController::class, 'index'],         ['HRHead']);
$router->add('GET',  '/hr/benefits/policies',                     [BenefitsController::class, 'policies'],      ['HRHead']);
$router->add('POST', '/hr/benefits/policies/{id}/approve',        [BenefitsController::class, 'approvePolicy'], ['HRHead']);
$router->add('POST', '/hr/benefits/contributions/{id}/lock',      [BenefitsController::class, 'lockRecord'],    ['HRHead']);
$router->add('POST', '/hr/benefits/contributions/{id}/unlock',    [BenefitsController::class, 'unlockRecord'],  ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Payroll  (REQ047–REQ052)
// NOTE: /hr/payroll/create before /hr/payroll/{id}
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/payroll',                  [PayrollController::class, 'index'],        ['HRHead']);
$router->add('GET',  '/hr/payroll/periods',           [PayrollController::class, 'listPeriods'],   ['HRHead']);
$router->add('POST', '/hr/payroll/periods',           [PayrollController::class, 'storePeriod'],   ['HRHead']);
$router->add('GET',  '/hr/payroll/create',            [PayrollController::class, 'create'],        ['HRHead']);
$router->add('POST', '/hr/payroll',                  [PayrollController::class, 'store'],    ['HRHead']);
$router->add('GET',  '/hr/payroll/{id}',             [PayrollController::class, 'show'],     ['HRHead']);
$router->add('POST', '/hr/payroll/{id}/compute',     [PayrollController::class, 'compute'],  ['HRHead']);
$router->add('POST', '/hr/payroll/{id}/submit',      [PayrollController::class, 'submit'],   ['HRHead']);

// ---------------------------------------------------------------------------
// HR Head — Reports  (REQ065–REQ073)
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/reports',        [ReportsController::class, 'index'],    ['HRHead', 'BusinessOwner']);
$router->add('GET',  '/hr/reports/export', [ReportsController::class, 'export'],   ['HRHead', 'BusinessOwner']);

// ---------------------------------------------------------------------------
// HR Head — Settings  (Job Positions)
// NOTE: /hr/settings/positions/{id}/toggle before /hr/settings/positions/{id}
// ---------------------------------------------------------------------------

$router->add('GET',  '/hr/settings/positions',              [PositionController::class, 'index'],  ['HRHead']);
$router->add('POST', '/hr/settings/positions',              [PositionController::class, 'store'],  ['HRHead']);
$router->add('POST', '/hr/settings/positions/{id}/toggle',  [PositionController::class, 'toggle'], ['HRHead']);
$router->add('POST', '/hr/settings/positions/{id}',         [PositionController::class, 'update'], ['HRHead']);

// ---------------------------------------------------------------------------
// Employee self-service portal  (REQ074–REQ082)
// NOTE: /employee/requests/new must be before /employee/requests/{id}
//       /employee/payslips/{id} has no collision — no literal after /payslips
// ---------------------------------------------------------------------------

$router->add('GET',  '/employee/dashboard',       [DashboardController::class,      'empDashboard'],  ['Employee']);
$router->add('GET',  '/employee/attendance',      [EmployeePortalController::class, 'attendance'],    ['Employee']);
$router->add('GET',  '/employee/requests',        [EmployeePortalController::class, 'requests'],      ['Employee']);
$router->add('GET',  '/employee/requests/new',    [EmployeePortalController::class, 'requestForm'],   ['Employee']);
$router->add('POST', '/employee/requests',        [EmployeePortalController::class, 'storeRequest'],  ['Employee']);
$router->add('GET',  '/employee/payslips',            [EmployeePortalController::class, 'payslips'],      ['Employee']);
$router->add('GET',  '/employee/payslips/{id}',       [EmployeePortalController::class, 'payslipDetail'], ['Employee']);
$router->add('GET',  '/employee/payslips/{id}/print', [EmployeePortalController::class, 'payslipPrint'],  ['Employee']);
