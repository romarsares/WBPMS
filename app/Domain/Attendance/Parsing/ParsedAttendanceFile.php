<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

final class ParsedAttendanceFile
{
    /** @param list<ParsedPunch> $punches */
    public function __construct(
        public string $parserVersion,
        public string $sha256,
        public array $punches,
    ) {
    }
}
