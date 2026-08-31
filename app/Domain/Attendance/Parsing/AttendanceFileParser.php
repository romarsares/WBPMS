<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

interface AttendanceFileParser
{
    public function supports(UploadedAttendanceFile $file): bool;

    /** @throws AttendanceParseException */
    public function parse(UploadedAttendanceFile $file, ParserContext $context): ParsedAttendanceFile;
}
