<?php

declare(strict_types=1);

namespace Wbpms\Application\EmployeeSetup;

/** Shared migration/repository boundary for B2 master-data forms. */
interface EmployeeSetupGateway
{
    public function createEmployee(array $attributes): int;

    public function assignInitialBranch(int $employeeId, int $branchId, string $effectiveFrom): void;

    public function assignEffectiveSchedule(int $employeeId, int $scheduleId, string $effectiveFrom): void;

    public function enrollBiometricCode(int $employeeId, int $deviceId, string $enrollmentCode, string $effectiveFrom): void;
}
