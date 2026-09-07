<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Wbpms\Application\Attendance\AttendanceImportGateway;
use Wbpms\Application\Attendance\AttendanceImportSummary;
use Wbpms\Application\Attendance\AttendancePunchMatch;
use Wbpms\Domain\Attendance\GeneratedAttendance;
use Wbpms\Domain\Attendance\WorkSchedule;
use Wbpms\Domain\Attendance\Parsing\ParsedPunch;
use Wbpms\Infrastructure\Database\Connection;

/**
 * PDO implementation of AttendanceImportGateway.
 *
 * All SQL lives here — no SQL in controllers or services (ADR-0003).
 * Accepts a raw PDO so the controller can hand in the same connection
 * that it wraps in Connection::transaction().
 */
final class PdoAttendanceImportGateway implements AttendanceImportGateway
{
    private PDO $pdo;
    private Connection $connection;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // Wrap PDO in Connection so we can delegate transaction()
        $this->connection = new Connection([], $pdo);
    }

    // -----------------------------------------------------------------------
    // Transaction
    // -----------------------------------------------------------------------

    public function transactional(callable $operation): mixed
    {
        return $this->connection->transaction(fn () => $operation());
    }

    // -----------------------------------------------------------------------
    // Duplicate-checksum guard
    // -----------------------------------------------------------------------

    public function hasCompletedChecksum(string $sha256): bool
    {
        // Cancelled and Processing batches are excluded:
        // - Cancelled: their file may be re-uploaded to replace the cancelled import.
        // - Processing: a stale Processing row (abandoned mid-import) should not
        //   block a fresh import of the same file; createImportBatch handles cleanup.
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM attendance_import_batch
              WHERE file_checksum = :cs
                AND status IN ('Draft', 'Approved', 'Completed')"
        );
        $stmt->execute([':cs' => $sha256]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // -----------------------------------------------------------------------
    // Batch lifecycle
    // -----------------------------------------------------------------------

    public function createImportBatch(
        int    $deviceId,
        int    $uploadedByUserId,
        int    $year,
        int    $month,
        string $sha256,
        string $parserVersion,
    ): int {
        $now  = $this->utcNow();

        // Remove any stale Processing or Cancelled row for this checksum before
        // inserting a fresh batch. Processing rows are left behind when a confirm
        // request fails mid-transaction; Cancelled rows are superseded by a
        // re-upload of the same file. Both have no usable data.
        $this->pdo->prepare(
            "DELETE FROM attendance_import_batch
              WHERE file_checksum = :cs
                AND status IN ('Processing', 'Cancelled')"
        )->execute([':cs' => $sha256]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO attendance_import_batch
                (device_id, uploaded_by, file_name, file_checksum,
                 source_year, source_month, parser_version, status,
                 records_parsed, records_matched, records_unmatched,
                 duplicates_skipped, incomplete_days, multi_punch_days,
                 uploaded_at)
             VALUES
                (:device_id, :uploaded_by, '', :checksum,
                 :year, :month, :version, 'Processing',
                 0, 0, 0, 0, 0, 0,
                 :now)"
        );
        $stmt->execute([
            ':device_id'   => $deviceId,
            ':uploaded_by' => $uploadedByUserId,
            ':checksum'    => $sha256,
            ':year'        => $year,
            ':month'       => $month,
            ':version'     => $parserVersion,
            ':now'         => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function completeImportBatch(int $batchId, AttendanceImportSummary $summary): void
    {
        $now  = $this->utcNow();
        $stmt = $this->pdo->prepare(
            "UPDATE attendance_import_batch SET
                status              = 'Draft',
                records_parsed      = :parsed,
                records_matched     = :matched,
                records_unmatched   = :unmatched,
                duplicates_skipped  = :duplicates,
                incomplete_days     = :incomplete,
                multi_punch_days    = :multi,
                completed_at        = :now
              WHERE import_batch_id = :id"
        );
        $stmt->execute([
            ':parsed'     => $summary->parsedTokens,
            ':matched'    => $summary->matchedPunches,
            ':unmatched'  => $summary->unmatchedPunches,
            ':duplicates' => $summary->duplicatePunches,
            ':incomplete' => $summary->incompleteDays,
            ':multi'      => $summary->multiPunchDays,
            ':now'        => $now,
            ':id'         => $batchId,
        ]);
    }

    // -----------------------------------------------------------------------
    // Punch matching
    // -----------------------------------------------------------------------

    /**
     * Match a punch against employee_biometric_enrollment effective at the
     * punch timestamp, then resolve branch and schedule.
     */
    public function match(ParsedPunch $punch): AttendancePunchMatch
    {
        $punchLocal = $punch->localTimestamp->format('Y-m-d H:i:s');
        $punchDate  = $punch->localTimestamp->format('Y-m-d');

        // 1. Find enrollment effective at punch time for this device + code
        $stmt = $this->pdo->prepare(
            "SELECT ebe.employee_id, ebe.enrollment_id
               FROM employee_biometric_enrollment ebe
              WHERE ebe.device_id            = :device_id
                AND ebe.device_employee_code = :code
                AND ebe.effective_from      <= :enrollment_from_date
                AND (ebe.effective_to IS NULL OR ebe.effective_to > :enrollment_to_date)
                AND ebe.status = 'Active'
              LIMIT 1"
        );
        $stmt->execute([
            ':device_id'  => $punch->deviceId,
            ':code'       => $punch->enrollmentCode,
            ':enrollment_from_date' => $punchDate,
            ':enrollment_to_date'   => $punchDate,
        ]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$enrollment) {
            return AttendancePunchMatch::unmatched(AttendancePunchMatch::UNMATCHED_ENROLLMENT);
        }

        $employeeId = (int) $enrollment['employee_id'];

        // 2. Find branch assignment effective at punch date
        $stmt = $this->pdo->prepare(
            "SELECT eba.branch_assignment_id, eba.branch_id
               FROM employee_branch_assignment eba
              WHERE eba.employee_id    = :emp_id
                AND eba.effective_from <= :branch_from_date
                AND (eba.effective_to IS NULL OR eba.effective_to > :branch_to_date)
              ORDER BY eba.effective_from DESC
              LIMIT 1"
        );
        $stmt->execute([
            ':emp_id' => $employeeId,
            ':branch_from_date' => $punchDate,
            ':branch_to_date' => $punchDate,
        ]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$branch) {
            return AttendancePunchMatch::unmatched(AttendancePunchMatch::OUT_OF_COVERAGE);
        }

        $branchId = (int) $branch['branch_id'];

        // 3. Verify device covers this branch on punch date
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM biometric_device_branch
              WHERE device_id    = :device_id
                AND branch_id    = :branch_id
                AND effective_from <= :coverage_from_date
                AND (effective_to IS NULL OR effective_to > :coverage_to_date)
                AND status = 'Active'"
        );
        $stmt->execute([
            ':device_id'  => $punch->deviceId,
            ':branch_id'  => $branchId,
            ':coverage_from_date' => $punchDate,
            ':coverage_to_date'   => $punchDate,
        ]);
        if ((int) $stmt->fetchColumn() === 0) {
            return AttendancePunchMatch::unmatched(AttendancePunchMatch::OUT_OF_COVERAGE);
        }

        // 4. Find current work schedule for employee on punch date
        //    Now resolved via employee_schedule_assignment (work_schedule is no longer per-employee).
        $stmt = $this->pdo->prepare(
            "SELECT esa.schedule_id
               FROM employee_schedule_assignment esa
              WHERE esa.employee_id    = :emp_id
                AND esa.effective_from <= :schedule_from_date
                AND (esa.effective_to IS NULL OR esa.effective_to > :schedule_to_date)
                AND esa.status = 'Active'
              ORDER BY esa.effective_from DESC
              LIMIT 1"
        );
        $stmt->execute([
            ':emp_id' => $employeeId,
            ':schedule_from_date' => $punchDate,
            ':schedule_to_date' => $punchDate,
        ]);
        $schedule   = $stmt->fetch(PDO::FETCH_ASSOC);
        $scheduleId = $schedule ? (int) $schedule['schedule_id'] : 0;

        return AttendancePunchMatch::matched($employeeId, $branchId, $scheduleId);
    }

    // -----------------------------------------------------------------------
    // Effective schedule loader
    // -----------------------------------------------------------------------

    public function effectiveSchedule(int $scheduleId, string $attendanceDate): WorkSchedule
    {
        if ($scheduleId === 0) {
            // Fallback: standard 7am-4pm schedule if none found
            return new WorkSchedule(0, '07:00', '16:00', 60, 480);
        }

        $stmt = $this->pdo->prepare(
            "SELECT schedule_id, work_start_time, work_end_time,
                    break_minutes, standard_minutes
               FROM work_schedule
              WHERE schedule_id = :id"
        );
        $stmt->execute([':id' => $scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return new WorkSchedule(0, '07:00', '16:00', 60, 480);
        }

        return new WorkSchedule(
            (int) $row['schedule_id'],
            substr((string) $row['work_start_time'], 0, 5),
            substr((string) $row['work_end_time'], 0, 5),
            (int) $row['break_minutes'],
            (int) $row['standard_minutes'],
        );
    }

    // -----------------------------------------------------------------------
    // Duplicate punch check
    // -----------------------------------------------------------------------

    public function isDuplicatePunch(ParsedPunch $punch): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM biometric_punch
              WHERE device_id            = :device_id
                AND device_employee_code = :code
                AND source_local_at      = :local_at"
        );
        $stmt->execute([
            ':device_id' => $punch->deviceId,
            ':code'      => $punch->enrollmentCode,
            ':local_at'  => $punch->localTimestamp->format('Y-m-d H:i:s'),
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // -----------------------------------------------------------------------
    // Punch persistence
    // -----------------------------------------------------------------------

    public function retainUnmatchedPunch(int $batchId, ParsedPunch $punch, string $reason): void
    {
        $this->insertPunch($batchId, $punch, null, null, $reason);
    }

    public function retainMatchedPunch(int $batchId, ParsedPunch $punch, int $employeeId, int $branchId): void
    {
        // Look up branch_assignment_id for this employee on punch date
        $punchDate = $punch->localTimestamp->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT branch_assignment_id FROM employee_branch_assignment
              WHERE employee_id = :emp_id
                AND effective_from <= :branch_from_date
                AND (effective_to IS NULL OR effective_to > :branch_to_date)
              ORDER BY effective_from DESC LIMIT 1"
        );
        $stmt->execute([
            ':emp_id' => $employeeId,
            ':branch_from_date' => $punchDate,
            ':branch_to_date' => $punchDate,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $baId = $row ? (int) $row['branch_assignment_id'] : null;

        $this->insertPunch($batchId, $punch, $employeeId, $baId, AttendancePunchMatch::MATCHED);
    }

    private function insertPunch(int $batchId, ParsedPunch $punch, ?int $employeeId, ?int $branchAssignmentId, string $matchStatus): void
    {
        $localAt  = $punch->localTimestamp->format('Y-m-d H:i:s');
        $utcAt    = $punch->localTimestamp->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $now      = $this->utcNow();

        $stmt = $this->pdo->prepare(
            "INSERT INTO biometric_punch
                (import_batch_id, device_id, employee_id, branch_assignment_id,
                 device_employee_code, source_local_at, punched_at_utc,
                 punch_type, match_status,
                 source_department, source_user_id, source_employee_name,
                 raw_record, source_workbook_row, source_date_column,
                 created_at)
             VALUES
                (:batch_id, :device_id, :emp_id, :ba_id,
                 :code, :local_at, :utc_at,
                 'Unknown', :match_status,
                 :dept, :src_user_id, :src_name,
                 :raw, :row, :col,
                 :now)"
        );
        $stmt->execute([
            ':batch_id'     => $batchId,
            ':device_id'    => $punch->deviceId,
            ':emp_id'       => $employeeId,
            ':ba_id'        => $branchAssignmentId,
            ':code'         => $punch->enrollmentCode,
            ':local_at'     => $localAt,
            ':utc_at'       => $utcAt,
            ':match_status' => $matchStatus,
            ':dept'         => $punch->department,
            ':src_user_id'  => $punch->sourceUserId,
            ':src_name'     => $punch->sourceName,
            ':raw'          => $punch->rawCellValue,
            ':row'          => $punch->sourceRow,
            ':col'          => (string) $punch->sourceColumn,
            ':now'          => $now,
        ]);
    }

    // -----------------------------------------------------------------------
    // Generated attendance persistence
    // -----------------------------------------------------------------------

    public function saveGeneratedAttendance(int $batchId, GeneratedAttendance $attendance): void
    {
        $now      = $this->utcNow();
        $timeIn   = $attendance->timeIn  ? $attendance->timeIn->format('H:i:s')  : null;
        $timeOut  = $attendance->timeOut ? $attendance->timeOut->format('H:i:s') : null;

        // Determine status from flags
        $status = 'Complete';
        if ($attendance->isIncomplete()) {
            $status = 'Incomplete';
        } elseif (in_array('MULTI_PUNCH_REVIEW', $attendance->flags, true)) {
            $status = 'ReviewRequired';
        }

        // Resolve branch_assignment_id and schedule_id for this employee/date
        $punchDate = $attendance->attendanceDate;
        $stmt = $this->pdo->prepare(
            "SELECT branch_assignment_id FROM employee_branch_assignment
              WHERE employee_id = :emp_id
                AND effective_from <= :branch_from_date
                AND (effective_to IS NULL OR effective_to > :branch_to_date)
              ORDER BY effective_from DESC LIMIT 1"
        );
        $stmt->execute([
            ':emp_id'            => $attendance->employeeId,
            ':branch_from_date'  => $punchDate,
            ':branch_to_date'    => $punchDate,
        ]);
        $baRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $baId  = $baRow ? (int) $baRow['branch_assignment_id'] : null;

        $stmt = $this->pdo->prepare(
            "SELECT esa.schedule_id
               FROM employee_schedule_assignment esa
              WHERE esa.employee_id = :emp_id
                AND esa.effective_from <= :schedule_from_date
                AND (esa.effective_to IS NULL OR esa.effective_to > :schedule_to_date)
                AND esa.status = 'Active'
              ORDER BY esa.effective_from DESC LIMIT 1"
        );
        $stmt->execute([
            ':emp_id'              => $attendance->employeeId,
            ':schedule_from_date'  => $punchDate,
            ':schedule_to_date'    => $punchDate,
        ]);
        $schRow     = $stmt->fetch(PDO::FETCH_ASSOC);
        $scheduleId = $schRow ? (int) $schRow['schedule_id'] : null;

        // Check for an existing attendance row for this employee+date.
        //
        // The weekly-upload workflow means HR uploads the same month's XLS again
        // each week as the device accumulates more data. Days from prior weeks
        // will already have attendance rows. Rules:
        //
        //   Existing row belongs to a Cancelled batch → treat as absent (INSERT/UPDATE over it)
        //   Different branch              → throw  (data conflict; HR must resolve)
        //   Same branch + Approved        → throw  (immutable per ADR-0002)
        //   Same branch + Incomplete
        //     AND new data has both punches → UPDATE (boundary-day completion)
        //   Same branch + anything else   → skip silently (already fully captured)
        $existing = $this->pdo->prepare(
            "SELECT a.attendance_id, a.status, eba.branch_id,
                    COALESCE(aib.status, 'manual') AS batch_status
               FROM attendance a
               JOIN employee_branch_assignment eba
                 ON eba.branch_assignment_id = a.branch_assignment_id
               LEFT JOIN attendance_import_batch aib
                 ON aib.import_batch_id = a.import_batch_id
              WHERE a.employee_id    = :emp_id
                AND a.attendance_date = :date
              FOR UPDATE"
        );
        $existing->execute([':emp_id' => $attendance->employeeId, ':date' => $punchDate]);
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

        if ($existingRow !== false) {
            // If the existing row came from a cancelled batch, it is superseded —
            // delete it and fall through to the normal INSERT below.
            if ((string) $existingRow['batch_status'] === 'Cancelled') {
                $this->pdo->prepare(
                    'DELETE FROM attendance WHERE attendance_id = :id'
                )->execute([':id' => (int) $existingRow['attendance_id']]);
                // Fall through to INSERT
            } else {
                $sameBranch     = (int) $existingRow['branch_id'] === $this->branchIdForAssignment($baId);
                $existingStatus = (string) $existingRow['status'];
                $existingId     = (int) $existingRow['attendance_id'];

                if (!$sameBranch) {
                    throw new \RuntimeException(
                        'Attendance already exists for this employee and date under another branch. '
                        . 'Resolve that branch assignment before importing.'
                    );
                }

                if ($existingStatus === 'Approved') {
                    throw new \RuntimeException(
                        'Attendance for this employee and date has already been approved and is immutable.'
                    );
                }

                // Incomplete row + new upload now supplies both time-in and time-out
                // → complete the record in-place so the weekly payroll can use it.
                if ($existingStatus === 'Incomplete' && $timeIn !== null && $timeOut !== null) {
                    $this->pdo->prepare(
                        "UPDATE attendance
                            SET time_in              = :time_in,
                                time_out             = :time_out,
                                hours_worked_minutes = :worked,
                                late_minutes         = :late,
                                undertime_minutes    = :undertime,
                                overtime_minutes     = :overtime,
                                status               = :status,
                                import_batch_id      = :batch_id,
                                updated_at           = :updated_at
                          WHERE attendance_id = :id"
                    )->execute([
                        ':time_in'    => $timeIn,
                        ':time_out'   => $timeOut,
                        ':worked'     => $attendance->workedMinutes,
                        ':late'       => $attendance->lateMinutes,
                        ':undertime'  => $attendance->undertimeMinutes,
                        ':overtime'   => $attendance->overtimeMinutes,
                        ':status'     => $status,
                        ':batch_id'   => $batchId,
                        ':updated_at' => $now,
                        ':id'         => $existingId,
                    ]);
                    return;
                }

                // Complete or ReviewRequired (same branch, not approved): the prior
                // import already captured this day fully — skip silently.
                return;
            }
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO attendance
                (employee_id, branch_assignment_id, schedule_id,
                 attendance_date, time_in, time_out,
                 hours_worked_minutes, late_minutes, undertime_minutes, overtime_minutes,
                 status, source, import_batch_id,
                 created_at, updated_at)
             VALUES
                (:emp_id, :ba_id, :sch_id,
                 :date, :time_in, :time_out,
                 :worked, :late, :undertime, :overtime,
                 :status, 'xls_import', :batch_id,
                 :created_at, :updated_at)"
        );
        $stmt->execute([
            ':emp_id'     => $attendance->employeeId,
            ':ba_id'      => $baId,
            ':sch_id'     => $scheduleId,
            ':date'       => $punchDate,
            ':time_in'    => $timeIn,
            ':time_out'   => $timeOut,
            ':worked'     => $attendance->workedMinutes,
            ':late'       => $attendance->lateMinutes,
            ':undertime'  => $attendance->undertimeMinutes,
            ':overtime'   => $attendance->overtimeMinutes,
            ':status'     => $status,
            ':batch_id'   => $batchId,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function utcNow(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function branchIdForAssignment(?int $branchAssignmentId): int
    {
        if ($branchAssignmentId === null) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT branch_id FROM employee_branch_assignment WHERE branch_assignment_id = :id'
        );
        $stmt->execute([':id' => $branchAssignmentId]);
        return (int) $stmt->fetchColumn();
    }
}
