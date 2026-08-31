<?php

declare(strict_types=1);

/*
 * Route contract for the shared dispatcher. Controllers are deliberately thin;
 * this file uses the shared Router API and role names. Include it from
 * routes/web.php once its controllers are registered.
 */
return static function (\Wbpms\Http\Routing\Router $router): void {
    $router->add('GET', '/hr/employees/new', [\Wbpms\Http\Controllers\EmployeeController::class, 'createForm'], ['HRHead']);
    $router->add('POST', '/hr/employees', [\Wbpms\Http\Controllers\EmployeeController::class, 'store'], ['HRHead']);
    $router->add('GET', '/hr/schedules', [\Wbpms\Http\Controllers\ScheduleController::class, 'index'], ['HRHead']);
    $router->add('POST', '/hr/schedules', [\Wbpms\Http\Controllers\ScheduleController::class, 'store'], ['HRHead']);
    $router->add('GET', '/hr/attendance/import', [\Wbpms\Http\Controllers\AttendanceController::class, 'importForm'], ['HRHead']);
    $router->add('POST', '/hr/attendance/import', [\Wbpms\Http\Controllers\AttendanceController::class, 'import'], ['HRHead']);
    $router->add('GET', '/hr/attendance', [\Wbpms\Http\Controllers\AttendanceController::class, 'index'], ['HRHead']);
    $router->add('GET', '/employee/attendance', [\Wbpms\Http\Controllers\EmployeePortalController::class, 'attendance'], ['Employee']);
    $router->add('GET', '/employee/payslips/{payslipId}', [\Wbpms\Http\Controllers\EmployeePortalController::class, 'payslip'], ['Employee']);
    $router->add('GET', '/employee/payslips/{payslipId}/download', [\Wbpms\Http\Controllers\EmployeePortalController::class, 'downloadPayslip'], ['Employee']);
};
