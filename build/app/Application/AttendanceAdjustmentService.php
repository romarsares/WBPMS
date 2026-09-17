<?php

declare(strict_types=1);

namespace Wbpms\Application;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Wbpms\Infrastructure\Database\Connection;

/**
 * Applies auditable HR corrections to imported attendance records.
 *
 * Raw biometric punches remain immutable.  This service changes only the
 * generated attendance row and appends both a domain adjustment record and
 * an audit-log event.  Attendance belonging to an approved payroll is never
 * mutable.
 */
final class AttendanceAdjustmentService
{
    public function __construct(private Connection $connection) {}

    /** @return array<string, mixed> */
    public function findForAdjustment(int $attendanceId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT a.attendance_id, a.employee_id, a.attendance_date,
                    a.time_in, a.time_out, a.hours_worked_minutes,
                    a.late_minutes, a.undertime_minutes, a.overtime_minutes,
                    a.status, a.source,
                    CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.employee_number, ws.schedule_name, ws.work_start_time,
                    ws.work_end_time, ws.break_minutes, ws.grace_minutes,
                    ws.standard_minutes, ws.overtime_allowed
               FROM attendance a
               JOIN employee e ON e.employee_id = a.employee_id
               JOIN work_schedule ws ON ws.schedule_id = a.schedule_id
              WHERE a.attendance_id = :id"
        );
        $stmt->execute([':id' => $attendanceId]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            throw new RuntimeException('Attendance record not found.');
        }

