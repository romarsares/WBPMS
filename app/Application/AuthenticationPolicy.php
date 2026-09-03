<?php

declare(strict_types=1);

namespace Wbpms\Application;

/** Central authentication rules shared by login, sessions, and user management. */
final class AuthenticationPolicy
{
    public const UNLINKED_EMPLOYEE_MESSAGE =
        'Your account is not linked to an employee profile. Please contact HR.';

    public static function isUnlinkedEmployee(string $roleName, ?int $employeeId): bool
    {
        return $roleName === 'Employee' && $employeeId === null;
    }
}