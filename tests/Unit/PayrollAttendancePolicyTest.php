<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Wbpms\Application\PayrollService;
use Wbpms\Infrastructure\Database\Connection;

final class PayrollAttendancePolicyTest extends TestCase
{
    public function testItPaysApprovedLeaveAndRecordsOnlyTrueUnpaidAbsences(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE work_schedule (schedule_id INTEGER PRIMARY KEY, working_days TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE employee_schedule_assignment (employee_id INTEGER NOT NULL, schedule_id INTEGER NOT NULL, effective_from TEXT NOT NULL, effective_to TEXT NULL)');
        $pdo->exec('CREATE TABLE request_type (request_type_id INTEGER PRIMARY KEY, type_name TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE request (request_id INTEGER PRIMARY KEY, employee_id INTEGER NOT NULL, request_type_id INTEGER NOT NULL, status TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE leave_request_detail (request_id INTEGER PRIMARY KEY, leave_type TEXT NOT NULL, start_date TEXT NOT NULL, end_date TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE holiday_calendar (holiday_date TEXT NOT NULL, status TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE employee_branch_assignment (employee_id INTEGER NOT NULL, branch_id INTEGER NOT NULL, effective_from TEXT NOT NULL, effective_to TEXT NULL)');
        $pdo->exec('CREATE TABLE biometric_device_branch (device_id INTEGER NOT NULL, branch_id INTEGER NOT NULL, effective_from TEXT NOT NULL, effective_to TEXT NULL)');
        $pdo->exec('CREATE TABLE attendance_import_batch (device_id INTEGER NOT NULL, source_year INTEGER NOT NULL, source_month INTEGER NOT NULL, status TEXT NOT NULL)');
        $pdo->exec("INSERT INTO work_schedule VALUES (1, '[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\"]')");
        $pdo->exec("INSERT INTO employee_schedule_assignment VALUES (61, 1, '2026-01-01', NULL)");
        $pdo->exec("INSERT INTO request_type VALUES (1, 'Leave')");
        $pdo->exec("INSERT INTO request VALUES (31, 61, 1, 'Approved')");
        $pdo->exec("INSERT INTO leave_request_detail VALUES (31, 'Sick', '2026-01-07', '2026-01-08')");
        $pdo->exec("INSERT INTO holiday_calendar VALUES ('2026-01-09', 'Active')");
        $pdo->exec("INSERT INTO employee_branch_assignment VALUES (61, 7, '2026-01-01', NULL)");
        $pdo->exec("INSERT INTO biometric_device_branch VALUES (2, 7, '2026-01-01', NULL)");
        $pdo->exec("INSERT INTO attendance_import_batch VALUES (2, 2026, 1, 'Approved')");

        $method = new ReflectionMethod(PayrollService::class, 'attendancePolicyForPeriod');
        $result = $method->invoke(
            new PayrollService(new Connection([])),
            $pdo,
            61,
            '2026-01-05',
            '2026-01-09',
            ['2026-01-06' => true],
        );

        self::assertSame(1, $result['unpaid_absence_days']);
        self::assertSame([
            ['request_id' => 31, 'leave_type' => 'Sick', 'days' => 2],
        ], $result['paid_leave']);
    }
}
