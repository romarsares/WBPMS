<?php

declare(strict_types=1);

namespace Wbpms\Application\Attendance;

final class AttendancePunchMatch
{
    /** These values map directly to biometric_punch.match_status. */
    public const MATCHED = 'matched';
    public const UNMATCHED_ENROLLMENT = 'unmatched';
    public const OUT_OF_COVERAGE = 'coverage_exception';

    private function __construct(
        public string $status,
        public ?int $employeeId = null,
        public ?int $branchId = null,
        public ?int $scheduleId = null,
    ) {
    }

    public static function matched(int $employeeId, int $branchId, int $scheduleId): self
    {
        return new self(self::MATCHED, $employeeId, $branchId, $scheduleId);
    }

    public static function unmatched(string $status = self::UNMATCHED_ENROLLMENT): self
    {
        return new self($status);
    }
}
