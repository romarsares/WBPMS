<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

use RuntimeException;

final class AttendanceParseException extends RuntimeException
{
    /** @param list<ParserError> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The attendance workbook could not be processed safely.');
    }
}
