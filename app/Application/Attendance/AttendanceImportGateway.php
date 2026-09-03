<?php

declare(strict_types=1);

namespace Wbpms\Application\Attendance;

use Wbpms\Domain\Attendance\GeneratedAttendance;
use Wbpms\Domain\Attendance\WorkSchedule;
use Wbpms\Domain\Attendance\Parsing\ParsedPunch;

/**
 * Persistence contract for Developer A's PDO/transaction foundation.
 * Its transaction method must commit all calls or roll them all back.
 */
interface AttendanceImportGateway
{
    public function transactional(callable $operation): mixed;

    /** File checksum is globally unique under uq_batch_checksum. */
    public function hasCompletedChecksum(string $sha256): bool;

    public function createImportBatch(int $deviceId, int $uploadedByUserId, int $year, int $month, string $sha256, string $parserVersion): int;

    public function match(ParsedPunch $punch): AttendancePunchMatch;

    public function effectiveSchedule(int $scheduleId, string $attendanceDate): WorkSchedule;

    public function isDuplicatePunch(ParsedPunch $punch): bool;

    public function retainUnmatchedPunch(int $batchId, ParsedPunch $punch, string $reason): void;

    public function retainMatchedPunch(int $batchId, ParsedPunch $punch, int $employeeId, int $branchId): void;

    public function saveGeneratedAttendance(int $batchId, GeneratedAttendance $attendance): void;

    public function completeImportBatch(int $batchId, AttendanceImportSummary $summary): void;
}
