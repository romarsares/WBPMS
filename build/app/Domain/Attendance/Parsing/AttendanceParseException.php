<?php

declare(strict_types=1);

namespace Wbpms\Domain\Attendance\Parsing;

use RuntimeException;

final class AttendanceParseException extends RuntimeException
{
    /** @var list<ParserError> */
    public array $errors;

    /** @param list<ParserError> $errors */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('The attendance workbook could not be processed safely.');
    }
}
