<?php

declare(strict_types=1);

namespace Wbpms\Tests\Integration;

use RuntimeException;
use Wbpms\Application\AttendanceAdjustmentService;
use Wbpms\Infrastructure\Database\Connection;

final class AttendanceAdjustmentServiceTest extends IntegrationTestCase
{
    private AttendanceAdjustmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceAdjustmentService(new Connection([], $this->pdo));
    }

    public function testAdjustmentResolvesIncompleteAttendanceAndAuditsManualOvertime(): void
    {
        [$attendanceId, $userId] = $this->createAttendanceFixture('2026-09-07');

        $this->service->adjust($attendanceId, [
            'time_in' => '07:10',
            'time_out' => '17:30',
            'manual_overtime_minutes' => '60',
            'reason' => 'Supervisor-approved overtime; gate log verified.',
        ], $userId);

        $stmt = $this->pdo->prepare(
            'SELECT time_in, time_out, hours_worked_minutes, late_minutes,
                    undertime_minutes, overtime_minutes, status
               FROM attendance WHERE attendance_id = :id'
        );
        $stmt->execute([':id' => $attendanceId]);
        $attendance = $stmt->fetch();
        $this->assertSame('07:10:00', $attendance['time_in']);
        $this->assertSame('17:30:00', $attendance['time_out']);
        $this->assertSame(560, (int) $attendance['hours_worked_minutes']);
        $this->assertSame(10, (int) $attendance['late_minutes']);
        $this->assertSame(0, (int) $attendance['undertime_minutes']);
        $this->assertSame(60, (int) $attendance['overtime_minutes']);
        $this->assertSame('Complete', $attendance['status']);

        $adjustment = $this->pdo->query(
            "SELECT old_time_in, new_time_in, old_overtime_minutes, new_overtime_minutes, adjustment_type
               FROM attendance_adjustment ORDER BY adjustment_id DESC LIMIT 1"
        )->fetch();
        $this->assertNull($adjustment['old_time_in']);
        $this->assertSame('07:10:00', $adjustment['new_time_in']);
        $this->assertSame(0, (int) $adjustment['old_overtime_minutes']);
        $this->assertSame(60, (int) $adjustment['new_overtime_minutes']);
        $this->assertSame('time_and_overtime', $adjustment['adjustment_type']);

        $audit = $this->pdo->query(
            "SELECT event_type FROM audit_logs WHERE event_type = 'attendance_adjusted' ORDER BY log_id DESC LIMIT 1"
        )->fetchColumn();
        $this->assertSame('attendance_adjusted', $audit);
    }

    public function testAdjustmentIsBlockedWhenRelatedPayrollIsApproved(): void
    {
        [$attendanceId, $userId, $employeeId, $branchId, $assignmentId, $scheduleId] = $this->createAttendanceFixture('2026-09-07');
        $periodId = $this->insertPayrollPeriod('2026-09-06', '2026-09-11', '2026-09-11');
        $policyId = $this->insertPayrollPolicy();
        $runId = $this->insertPayrollRun($periodId, $branchId, $policyId, 'Approved');
        $salaryId = $this->insertSalary($employeeId);
        $this->insertPayroll($runId, $periodId, $employeeId, $assignmentId, $salaryId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payroll has already been approved');
        $this->service->adjust($attendanceId, [
            'time_in' => '07:00',
            'time_out' => '16:00',
            'manual_overtime_minutes' => '',
            'reason' => 'Late biometric synchronization.',
        ], $userId);
    }

    /** @return array{int, int, int, int, int, int} */
    private function createAttendanceFixture(string $date): array
    {
        $roleId = $this->insertRole();
        $employeeId = $this->insertEmployee();
        $userId = $this->insertUser($roleId);
        $branchId = $this->insertBranch();
        $assignmentId = $this->insertBranchAssignment($employeeId, $branchId, '2026-01-01');
        $scheduleId = $this->insertSchedule();
        $stmt = $this->pdo->prepare(
            "INSERT INTO attendance
                (employee_id, branch_assignment_id, schedule_id, attendance_date,
                 hours_worked_minutes, late_minutes, undertime_minutes, overtime_minutes, status, source)
             VALUES (:employee_id, :assignment_id, :schedule_id, :attendance_date,
                     0, 0, 0, 0, 'Incomplete', 'manual')"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':assignment_id' => $assignmentId,
            ':schedule_id' => $scheduleId,
            ':attendance_date' => $date,
        ]);

        return [(int) $this->pdo->lastInsertId(), $userId, $employeeId, $branchId, $assignmentId, $scheduleId];
    }

    private function insertSchedule(): int
    {
        $name = 'Schedule ' . uniqid();
        $stmt = $this->pdo->prepare(
            "INSERT INTO work_schedule
                (schedule_name, working_days, rest_days, break_minutes, grace_minutes,
                 overtime_allowed, work_start_time, work_end_time, standard_minutes,
                 effective_from, status)
             VALUES (:name, :working_days, :rest_days, 60, 0,
                     1, '07:00:00', '16:00:00', 480, '2026-01-01', 'Active')"
        );
        $stmt->execute([
            ':name' => $name,
            ':working_days' => '["Monday","Tuesday","Wednesday","Thursday","Friday"]',
            ':rest_days' => '["Saturday","Sunday"]',
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
