<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

use DateTimeImmutable;

/** Immutable source evidence; matching and time-in/out classification happen later. */
final readonly class ParsedPunch
{
    public function __construct(
        public int $deviceId,
        public string $enrollmentCode,
        public DateTimeImmutable $localTimestamp,
        public int $sourceRow,
        public string $sourceColumn,
        public string $rawCellValue,
        public ?string $department,
        public ?string $sourceUserId,
        public ?string $sourceName,
    ) {
    }
}
