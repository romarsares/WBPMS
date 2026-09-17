<?php

declare(strict_types=1);

namespace Wbpms\Application\EmployeePortal;

use DomainException;

/** Enforces REQ075/REQ081 ownership before a repository query is made. */
final class EmployeeScope
{
    public function assertOwnRecord(int $authenticatedEmployeeId, int $requestedEmployeeId): void
    {
        if ($authenticatedEmployeeId !== $requestedEmployeeId) {
            throw new DomainException('FORBIDDEN_EMPLOYEE_RECORD');
        }
    }
}
