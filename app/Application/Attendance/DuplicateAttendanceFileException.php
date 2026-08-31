<?php

declare(strict_types=1);

namespace Wbpms\Application\Attendance;

use DomainException;

final class DuplicateAttendanceFileException extends DomainException
{
    public function __construct()
    {
        parent::__construct('DUPLICATE_ATTENDANCE_FILE');
    }
}
