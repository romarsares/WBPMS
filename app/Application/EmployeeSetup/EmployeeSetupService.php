<?php

declare(strict_types=1);

namespace Wbpms\Application\EmployeeSetup;

use InvalidArgumentException;

/** Minimal P0 employee/schedule/enrollment setup path for attendance import. */
final readonly class EmployeeSetupService
{
    public function __construct(private EmployeeSetupGateway $gateway)
    {
    }

    /** @param array{employeeNumber:string, firstName:string, lastName:string} $employee */
    public function createForAttendance(array $employee, int $branchId, int $scheduleId, int $deviceId, string $enrollmentCode, string $effectiveFrom): int
    {
        if ($branchId < 1 || $scheduleId < 1 || $deviceId < 1 || trim($enrollmentCode) === ''
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveFrom)) {
            throw new InvalidArgumentException('Invalid employee attendance setup.');
        }
        $employeeId = $this->gateway->createEmployee($employee);
        $this->gateway->assignInitialBranch($employeeId, $branchId, $effectiveFrom);
        $this->gateway->assignEffectiveSchedule($employeeId, $scheduleId, $effectiveFrom);
        $this->gateway->enrollBiometricCode($employeeId, $deviceId, trim($enrollmentCode), $effectiveFrom);
        return $employeeId;
    }
}
