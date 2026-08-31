<?php

declare(strict_types=1);

namespace Wbpms\Application\Attendance;

/** Contract handed to the HR view and payroll lane after a successful import. */
final readonly class AttendanceImportSummary
{
    /** @param array<int, array{matched:int, unmatched:int, duplicates:int, incomplete:int, multiPunch:int}> $byBranch */
    public function __construct(
        public int $parsedTokens,
        public int $matchedPunches,
        public int $unmatchedPunches,
        public int $duplicatePunches,
        public int $incompleteDays,
        public int $multiPunchDays,
        public array $byBranch,
    ) {
    }
}
