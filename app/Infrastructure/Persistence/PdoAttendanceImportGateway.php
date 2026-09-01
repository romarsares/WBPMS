<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Wbpms\Application\Attendance\AttendanceImportGateway;
use Wbpms\Application\Attendance\AttendanceImportSummary;
use Wbpms\Application\Attendance\AttendancePunchMatch;
use Wbpms\Domain\Attendance\GeneratedAttendance;
use Wbpms\Domain\Attendance\WorkSchedule;
use Wbpms\Domain\Attendance\Parsing\ParsedPunch;

/**
 * PDO implementation of AttendanceImportGateway.
 *
 * Owns all SQL for the attendance import transaction:
 *   - attendance_import_batch (create, complete)
 *   - employee_biometric_enrollment (match punch to employee)
 *   - employee_branch_assignment (resolve current branch)
 *   - work_schedule (resolve effective schedule)
 *   - biometric_punch (retain matched and unmatched evidence)
 *   - attendance + attendance_punch (persist generated timesheet rows)
 *
 * REQ018–REQ024, ADR-0001 biometric workbook contract, ADR-0002 §4/§5.
 */
final class PdoAttendanceImportGateway implements AttendanceImportGateway
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // -----------------------------------------------------------------------
    // Transactional boundary
    // -----------------------------------------------------------------------

    public function transactional(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Duplicate-file guard
    // -----------------------------------------------------------------------

    public function hasCompletedChecksum(string $sha256): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM attendance_import_batch
              WHERE file_checksum = :checksum
                AND status = 'Completed'"
        );
        $stmt->execute([':checksum' => $sha256]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // -----------------------------------------------------------------------
    // Batch lifecycle
    // -----------------------------------------------------------------------

    public function createImportBatch(
        int $deviceId,
        int $uploadedByUserId,
        int $year,
        int $month,
        string $sha256,
        string $parserVersion
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO attendance_import_batch
                 (device_id, uploaded_by, file_name, file_checksum,
                  source_year, source_month, parser_version,
                  status, uploaded_at)
             VALUES
                 (:device_id, :uploaded_by, :file_name, :checksum,
                  :year, :month, :parser_version,
                  'Processing', NOW())"
        );
        $stmt->execute([
            ':device_id'       => $deviceId,
            ':uploaded_by'     => $uploadedByUserId,
            ':file_name'       => '',          // caller may update; file name passed at controller level
            ':checksum'        => $sha256,
            ':year'            => $year,
            ':month'           => $month,
            ':parser_version'  => $parserVersion,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function completeImportBatch(int $batchId, AttendanceImportSummary $summary): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE attendance_import_batch
                SET status            = 'Completed',
                    records_parsed    = :parsed,
                    records_matched   = :matched,
                    records_unmatched = :unmatched,
                    duplicates_skipped = :dupes,
                    incomplete_days   = :incomplete,
                    multi_punch_days  = :multi,
                    completed_at      = NOW()
              WHERE import_batch_id = :id"
        );
        $stmt->execute([
            ':parsed'     => $summary->parsedTokens,
            ':matched'    => $summary->matchedPunches,
            ':unmatched'  => $summary->unmatchedPunches,
            ':dupes'      => $summary->duplicatePunches,
            ':incomplete' => $summary->incompleteDays,
            ':multi'      => $summary->multiPunchDays,
            ':id'         => $batchId,
        ]);
    }

    // -----------------------------------------------------------------------
    // Punch-to-employee matching
    // -----------------------------------------------------------------------

    /**
     * Resolve enrollment code + device → employee, branch assignment, schedule.
     *
     * Matching logic (ADR-0001 / ADR-0003):
     *   1. Find an active enrollment for this device + code on the punch date.
     *   2. Resolve the employee's branch assignment effective on that date.
     *   3. Resolve the employee's work schedule effective on that date.
     *
     * Returns UNMATCHED_ENROLLMENT when step 1 fails.
     * Returns OUT_OF_COVERAGE when step 2 or 3 fails.
     */
    public function match(ParsedPunch $punch): AttendancePunchMatch
    {
        $punchDate = $punch->localTimestamp->format('Y-m-d');

        // Step 1 — enrollment lookup
        $stmt = $this->pdo->prepare(
            "SELECT ebe.employee_id
               FROM employee_biometric_enrollment ebe
              WHERE ebe.device_id           = :device_id
                AND ebe.device_employee_code = :code
                AND ebe.effective_from      <= :punch_date
                AND (ebe.effective_to IS NULL OR ebe.effective_to > :punch_date2)
                AND ebe.status = 'Active'
              LIMIT 1"
        );
        $stmt->execute([
            ':device_id'   => $punch->deviceId,
            ':code'        => $punch->enrollmentCode,
            ':punch_date'  => $punchDate,
            ':punch_date2' => $punchDate,
        ]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($enrollment === false) {
            return AttendancePunchMatch::unmatched(AttendancePunchMatch::UNMATCHED_ENROLLMENT);
        }

        $employeeId = (int) $enrollment['employee_id'];

        // Step 2 — branch assignment
        $stmt = $this->pdo->prepare(
            "SELECT eba.branch_assignment_id, eba.branch_id
               FROM employee_branch_assignment eba
              WHERE eba.employee_id    = :employee_id
                AND eba.effective_from <= :punch_date
                AND (eba.effective_to IS NULL OR eba.effective_to > :punch_date2)
              LIMIT 1"
        );
        $stmt->execute([
            ':employee_id'  => $employeeId,
            ':punch_date'   => $punchDate,
            ':punch_date2'  => $punchDate,
        ]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($assignment === false) {
            return AttendancePunchMatch::unmatched(AttendancePunchMatch::OUT_OF_COVERAGE);
        }

        // Step 3 — work schedule
        $stmt = $this->pdo->prepare(
            "SELECT ws.schedule_id
               FROM work_schedule ws
              WHERE ws.employee_id    = :employee_id
                AND ws.effective_from <= :punch_date
                AND (ws.effective_to IS NULL OR ws.effective_to > :punch_date2)
                AND ws.status = 'Active'
              LIMIT 1"
        );
        $stmt->execute([
            ':employee_id'  => $employeeId,
            ':punch_date'   => $punchDate,
            ':punch_date2'  => $punchDate,
        ]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($schedule === false) {
            return AttendancePunchMatch::unmatched(AttendancePunchMatch::OUT_OF_COVERAGE);
        }

        return AttendancePunchMatch::matched(
            $employeeId,
            (int) $assignment['branch_id'],
            (int) $schedule['schedule_id'],
        );
    }

    // -----------------------------------------------------------------------
    // Effective schedule retrieval
    // -----------------------------------------------------------------------

    public function effectiveSchedule(int $scheduleId, string $attendanceDate): WorkSchedule
    {
        $stmt = $this->pdo->prepare(
            "SELECT schedule_id, work_start_time, work_end_time,
                    break_minutes, standard_minutes
               FROM work_schedule
              WHERE schedule_id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException("work_schedule {$scheduleId} not found.");
        }

        // work_start_time/work_end_time stored as HH:MM:SS — trim to HH:MM
        $start = substr((string) $row['work_start_time'], 0, 5);
        $end   = substr((string) $row['work_end_time'], 0, 5);

        return new WorkSchedule(
            (int) $row['schedule_id'],
            $start,
            $end,
            (int) $row['break_minutes'],
            (int) $row['standard_minutes'],
        );
    }

    // -----------------------------------------------------------------------
    // Duplicate-punch guard
    // -----------------------------------------------------------------------

    public function isDuplicatePunch(ParsedPunch $punch): bool
    {
        // Unique constraint: (device_id, device_employee_code, source_local_at)
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
        $utc = $punch->localTimestamp
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            "INSERT INTO biometric_punch
                 (import_batch_id, device_id, employee_id, branch_assignment_id,
                  device_employee_code, source_local_at, punched_at_utc,
                  match_status, source_department, source_user_id,
                  source_employee_name, raw_record,
                  source_workbook_row, source_date_column, created_at)
             VALUES
                 (:batch_id, :device_id, NULL, NULL,
                  :code, :local_at, :utc_at,
                  :status, :dept, :src_user,
                  :src_name, :raw,
                  :row, :col, NOW())"
        );
        $stmt->execute([
            ':batch_id'  => $batchId,
            ':device_id' => $punch->deviceId,
            ':code'      => $punch->enrollmentCode,
            ':local_at'  => $punch->localTimestamp->format('Y-m-d H:i:s'),
            ':utc_at'    => $utc,
            ':status'    => $reason,
            ':dept'      => $punch->department,
            ':src_user'  => $punch->sourceUserId,
            ':src_name'  => $punch->sourceName,
            ':raw'       => $punch->rawCellValue,
            ':row'       => $punch->sourceRow,
            ':col'       => $punch->sourceColumn,
        ]);
    }

    public function retainMatchedPunch(int $batchId, ParsedPunch $punch, int $employeeId, int $branchId): void
    {
        $utc = $punch->localTimestamp
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        // Resolve branch_assignment_id for the punch date
        $punchDate = $punch->localTimestamp->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT branch_assignment_id
               FROM employee_branch_assignment
              WHERE employee_id    = :emp
                AND branch_id      = :branch
                AND effective_from <= :d
                AND (effective_to IS NULL OR effective_to > :d2)
              LIMIT 1"
        );
        $stmt->execute([
            ':emp'    => $employeeId,
            ':branch' => $branchId,
            ':d'      => $punchDate,
            ':d2'     => $punchDate,
        ]);
        $asgn = $stmt->fetch(PDO::FETCH_ASSOC);
        $branchAssignmentId = $asgn !== false ? (int) $asgn['branch_assignment_id'] : null;

        $stmt = $this->pdo->prepare(
            "INSERT INTO biometric_punch
                 (import_batch_id, device_id, employee_id, branch_assignment_id,
                  device_employee_code, source_local_at, punched_at_utc,
                  match_status, source_department, source_user_id,
                  source_employee_name, raw_record,
                  source_workbook_row, source_date_column, created_at)
             VALUES
                 (:batch_id, :device_id, :employee_id, :asgn_id,
                  :code, :local_at, :utc_at,
                  'matched', :dept, :src_user,
                  :src_name, :raw,
                  :row, :col, NOW())"
        );
        $stmt->execute([
            ':batch_id'    => $batchId,
            ':device_id'   => $punch->deviceId,
            ':employee_id' => $employeeId,
            ':asgn_id'     => $branchAssignmentId,
            ':code'        => $punch->enrollmentCode,
            ':local_at'    => $punch->localTimestamp->format('Y-m-d H:i:s'),
            ':utc_at'      => $utc,
            ':dept'        => $punch->department,
            ':src_user'    => $punch->sourceUserId,
            ':src_name'    => $punch->sourceName,
            ':raw'         => $punch->rawCellValue,
            ':row'         => $punch->sourceRow,
            ':col'         => $punch->sourceColumn,
        ]);
    }

    // -----------------------------------------------------------------------
    // Attendance row persistence
    // -----------------------------------------------------------------------

    public function saveGeneratedAttendance(GeneratedAttendance $attendance): void
    {
        // Resolve branch_assignment_id and schedule_id for this employee + date
        $stmt = $this->pdo->prepare(
            "SELECT eba.branch_assignment_id, ws.schedule_id
               FROM employee_branch_assignment eba
               JOIN work_schedule ws
                 ON ws.employee_id    = eba.employee_id
                AND ws.effective_from <= :d
                AND (ws.effective_to IS NULL OR ws.effective_to > :d2)
                AND ws.status = 'Active'
              WHERE eba.employee_id    = :emp
                AND eba.effective_from <= :d3
                AND (eba.effective_to IS NULL OR eba.effective_to > :d4)
              LIMIT 1"
        );
        $stmt->execute([
            ':emp' => $attendance->employeeId,
            ':d'   => $attendance->attendanceDate,
            ':d2'  => $attendance->attendanceDate,
            ':d3'  => $attendance->attendanceDate,
            ':d4'  => $attendance->attendanceDate,
        ]);
        $context = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($context === false) {
            // No branch/schedule context — skip; retainMatchedPunch already stored evidence
            return;
        }

        $branchAssignmentId = (int) $context['branch_assignment_id'];
        $scheduleId         = (int) $context['schedule_id'];

        $hasIncomplete      = in_array('INCOMPLETE', $attendance->flags, true);
        $hasMultiPunch      = in_array('MULTI_PUNCH_REVIEW', $attendance->flags, true);
        $status = $hasMultiPunch ? 'ReviewRequired'
                : ($hasIncomplete ? 'Incomplete' : 'Complete');

        $timeIn  = $attendance->timeIn  !== null ? $attendance->timeIn->format('H:i:s')  : null;
        $timeOut = $attendance->timeOut !== null ? $attendance->timeOut->format('H:i:s') : null;

        // Resolve import_batch_id from the first punch's batch
        $batchId = $this->resolveBatchIdForPunches($attendance);

        // Upsert: if a row already exists for this employee+date, update it
        $stmt = $this->pdo->prepare(
            "INSERT INTO attendance
                 (employee_id, branch_assignment_id, schedule_id,
                  attendance_date, time_in, time_out,
                  hours_worked_minutes, late_minutes, undertime_minutes, overtime_minutes,
                  status, source, import_batch_id, created_at, updated_at)
             VALUES
                 (:emp, :asgn, :sched,
                  :date, :time_in, :time_out,
                  :worked, :late, :undertime, :overtime,
                  :status, 'xls_import', :batch_id, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                  time_in               = VALUES(time_in),
                  time_out              = VALUES(time_out),
                  hours_worked_minutes  = VALUES(hours_worked_minutes),
                  late_minutes          = VALUES(late_minutes),
                  undertime_minutes     = VALUES(undertime_minutes),
                  overtime_minutes      = VALUES(overtime_minutes),
                  status                = VALUES(status),
                  updated_at            = NOW()"
        );
        $stmt->execute([
            ':emp'       => $attendance->employeeId,
            ':asgn'      => $branchAssignmentId,
            ':sched'     => $scheduleId,
            ':date'      => $attendance->attendanceDate,
            ':time_in'   => $timeIn,
            ':time_out'  => $timeOut,
            ':worked'    => $attendance->workedMinutes,
            ':late'      => $attendance->lateMinutes,
            ':undertime' => $attendance->undertimeMinutes,
            ':overtime'  => $attendance->overtimeMinutes,
            ':status'    => $status,
            ':batch_id'  => $batchId,
        ]);

        $attendanceId = (int) $this->pdo->lastInsertId();
        if ($attendanceId === 0) {
            // ON DUPLICATE KEY UPDATE path — fetch the existing id
            $lookup = $this->pdo->prepare(
                "SELECT attendance_id FROM attendance
                  WHERE employee_id = :emp AND attendance_date = :date LIMIT 1"
            );
            $lookup->execute([':emp' => $attendance->employeeId, ':date' => $attendance->attendanceDate]);
            $attendanceId = (int) $lookup->fetchColumn();
        }

        if ($attendanceId === 0) {
            return;
        }

        // Link each punch via attendance_punch
        $this->linkAttendancePunches($attendanceId, $attendance);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Find the import_batch_id for the punches in a GeneratedAttendance.
     * Uses the device_id + enrollment_code + local_at of the first punch.
     */
    private function resolveBatchIdForPunches(GeneratedAttendance $attendance): ?int
    {
        if (empty($attendance->punches)) {
            return null;
        }
        $first = $attendance->punches[0];
        $stmt = $this->pdo->prepare(
            "SELECT import_batch_id FROM biometric_punch
              WHERE device_id            = :device
                AND device_employee_code = :code
                AND source_local_at      = :local_at
              LIMIT 1"
        );
        $stmt->execute([
            ':device'   => $first->deviceId,
            ':code'     => $first->enrollmentCode,
            ':local_at' => $first->localTimestamp->format('Y-m-d H:i:s'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (int) $row['import_batch_id'] : null;
    }

    /**
     * Insert attendance_punch linking rows for every punch in the attendance record.
     * punch_id is resolved via the unique index (device_id, code, local_at).
     */
    private function linkAttendancePunches(int $attendanceId, GeneratedAttendance $attendance): void
    {
        $punches = $attendance->punches;
        usort($punches, static fn ($a, $b) => $a->localTimestamp <=> $b->localTimestamp);
        $count = count($punches);

        foreach ($punches as $index => $punch) {
            $role = match (true) {
                $count === 1                => 'Unclassified',
                $index === 0                => 'TimeIn',
                $index === $count - 1       => 'TimeOut',
                default                     => 'Intermediate',
            };

            // Resolve punch_id
            $stmt = $this->pdo->prepare(
                "SELECT punch_id FROM biometric_punch
                  WHERE device_id            = :device
                    AND device_employee_code = :code
                    AND source_local_at      = :local_at
                  LIMIT 1"
            );
            $stmt->execute([
                ':device'   => $punch->deviceId,
                ':code'     => $punch->enrollmentCode,
                ':local_at' => $punch->localTimestamp->format('Y-m-d H:i:s'),
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                continue;
            }
            $punchId = (int) $row['punch_id'];

            // attendance_punch has uq_ap_punch_id — skip on duplicate
            $link = $this->pdo->prepare(
                "INSERT IGNORE INTO attendance_punch
                     (attendance_id, punch_id, evidence_role, created_at)
                 VALUES (:att_id, :punch_id, :role, NOW())"
            );
            $link->execute([
                ':att_id'   => $attendanceId,
                ':punch_id' => $punchId,
                ':role'     => $role,
            ]);
        }
    }
}
