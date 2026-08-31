<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance;

use Wbpms\Domain\Attendance\Parsing\ParsedPunch;
use DateTimeImmutable;

final readonly class GeneratedAttendance
{
    /** @param list<ParsedPunch> $punches @param list<string> $flags */
    public function __construct(
        public int $employeeId,
        public string $attendanceDate,
        public ?DateTimeImmutable $timeIn,
        public ?DateTimeImmutable $timeOut,
        public int $workedMinutes,
        public int $lateMinutes,
        public int $undertimeMinutes,
        public int $overtimeMinutes,
        public array $punches,
        public array $flags,
    ) {
    }

    public function isIncomplete(): bool
    {
        return in_array('INCOMPLETE', $this->flags, true);
    }
}
