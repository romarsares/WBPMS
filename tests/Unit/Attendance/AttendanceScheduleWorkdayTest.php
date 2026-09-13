<?php

declare(strict_types=1);

namespace Wbpms\Tests\Unit\Attendance;

use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Wbpms\Http\Controllers\AttendanceController;

final class AttendanceScheduleWorkdayTest extends TestCase
{
    public function testAbsenceWorkdaysFollowTheEmployeeScheduleAndItsEffectiveDates(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE work_schedule (schedule_id INTEGER PRIMARY KEY, working_days TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE employee_schedule_assignment (employee_id INTEGER NOT NULL, schedule_id INTEGER NOT NULL, effective_from TEXT NOT NULL, effective_to TEXT NULL)');
        $pdo->exec("INSERT INTO work_schedule (schedule_id, working_days) VALUES
            (1, '[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\"]'),
            (2, '[\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\"]')");
        $pdo->exec("INSERT INTO employee_schedule_assignment (employee_id, schedule_id, effective_from, effective_to) VALUES
            (21, 1, '2026-09-01', '2026-09-15'),
            (21, 2, '2026-09-16', NULL)");

        $method = new ReflectionMethod(AttendanceController::class, 'scheduledWorkingDays');
        $workdays = $method->invoke(
            new AttendanceController(),
            $pdo,
            [['employee_id' => 21]],
            ['2026-09-14', '2026-09-15', '2026-09-16', '2026-09-19', '2026-09-20'],
            '2026-09-14',
            '2026-09-20',
        );

        self::assertTrue($workdays[21]['2026-09-14']);  // Monday: first schedule
        self::assertTrue($workdays[21]['2026-09-15']);  // Tuesday: first schedule's last day
        self::assertTrue($workdays[21]['2026-09-16']);  // Wednesday: second schedule
        self::assertTrue($workdays[21]['2026-09-19']);  // Saturday: second schedule
        self::assertFalse($workdays[21]['2026-09-20']); // Sunday: rest day
    }

    public function testNoScheduleDoesNotCreateAnAbsenceWorkday(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE work_schedule (schedule_id INTEGER PRIMARY KEY, working_days TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE employee_schedule_assignment (employee_id INTEGER NOT NULL, schedule_id INTEGER NOT NULL, effective_from TEXT NOT NULL, effective_to TEXT NULL)');

        $method = new ReflectionMethod(AttendanceController::class, 'scheduledWorkingDays');
        $workdays = $method->invoke(
            new AttendanceController(),
            $pdo,
            [['employee_id' => 22]],
            ['2026-09-14'],
            '2026-09-14',
            '2026-09-14',
        );

        self::assertArrayNotHasKey('2026-09-14', $workdays[22] ?? []);
    }

    public function testUploadCoverageDistinguishesApprovedDraftAndMissingImports(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE attendance_import_batch (device_id INTEGER NOT NULL, source_year INTEGER NOT NULL, source_month INTEGER NOT NULL, status TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE biometric_device_branch (device_id INTEGER NOT NULL, branch_id INTEGER NOT NULL, effective_from TEXT NOT NULL, effective_to TEXT NULL)');
        $pdo->exec('CREATE TABLE employee_branch_assignment (employee_id INTEGER NOT NULL, branch_id INTEGER NOT NULL, effective_from TEXT NOT NULL, effective_to TEXT NULL)');
        $pdo->exec("INSERT INTO attendance_import_batch VALUES
            (1, 2026, 9, 'Approved'),
            (1, 2026, 10, 'Draft')");
        $pdo->exec("INSERT INTO biometric_device_branch VALUES (1, 5, '2026-01-01', NULL)");
        $pdo->exec("INSERT INTO employee_branch_assignment VALUES (21, 5, '2026-01-01', NULL)");

        $method = new ReflectionMethod(AttendanceController::class, 'attendanceUploadCoverage');
        $coverage = $method->invoke(
            new AttendanceController(),
            $pdo,
            [['employee_id' => 21], ['employee_id' => 22]],
            ['2026-09-14', '2026-10-01'],
        );

        self::assertSame('Covered', $coverage[21]['2026-09-14']);
        self::assertSame('Draft', $coverage[21]['2026-10-01']);
        self::assertArrayNotHasKey(22, $coverage);
    }
}