        return $attendance;
    }

    /**
     * @param array{time_in:string,time_out:string,manual_overtime_minutes:string,reason:string} $input
     */
    public function adjust(int $attendanceId, array $input, int $actingUserId): void
    {
        if ($actingUserId < 1) {
            throw new RuntimeException('A signed-in HR user is required to adjust attendance.');
        }

        $reason = trim($input['reason']);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new RuntimeException('Provide an adjustment reason of up to 1,000 characters.');
        }

        $timeIn = $this->normaliseTime($input['time_in'], 'Time in');
        $timeOut = $this->normaliseTime($input['time_out'], 'Time out');
        if ($timeIn === null || $timeOut === null) {
            throw new RuntimeException('Time in and time out are both required to resolve attendance.');
        }

        $manualOvertime = $this->normaliseManualOvertime($input['manual_overtime_minutes']);

        $this->connection->transaction(function (PDO $pdo) use (
            $attendanceId, $actingUserId, $reason, $timeIn, $timeOut, $manualOvertime
        ): void {
            $attendance = $this->findForUpdate($pdo, $attendanceId);
            if ($this->hasApprovedPayroll($pdo, (int) $attendance['employee_id'], (string) $attendance['attendance_date'])) {
                throw new RuntimeException('Attendance cannot be adjusted because its payroll has already been approved.');
            }

            [$worked, $late, $undertime, $calculatedOvertime] = $this->calculateMinutes($attendance, $timeIn, $timeOut);
            $overtime = $manualOvertime ?? $calculatedOvertime;

            if (!(bool) $attendance['overtime_allowed'] && $overtime > 0) {
                throw new RuntimeException('This work schedule does not allow overtime.');
            }

            $timeChanged = $timeIn !== $attendance['time_in'] || $timeOut !== $attendance['time_out'];
            $overtimeChanged = $overtime !== (int) $attendance['overtime_minutes'];
            if (!$timeChanged && !$overtimeChanged) {
                throw new RuntimeException('No attendance values were changed.');
            }

            $type = $timeChanged && $overtimeChanged ? 'time_and_overtime'
                : ($timeChanged ? 'time_correction' : 'manual_overtime');
            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            $pdo->prepare(
                "UPDATE attendance
                    SET time_in = :time_in, time_out = :time_out,
                        hours_worked_minutes = :worked, late_minutes = :late,
                        undertime_minutes = :undertime, overtime_minutes = :overtime,
                        status = 'Complete', updated_at = :updated_at
                  WHERE attendance_id = :id"
            )->execute([
                ':time_in' => $timeIn,
                ':time_out' => $timeOut,
                ':worked' => $worked,
                ':late' => $late,
                ':undertime' => $undertime,
                ':overtime' => $overtime,
                ':updated_at' => $now,
                ':id' => $attendanceId,
            ]);

            $pdo->prepare(
                "INSERT INTO attendance_adjustment
                    (attendance_id, adjusted_by, old_time_in, new_time_in,
                     old_time_out, new_time_out, old_overtime_minutes,
                     new_overtime_minutes, adjustment_type, reason, adjustment_at)
                 VALUES
                    (:attendance_id, :adjusted_by, :old_time_in, :new_time_in,
                     :old_time_out, :new_time_out, :old_overtime, :new_overtime,
                     :type, :reason, :adjustment_at)"
            )->execute([
                ':attendance_id' => $attendanceId,
                ':adjusted_by' => $actingUserId,
                ':old_time_in' => $attendance['time_in'],
                ':new_time_in' => $timeIn,
                ':old_time_out' => $attendance['time_out'],
                ':new_time_out' => $timeOut,
                ':old_overtime' => (int) $attendance['overtime_minutes'],
                ':new_overtime' => $overtime,
                ':type' => $type,
                ':reason' => $reason,
                ':adjustment_at' => $now,
            ]);

            $description = json_encode([
                'attendance_date' => $attendance['attendance_date'],
                'reason' => $reason,
                'old' => ['time_in' => $attendance['time_in'], 'time_out' => $attendance['time_out'], 'overtime_minutes' => (int) $attendance['overtime_minutes']],
                'new' => ['time_in' => $timeIn, 'time_out' => $timeOut, 'overtime_minutes' => $overtime],
            ], JSON_UNESCAPED_SLASHES);
            $pdo->prepare(
                "INSERT INTO audit_logs
                    (user_id, event_type, action_performed, table_affected, record_id,
                     description, action_at, created_at)
                 VALUES
                    (:user_id, 'attendance_adjusted', 'adjust_attendance', 'attendance', :record_id,
                     :description, :action_at, :created_at)"
            )->execute([
                ':user_id' => $actingUserId,
                ':record_id' => $attendanceId,
                ':description' => $description,
                ':action_at' => $now,
                ':created_at' => $now,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function findForUpdate(PDO $pdo, int $attendanceId): array
    {
        $stmt = $pdo->prepare(
            "SELECT a.attendance_id, a.employee_id, a.attendance_date, a.time_in, a.time_out,
                    a.overtime_minutes, ws.work_start_time, ws.work_end_time,
                    ws.break_minutes, ws.grace_minutes, ws.standard_minutes, ws.overtime_allowed
               FROM attendance a
               JOIN work_schedule ws ON ws.schedule_id = a.schedule_id
              WHERE a.attendance_id = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $attendanceId]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$attendance) {
            throw new RuntimeException('Attendance record not found.');
        }
        return $attendance;
    }

    private function hasApprovedPayroll(PDO $pdo, int $employeeId, string $attendanceDate): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1
               FROM payroll p
               JOIN payroll_run pr ON pr.payroll_run_id = p.payroll_run_id
               JOIN payroll_period pp ON pp.payroll_period_id = p.payroll_period_id
              WHERE p.employee_id = :employee_id
                AND pr.status = 'Approved'
                AND :attendance_date BETWEEN pp.period_start AND pp.period_end
              LIMIT 1"
        );
        $stmt->execute([':employee_id' => $employeeId, ':attendance_date' => $attendanceDate]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array{int, int, int, int} worked, late, undertime, overtime */
    private function calculateMinutes(array $attendance, string $timeIn, string $timeOut): array
    {
        $timezone = new DateTimeZone('Asia/Manila');
        $date = (string) $attendance['attendance_date'];
        $actualIn = new DateTimeImmutable("{$date} {$timeIn}", $timezone);
        $actualOut = new DateTimeImmutable("{$date} {$timeOut}", $timezone);
        $scheduledStart = new DateTimeImmutable("{$date} {$attendance['work_start_time']}", $timezone);
        $scheduledEnd = new DateTimeImmutable("{$date} {$attendance['work_end_time']}", $timezone);
        if ($scheduledEnd <= $scheduledStart) {
            $scheduledEnd = $scheduledEnd->modify('+1 day');
            if ($actualOut <= $actualIn) {
                $actualOut = $actualOut->modify('+1 day');
            }
        }
        if ($actualOut <= $actualIn) {
            throw new RuntimeException('Time out must be later than time in.');
        }

        $elapsed = intdiv($actualOut->getTimestamp() - $actualIn->getTimestamp(), 60);
        $worked = max(0, $elapsed - (int) $attendance['break_minutes']);
        $grace = (int) $attendance['grace_minutes'];
        $late = max(0, intdiv($actualIn->getTimestamp() - $scheduledStart->getTimestamp(), 60) - $grace);
        $undertime = max(0, intdiv($scheduledEnd->getTimestamp() - $actualOut->getTimestamp(), 60));
        $overtime = max(0, $worked - (int) $attendance['standard_minutes']);
        return [$worked, $late, $undertime, $overtime];
    }

    private function normaliseTime(string $value, string $label): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/', $value)) {
            throw new RuntimeException("{$label} must use a valid 24-hour time.");
        }
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function normaliseManualOvertime(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!ctype_digit($value) || (int) $value > 1440) {
            throw new RuntimeException('Manual overtime must be a whole number from 0 to 1,440 minutes.');
        }
        return (int) $value;
    }
}
